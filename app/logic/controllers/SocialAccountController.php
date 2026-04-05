<?php

namespace App\Controllers;

use App\Services\Platform\Adapters\BlueskyAdapter;
use App\Services\Platform\Adapters\DiscordAdapter;
use App\Services\Platform\Adapters\GoogleBusinessAdapter;
use App\Services\Platform\Adapters\WhatsAppChannelsAdapter;
use App\Services\Platform\Adapters\WordPressAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PlatformRegistry;
use App\Services\SocialAccount\AccountLinkingService;
use App\Services\SocialAccount\SocialAccountLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class SocialAccountController extends Controller
{
    public function __construct(
        private readonly AccountLinkingService $linkingService,
        private readonly PlatformRegistry      $registry,
    ) {}

    /**
     * Initiate the OAuth redirect for a given platform.
     */
    public function connect(Request $request, string $platform): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $platformEnum = $this->resolvePlatform($platform);

        if ($platformEnum === null || !$this->registry->isEnabled($platformEnum)) {
            return redirect()->route('accounts')
                ->with('error', 'This platform is not available.');
        }

        $limitService = app(SocialAccountLimitService::class);
        if (!$limitService->canAddAnotherAccount(Auth::user())) {
            return redirect()->route('accounts')
                ->with('error', $limitService->rejectionMessageForNewConnection(Auth::user()));
        }

        if ($platformEnum === Platform::Telegram) {
            return redirect()->route('accounts')
                ->with('info', 'Telegram uses bot tokens. Please add your bot token from the accounts page.');
        }

        if ($platformEnum === Platform::WordPress) {
            return redirect()->route('accounts')
                ->with('info', 'WordPress uses Application Passwords. Please enter your credentials from the accounts page.');
        }

        if ($platformEnum === Platform::Discord) {
            return redirect()->route('accounts')
                ->with('info', 'Discord uses webhook URLs. Please add your webhook from the accounts page.');
        }

        if ($platformEnum === Platform::Bluesky) {
            return redirect()->route('accounts')
                ->with('info', 'Bluesky uses your handle and an App Password. Add them from the accounts page.');
        }

        if ($platformEnum === Platform::WhatsappChannels) {
            return redirect()->route('accounts')
                ->with('info', 'WhatsApp Channels uses your Cloud API access token and IDs. Add them from the accounts page.');
        }

        $scopes = config("platforms.{$platform}.scopes", []);
        $scopes = is_array($scopes) ? $scopes : [];

        if ($this->isSocialiteSupported($platformEnum)) {
            if (!$this->socialiteServicesConfigured($platformEnum)) {
                return redirect()->route('accounts')
                    ->with('error', $platformEnum->label() . ' connection is not configured. Add the OAuth client ID and secret (and callback URL) in your environment or admin settings.');
            }

            return $this->socialiteForAccountLinking($platformEnum)
                ->scopes($scopes)
                ->redirect();
        }

        return $this->customOAuthRedirect($platformEnum, $scopes);
    }

    /**
     * Handle the OAuth callback from a platform.
     */
    public function callback(Request $request, string $platform): RedirectResponse
    {
        $platformEnum = $this->resolvePlatform($platform);

        if ($platformEnum === null) {
            return redirect()->route('accounts')
                ->with('error', 'Unknown platform.');
        }

        if (!$this->isSocialiteSupported($platformEnum)) {
            $storedState = session()->pull('oauth_state');
            $returnedState = $request->input('state');

            if (empty($storedState) || $storedState !== $returnedState) {
                return redirect()->route('accounts')
                    ->with('error', 'Invalid OAuth state. Please try connecting again.');
            }

            if ($request->has('error')) {
                $errorDesc = $request->input('error_description', $request->input('error'));
                return redirect()->route('accounts')
                    ->with('error', 'Authorization denied: ' . $errorDesc);
            }

            if (!$request->has('code')) {
                return redirect()->route('accounts')
                    ->with('error', 'No authorization code received from ' . $platformEnum->label() . '.');
            }
        }

        try {
            if ($this->isSocialiteSupported($platformEnum)) {
                if (!$this->socialiteServicesConfigured($platformEnum)) {
                    return redirect()->route('accounts')
                        ->with('error', $platformEnum->label() . ' connection is not configured. Add the OAuth client ID and secret in your environment.');
                }

                $socialUser = $this->socialiteForAccountLinking($platformEnum)->user();
                $platformUserId = $this->extractSocialUserId($socialUser, $platformEnum);
                $accessToken = trim((string) ($socialUser->token ?? ''));

                if ($platformUserId === '') {
                    throw new \InvalidArgumentException('Could not read your ' . $platformEnum->label() . ' account ID from OAuth response. Please reconnect and approve all requested permissions.');
                }

                if ($accessToken === '') {
                    throw new \InvalidArgumentException('Could not read access token from ' . $platformEnum->label() . '. Please reconnect.');
                }

                $rawUser = $this->socialUserRawData($socialUser);
                $username = $socialUser->getNickname()
                    ?: ($rawUser['preferred_username'] ?? $rawUser['username'] ?? $rawUser['screen_name'] ?? null);
                $displayName = $socialUser->getName()
                    ?: ($rawUser['name'] ?? $rawUser['localizedFirstName'] ?? $username);

                $storedScopes = config("platforms.{$platform}.scopes", []);
                $storedScopes = is_array($storedScopes) ? $storedScopes : null;

                $this->linkingService->linkAccount(
                    user:           Auth::user(),
                    platform:       $platformEnum,
                    platformUserId: $platformUserId,
                    accessToken:    $accessToken,
                    refreshToken:   $socialUser->refreshToken ?? ($rawUser['refresh_token'] ?? null),
                    username:       $username,
                    displayName:    $displayName,
                    avatarUrl:      $socialUser->getAvatar(),
                    scopes:         $storedScopes,
                    expiresAt:      $socialUser->expiresIn
                        ? now()->addSeconds($socialUser->expiresIn)
                        : null,
                );
            } else {
                $this->handleCustomOAuthCallback($request, $platformEnum);
            }

            Log::info('Social account OAuth callback succeeded', [
                'user_id'  => Auth::id(),
                'platform' => $platformEnum->value,
            ]);

            return redirect()->route('accounts')
                ->with('success', $platformEnum->label() . ' account connected successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('accounts')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Social account OAuth callback failed', [
                'user_id'  => Auth::id(),
                'platform' => $platformEnum->value,
                'error'    => $e->getMessage(),
            ]);
            report($e);

            return redirect()->route('accounts')
                ->with('error', 'Failed to connect ' . $platformEnum->label() . '. Please try again.');
        }
    }

    /**
     * Store Telegram bot credentials directly (no OAuth).
     */
    public function storeTelegram(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bot_token'    => ['required', 'string', 'regex:/^\d+:[A-Za-z0-9_-]{35,}$/'],
            'chat_id'      => ['required', 'string', 'regex:/^-?\d+$|^@\w+$/'],
            'channel_name' => 'nullable|string|max:255',
        ], [
            'bot_token.regex' => 'The bot token format is invalid. It should look like 123456:ABC-DEF...',
            'chat_id.regex'   => 'The chat ID must be a numeric ID or @channel_username.',
        ]);

        $existingActive = \App\Models\SocialAccount::where('user_id', Auth::id())
            ->where('platform', 'telegram')
            ->where('platform_user_id', $validated['chat_id'])
            ->where('status', 'active')
            ->exists();

        if ($existingActive) {
            return redirect()->route('accounts')
                ->with('error', 'This Telegram chat is already connected.');
        }

        try {
            $this->linkingService->linkTelegram(
                user:        Auth::user(),
                botToken:    $validated['bot_token'],
                chatId:      $validated['chat_id'],
                channelName: $validated['channel_name'] ?? null,
            );

            Log::info('Telegram bot connected', [
                'user_id' => Auth::id(),
                'chat_id' => $validated['chat_id'],
            ]);

            return redirect()->route('accounts')
                ->with('success', 'Telegram bot connected successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('accounts')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Telegram bot connection failed', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);
            report($e);
            return redirect()->route('accounts')
                ->with('error', 'Failed to connect Telegram bot. Please verify your bot token and chat ID.');
        }
    }

    /**
     * Store WordPress site credentials directly (no OAuth).
     */
    public function storeWordPress(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_url'     => ['required', 'url', 'max:500'],
            'wp_username'  => ['required', 'string', 'max:255'],
            'app_password' => ['required', 'string', 'min:8'],
        ], [
            'site_url.url' => 'Please enter a valid URL (e.g. https://myblog.com).',
        ]);

        $siteUrl     = rtrim($validated['site_url'], '/');
        $wpUsername  = $validated['wp_username'];
        $appPassword = $validated['app_password'];

        $adapter = new WordPressAdapter();
        $check   = $adapter->validateCredentials($siteUrl, $wpUsername, $appPassword);

        if (!$check['valid']) {
            return redirect()->route('accounts')
                ->with('error', $check['error']);
        }

        $existingActive = \App\Models\SocialAccount::where('user_id', Auth::id())
            ->where('platform', 'wordpress')
            ->where('platform_user_id', $check['id'])
            ->where('status', 'active')
            ->exists();

        if ($existingActive) {
            return redirect()->route('accounts')
                ->with('error', 'This WordPress site is already connected.');
        }

        try {
            $this->linkingService->linkWordPress(
                user:           Auth::user(),
                siteUrl:        $siteUrl,
                wpUsername:     $wpUsername,
                appPassword:    $appPassword,
                platformUserId: $check['id'],
                displayName:    $check['name'] ?? $wpUsername,
                avatarUrl:      $check['avatar'] ?? null,
            );

            Log::info('WordPress site connected', [
                'user_id'  => Auth::id(),
                'site_url' => $siteUrl,
            ]);

            return redirect()->route('accounts')
                ->with('success', 'WordPress site connected successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('accounts')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('WordPress connection failed', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);
            report($e);
            return redirect()->route('accounts')
                ->with('error', 'Failed to connect WordPress site. Please verify your credentials.');
        }
    }

    /**
     * Store Discord webhook credentials directly (no OAuth).
     */
    public function storeBluesky(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier'   => ['required', 'string', 'max:255'],
            'app_password' => ['required', 'string', 'min:8', 'max:500'],
        ], [
            'identifier.required' => 'Enter your Bluesky handle (e.g. you.bsky.social) or account email.',
        ]);

        $adapter = new BlueskyAdapter();
        $check = $adapter->validateCredentials($validated['identifier'], $validated['app_password']);

        if (!$check['valid']) {
            return redirect()->route('accounts')
                ->with('error', $check['error'] ?? 'Could not sign in to Bluesky.');
        }

        $existingActive = \App\Models\SocialAccount::where('user_id', Auth::id())
            ->where('platform', 'bluesky')
            ->where('platform_user_id', $check['did'])
            ->where('status', 'active')
            ->exists();

        if ($existingActive) {
            return redirect()->route('accounts')
                ->with('error', 'This Bluesky account is already connected.');
        }

        try {
            $this->linkingService->linkBluesky(
                user:        Auth::user(),
                identifier:  $validated['identifier'],
                did:         $check['did'],
                handle:      $check['handle'],
                accessJwt:   $check['accessJwt'],
                refreshJwt:  $check['refreshJwt'],
                avatarUrl:   $check['avatar'] ?? null,
                expiresAt:   $check['expiresAt'] ?? null,
            );

            Log::info('Bluesky account connected', [
                'user_id' => Auth::id(),
                'did'     => $check['did'],
            ]);

            return redirect()->route('accounts')
                ->with('success', 'Bluesky connected successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('accounts')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Bluesky connection failed', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);
            report($e);

            return redirect()->route('accounts')
                ->with('error', 'Failed to connect Bluesky. Please verify your handle and App Password.');
        }
    }

    public function storeWhatsappChannels(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'access_token'       => ['required', 'string', 'min:16'],
            'phone_number_id'    => ['required', 'string', 'regex:/^\d+$/'],
            'channel_recipient'  => ['required', 'string', 'max:255'],
            'recipient_type'     => ['nullable', 'in:individual,group'],
            'channel_name'       => ['nullable', 'string', 'max:255'],
        ], [
            'phone_number_id.regex' => 'Phone number ID must contain digits only (from Meta API Setup).',
        ]);

        $recipientType = $validated['recipient_type'] ?? 'individual';
        $channelRecipient = trim($validated['channel_recipient']);
        $accessToken = trim($validated['access_token']);
        $phoneNumberId = trim($validated['phone_number_id']);

        $adapter = new WhatsAppChannelsAdapter();
        $check   = $adapter->validateCredentials($accessToken, $phoneNumberId);

        if (!$check['valid']) {
            return redirect()->route('accounts')
                ->with('error', $check['error'] ?? 'Could not verify WhatsApp credentials.');
        }

        $existingActive = \App\Models\SocialAccount::where('user_id', Auth::id())
            ->where('platform', 'whatsapp_channels')
            ->where('platform_user_id', $channelRecipient)
            ->where('status', 'active')
            ->exists();

        if ($existingActive) {
            return redirect()->route('accounts')
                ->with('error', 'This WhatsApp recipient is already connected.');
        }

        try {
            $this->linkingService->linkWhatsappChannels(
                user:           Auth::user(),
                accessToken:    $accessToken,
                phoneNumberId:  $phoneNumberId,
                channelRecipient: $channelRecipient,
                recipientType:  $recipientType,
                displayName:    $validated['channel_name'] ?? null,
            );

            Log::info('WhatsApp Channels connected', [
                'user_id' => Auth::id(),
            ]);

            return redirect()->route('accounts')
                ->with('success', 'WhatsApp Channels connected successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('accounts')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('WhatsApp Channels connection failed', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);
            report($e);

            return redirect()->route('accounts')
                ->with('error', 'Failed to connect WhatsApp. Please verify your token, phone number ID, and recipient.');
        }
    }

    public function storeDiscord(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'webhook_url'  => ['required', 'url', 'regex:#^https://discord(app)?\.com/api/webhooks/\d+/.+$#'],
            'channel_name' => 'nullable|string|max:255',
        ], [
            'webhook_url.regex' => 'Please enter a valid Discord webhook URL (https://discord.com/api/webhooks/…).',
        ]);

        $webhookUrl  = $validated['webhook_url'];
        $channelName = $validated['channel_name'] ?? null;

        $adapter = new DiscordAdapter();
        $check   = $adapter->validateWebhook($webhookUrl);

        if (!$check['valid']) {
            return redirect()->route('accounts')
                ->with('error', $check['error']);
        }

        $existingActive = \App\Models\SocialAccount::where('user_id', Auth::id())
            ->where('platform', 'discord')
            ->where('platform_user_id', $check['webhook_id'])
            ->where('status', 'active')
            ->exists();

        if ($existingActive) {
            return redirect()->route('accounts')
                ->with('error', 'This Discord webhook is already connected.');
        }

        try {
            $this->linkingService->linkDiscord(
                user:        Auth::user(),
                webhookUrl:  $webhookUrl,
                webhookId:   $check['webhook_id'],
                channelName: $channelName ?? $check['name'],
                avatarUrl:   $check['avatar'] ?? null,
            );

            Log::info('Discord webhook connected', [
                'user_id'    => Auth::id(),
                'webhook_id' => $check['webhook_id'],
            ]);

            return redirect()->route('accounts')
                ->with('success', 'Discord channel connected successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('accounts')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Discord connection failed', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);
            report($e);
            return redirect()->route('accounts')
                ->with('error', 'Failed to connect Discord webhook. Please verify the URL.');
        }
    }

    public function disconnect(Request $request, int $accountId): RedirectResponse
    {
        try {
            $disconnected = $this->linkingService->disconnect(Auth::user(), $accountId);

            if (!$disconnected) {
                return redirect()->route('accounts')
                    ->with('error', 'Account not found or already disconnected.');
            }

            return redirect()->route('accounts')
                ->with('success', 'Account disconnected.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('accounts')
                ->with('error', $e->getMessage());
        }
    }

    private function resolvePlatform(string $slug): ?Platform
    {
        return Platform::tryFrom($slug);
    }

    private function isSocialiteSupported(Platform $platform): bool
    {
        return in_array($platform, [
            Platform::Twitter,
            Platform::Facebook,
            Platform::LinkedIn,
            Platform::YouTube,
        ]);
    }

    private function socialiteDriver(Platform $platform): string
    {
        return match ($platform) {
            Platform::YouTube  => 'google',
            Platform::LinkedIn => 'linkedin-openid',
            default              => $platform->value,
        };
    }

    /**
     * True when the Socialite services.* entry exists and has non-empty client credentials.
     */
    private function socialiteServicesConfigured(Platform $platform): bool
    {
        $key = 'services.' . $this->socialiteDriver($platform);
        $c   = config($key);

        if (!is_array($c)) {
            return false;
        }

        $id     = $c['client_id'] ?? null;
        $secret = $c['client_secret'] ?? null;

        return is_string($id) && trim($id) !== ''
            && is_string($secret) && trim($secret) !== '';
    }

    /**
     * Socialite driver with redirect URI for account linking (may differ from social login).
     * LinkedIn uses the same OpenID app as login but must redirect to /accounts/linkedin/callback.
     */
    private function socialiteForAccountLinking(Platform $platform): \Laravel\Socialite\Contracts\Provider
    {
        $driver = Socialite::driver($this->socialiteDriver($platform));
        $slug   = $platform->value;

        $configured = config("platforms.{$slug}.redirect_uri");
        $full       = null;
        if (is_string($configured) && trim($configured) !== '') {
            $u = trim($configured);
            $full = str_starts_with($u, 'http://') || str_starts_with($u, 'https://')
                ? $u
                : url($u);
        }

        if ($full === null && $platform === Platform::LinkedIn) {
            $full = route('accounts.callback', ['platform' => $slug], true);
        }

        if ($full !== null) {
            $driver->redirectUrl($full);
        }

        return $driver;
    }

    private function customOAuthRedirect(Platform $platform, array $scopes): RedirectResponse
    {
        $config    = config("platforms.{$platform->value}");
        $state     = bin2hex(random_bytes(16));
        session(['oauth_state' => $state]);

        $params = [
            'client_id'     => $config['client_id'],
            'redirect_uri'  => $config['redirect_uri'],
            'response_type' => 'code',
            'scope'         => implode(',', $scopes),
            'state'         => $state,
        ];

        $authUrl = match ($platform) {
            Platform::Instagram => 'https://www.facebook.com/dialog/oauth?' . http_build_query($params),
            Platform::Threads   => 'https://threads.net/oauth/authorize?' . http_build_query($params),
            Platform::TikTok    => 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query(array_merge($params, ['client_key' => $config['client_id']])),
            Platform::Pinterest => 'https://www.pinterest.com/oauth/?' . http_build_query($params),
            Platform::Reddit    => 'https://www.reddit.com/api/v1/authorize?' . http_build_query(array_merge($params, ['duration' => 'permanent'])),
            Platform::GoogleBusiness => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(array_merge($params, [
                'scope'       => implode(' ', $scopes),
                'access_type' => 'offline',
                'prompt'      => 'consent',
            ])),
            default             => throw new \InvalidArgumentException("No OAuth URL defined for {$platform->value}"),
        };

        return redirect()->away($authUrl);
    }

    private function handleCustomOAuthCallback(Request $request, Platform $platform): void
    {
        $code = $request->input('code');

        if (empty($code)) {
            throw new \RuntimeException('No authorization code received from ' . $platform->label() . '.');
        }

        $config = config("platforms.{$platform->value}");

        if (empty($config['client_id']) || empty($config['client_secret'])) {
            throw new \RuntimeException($platform->label() . ' is not configured. Missing client credentials.');
        }

        $tokenPayload = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $config['redirect_uri'],
            'client_id'     => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ];

        $tokenUrl = match ($platform) {
            Platform::Instagram      => 'https://graph.facebook.com/v21.0/oauth/access_token',
            Platform::Threads        => 'https://graph.threads.net/oauth/access_token',
            Platform::TikTok         => 'https://open.tiktokapis.com/v2/oauth/token/',
            Platform::Pinterest      => 'https://api.pinterest.com/v5/oauth/token',
            Platform::Reddit         => 'https://www.reddit.com/api/v1/access_token',
            Platform::GoogleBusiness => 'https://oauth2.googleapis.com/token',
            default                  => throw new \InvalidArgumentException("No token URL for {$platform->value}"),
        };

        $response = \Illuminate\Support\Facades\Http::asForm()->post($tokenUrl, $tokenPayload);

        if (!$response->successful()) {
            throw new \RuntimeException("OAuth token exchange failed for {$platform->value}: " . $response->body());
        }

        $data = $response->json();
        $userInfo = $this->fetchCustomUserInfo($platform, $data['access_token']);

        $this->linkingService->linkAccount(
            user:           Auth::user(),
            platform:       $platform,
            platformUserId: $userInfo['id'],
            accessToken:    $data['access_token'],
            refreshToken:   $data['refresh_token'] ?? null,
            username:       $userInfo['username'] ?? null,
            displayName:    $userInfo['name'] ?? null,
            avatarUrl:      $userInfo['avatar'] ?? null,
            scopes:         $config['scopes'],
            expiresAt:      isset($data['expires_in'])
                ? now()->addSeconds($data['expires_in'])
                : null,
            metadata:       $userInfo['metadata'] ?? [],
        );
    }

    private function fetchCustomUserInfo(Platform $platform, string $accessToken): array
    {
        if ($platform === Platform::GoogleBusiness) {
            return $this->fetchGoogleBusinessUserInfo($accessToken);
        }

        $response = match ($platform) {
            Platform::Instagram => \Illuminate\Support\Facades\Http::get("https://graph.instagram.com/me", [
                'fields'       => 'id,username',
                'access_token' => $accessToken,
            ]),
            Platform::Threads => \Illuminate\Support\Facades\Http::get("https://graph.threads.net/v1.0/me", [
                'fields'       => 'id,username,name,threads_profile_picture_url',
                'access_token' => $accessToken,
            ]),
            Platform::TikTok => \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->get('https://open.tiktokapis.com/v2/user/info/', [
                    'fields' => 'open_id,display_name,avatar_url,username',
                ]),
            Platform::Pinterest => \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->get('https://api.pinterest.com/v5/user_account'),
            Platform::Reddit => \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->withHeaders(['User-Agent' => config('app.name') . '/1.0'])
                ->get('https://oauth.reddit.com/api/v1/me'),
            default => throw new \InvalidArgumentException("No user info endpoint for {$platform->value}"),
        };

        $data = $response->json();

        return match ($platform) {
            Platform::Instagram => [
                'id'       => $data['id'],
                'username' => $data['username'] ?? null,
                'name'     => $data['username'] ?? null,
                'avatar'   => null,
            ],
            Platform::Threads => [
                'id'       => $data['id'],
                'username' => $data['username'] ?? null,
                'name'     => $data['name'] ?? $data['username'] ?? null,
                'avatar'   => $data['threads_profile_picture_url'] ?? null,
            ],
            Platform::TikTok => [
                'id'       => $data['data']['user']['open_id'] ?? $data['data']['user']['union_id'] ?? '',
                'username' => $data['data']['user']['username'] ?? null,
                'name'     => $data['data']['user']['display_name'] ?? null,
                'avatar'   => $data['data']['user']['avatar_url'] ?? null,
            ],
            Platform::Pinterest => [
                'id'       => $data['username'] ?? '',
                'username' => $data['username'] ?? null,
                'name'     => $data['business_name'] ?? $data['username'] ?? null,
                'avatar'   => $data['profile_image'] ?? null,
            ],
            Platform::Reddit => [
                'id'       => $data['id'] ?? $data['name'] ?? '',
                'username' => $data['name'] ?? null,
                'name'     => $data['name'] ?? null,
                'avatar'   => $data['icon_img'] ?? null,
            ],
            default => ['id' => '', 'username' => null, 'name' => null, 'avatar' => null],
        };
    }

    /**
     * Google Business needs two API calls: Google user info + Business account/location discovery.
     */
    private function fetchGoogleBusinessUserInfo(string $accessToken): array
    {
        $profileResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        $profile = $profileResponse->successful() ? $profileResponse->json() : [];

        $adapter  = new GoogleBusinessAdapter();
        $location = $adapter->fetchAccountAndLocation($accessToken);

        if (!$location['valid']) {
            throw new \RuntimeException($location['error']);
        }

        return [
            'id'       => $profile['id'] ?? $location['account_name'],
            'username' => $profile['email'] ?? null,
            'name'     => $location['location_title'],
            'avatar'   => $profile['picture'] ?? null,
            'metadata' => [
                'account_name'  => $location['account_name'],
                'location_name' => $location['location_name'],
            ],
        ];
    }

    /**
     * Socialite payload shape differs by provider (especially LinkedIn OpenID).
     */
    private function extractSocialUserId(object $socialUser, Platform $platform): string
    {
        $id = trim((string) ($socialUser->getId() ?? ''));
        if ($id !== '') {
            return $id;
        }

        $raw = $this->socialUserRawData($socialUser);

        return match ($platform) {
            Platform::LinkedIn => trim((string) ($raw['sub'] ?? $raw['id'] ?? '')),
            Platform::Twitter => trim((string) ($raw['id'] ?? $raw['data']['id'] ?? '')),
            default => trim((string) ($raw['id'] ?? '')),
        };
    }

    /**
     * Extract raw Socialite provider user payload.
     *
     * @return array<string, mixed>
     */
    private function socialUserRawData(object $socialUser): array
    {
        if (method_exists($socialUser, 'getRaw')) {
            $raw = $socialUser->getRaw();
            return is_array($raw) ? $raw : [];
        }

        if (property_exists($socialUser, 'user')) {
            $vars = get_object_vars($socialUser);
            $raw = $vars['user'] ?? null;
            return is_array($raw) ? $raw : [];
        }

        return [];
    }
}
