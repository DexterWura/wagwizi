<?php

namespace App\Controllers;

use App\Services\Platform\Adapters\BlueskyAdapter;
use App\Services\Platform\Adapters\DevToAdapter;
use App\Services\Platform\Adapters\DiscordAdapter;
use App\Services\Platform\Adapters\GoogleBusinessAdapter;
use App\Services\Platform\Adapters\WhatsAppChannelsAdapter;
use App\Services\Platform\Adapters\WordPressAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PlatformRegistry;
use App\Models\User;
use App\Services\SocialAccount\AccountLinkingService;
use App\Services\SocialAccount\SocialAccountLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\View\View;

class SocialAccountController extends Controller
{
    private const DESTINATION_SELECTION_SESSION_KEY = 'oauth_destination_selection';

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

        if ($platformEnum === Platform::DevTo) {
            return redirect()->route('accounts')
                ->with('info', 'Dev.to uses personal API keys. Add your key from the accounts page.');
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

            $driver = $this->socialiteForAccountLinking($platformEnum);
            $metaBusinessConfigId = $this->metaBusinessConfigIdForPlatform($platformEnum);

            if ($metaBusinessConfigId !== null) {
                // Meta Login for Business uses config_id-driven permissions instead of scope.
                $driver->scopes([]);
            } else {
                $driver->scopes($scopes);
            }

            $extra  = $this->oauthQueryParamsWhenLinkingAnotherAccount($platformEnum, Auth::user());
            if ($metaBusinessConfigId !== null) {
                $extra['config_id'] = $metaBusinessConfigId;
            }
            if ($extra !== []) {
                $driver->with($extra);
            }

            return $driver->redirect();
        }

        return $this->customOAuthRedirect($platformEnum, $scopes, Auth::user());
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

                if ($platformEnum === Platform::LinkedIn) {
                    return $this->handleLinkedInOAuthCallback($request);
                } else {
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

                    if ($platformEnum === Platform::Facebook) {
                        return $this->beginFacebookDestinationSelection(
                            accessToken: $accessToken,
                            refreshToken: $socialUser->refreshToken ?? ($rawUser['refresh_token'] ?? null),
                            expiresIn: isset($socialUser->expiresIn) ? (int) $socialUser->expiresIn : null,
                            oauthUserId: $platformUserId,
                            oauthUsername: $username,
                            oauthDisplayName: $displayName,
                            oauthAvatarUrl: $socialUser->getAvatar(),
                            scopes: $storedScopes,
                        );
                    }

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
                }
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

    public function storeDevTo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'api_key'      => ['required', 'string', 'min:20', 'max:500'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        $apiKey = trim((string) $validated['api_key']);
        $displayName = trim((string) ($validated['display_name'] ?? ''));

        $adapter = new DevToAdapter();
        $check = $adapter->validateCredentials($apiKey);

        if (!($check['valid'] ?? false)) {
            return redirect()->route('accounts')
                ->with('error', $check['error'] ?? 'Could not verify Dev.to API key.');
        }

        $platformUserId = trim((string) ($check['id'] ?? ''));
        if ($platformUserId === '') {
            return redirect()->route('accounts')
                ->with('error', 'Could not determine your Dev.to account ID.');
        }

        $existingActive = \App\Models\SocialAccount::where('user_id', Auth::id())
            ->where('platform', 'devto')
            ->where('platform_user_id', $platformUserId)
            ->where('status', 'active')
            ->exists();

        if ($existingActive) {
            return redirect()->route('accounts')
                ->with('error', 'This Dev.to account is already connected.');
        }

        try {
            $this->linkingService->linkDevTo(
                user: Auth::user(),
                apiKey: $apiKey,
                platformUserId: $platformUserId,
                username: isset($check['username']) ? (string) $check['username'] : null,
                displayName: $displayName !== '' ? $displayName : (isset($check['name']) ? (string) $check['name'] : null),
                avatarUrl: isset($check['avatar']) ? (string) $check['avatar'] : null,
            );

            Log::info('Dev.to account connected', [
                'user_id' => Auth::id(),
                'platform_user_id' => $platformUserId,
            ]);

            return redirect()->route('accounts')
                ->with('success', 'Dev.to account connected successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('accounts')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Dev.to connection failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
            report($e);

            return redirect()->route('accounts')
                ->with('error', 'Failed to connect Dev.to account. Please verify your API key.');
        }
    }

    public function exchangeWhatsappEmbeddedSignupCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:8'],
            'phone_number_id' => ['required', 'string', 'regex:/^\d+$/'],
            'waba_id' => ['nullable', 'string', 'regex:/^\d+$/'],
        ]);

        $platformConfig = config('platforms.whatsapp_channels', []);
        $appId = trim((string) ($platformConfig['embedded_signup_app_id'] ?? ''));
        $appSecret = trim((string) ($platformConfig['embedded_signup_app_secret'] ?? ''));
        $graphVersion = trim((string) ($platformConfig['graph_api_version'] ?? 'v21.0'));
        $graphVersion = $graphVersion !== '' ? ltrim($graphVersion, '/') : 'v21.0';

        if ($appId === '' || $appSecret === '') {
            return response()->json([
                'message' => 'WhatsApp Embedded Signup is not configured on this server.',
            ], 422);
        }

        $tokenResponse = Http::acceptJson()->get("https://graph.facebook.com/{$graphVersion}/oauth/access_token", [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $validated['code'],
        ]);

        if (!$tokenResponse->successful()) {
            Log::warning('WhatsApp embedded signup code exchange failed', [
                'user_id' => Auth::id(),
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body(),
            ]);

            return response()->json([
                'message' => 'Could not exchange signup code with Meta. Please retry the flow.',
            ], 422);
        }

        $accessToken = trim((string) $tokenResponse->json('access_token'));
        if ($accessToken === '') {
            return response()->json([
                'message' => 'Meta did not return an access token. Please retry.',
            ], 422);
        }

        $phoneNumberId = trim((string) $validated['phone_number_id']);
        $phoneResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$graphVersion}/{$phoneNumberId}", [
                'fields' => 'id,display_phone_number,verified_name,whatsapp_business_account{id,name}',
            ]);

        if (!$phoneResponse->successful()) {
            Log::warning('WhatsApp embedded signup phone number lookup failed', [
                'user_id' => Auth::id(),
                'phone_number_id' => $phoneNumberId,
                'status' => $phoneResponse->status(),
                'body' => $phoneResponse->body(),
            ]);

            return response()->json([
                'message' => 'Could not verify the selected WhatsApp phone number with the issued token.',
            ], 422);
        }

        $phone = $phoneResponse->json();
        $verifiedWabaId = trim((string) ($phone['whatsapp_business_account']['id'] ?? ''));
        $requestedWabaId = trim((string) ($validated['waba_id'] ?? ''));

        if ($requestedWabaId !== '' && $verifiedWabaId !== '' && $requestedWabaId !== $verifiedWabaId) {
            return response()->json([
                'message' => 'The returned phone number does not match the selected WhatsApp Business Account.',
            ], 422);
        }

        return response()->json([
            'access_token' => $accessToken,
            'phone_number_id' => (string) ($phone['id'] ?? $phoneNumberId),
            'display_phone_number' => $phone['display_phone_number'] ?? null,
            'verified_name' => $phone['verified_name'] ?? ($phone['whatsapp_business_account']['name'] ?? null),
            'waba_id' => $verifiedWabaId !== '' ? $verifiedWabaId : ($requestedWabaId !== '' ? $requestedWabaId : null),
        ]);
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
            $result = $this->linkingService->disconnect(
                Auth::user(),
                $accountId,
                $request->boolean('force_disconnect')
            );

            if (!($result['disconnected'] ?? false)) {
                return redirect()->route('accounts')
                    ->with('error', 'Account not found or already disconnected.');
            }

            $cancelled = (int) ($result['cancelled_pending'] ?? 0);
            if ($cancelled > 0) {
                return redirect()->route('accounts')
                    ->with('success', "Account disconnected. {$cancelled} pending/publishing post(s) were cancelled.");
            }

            return redirect()->route('accounts')
                ->with('success', 'Account disconnected.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('accounts')
                ->with('error', $e->getMessage());
        }
    }

    public function destinations(Request $request, string $platform): View|RedirectResponse
    {
        $platformEnum = $this->resolvePlatform($platform);
        if (!in_array($platformEnum, [Platform::Facebook, Platform::LinkedIn], true)) {
            return redirect()->route('accounts')->with('error', 'This platform does not require destination selection.');
        }

        $pending = $this->pullPendingDestinationSelection($platformEnum, false);
        if ($pending === null) {
            return redirect()->route('accounts')->with('error', 'No pending destination selection found. Please reconnect your account.');
        }

        return view('accounts-destinations', [
            'platform' => $platformEnum,
            'destinations' => $pending['destinations'],
        ]);
    }

    public function storeDestinations(Request $request, string $platform): RedirectResponse
    {
        $platformEnum = $this->resolvePlatform($platform);
        if (!in_array($platformEnum, [Platform::Facebook, Platform::LinkedIn], true)) {
            return redirect()->route('accounts')->with('error', 'This platform does not require destination selection.');
        }

        $pending = $this->pullPendingDestinationSelection($platformEnum, false);
        if ($pending === null) {
            return redirect()->route('accounts')->with('error', 'Your destination selection expired. Please reconnect your account.');
        }

        $validated = $request->validate([
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*' => ['string'],
        ]);

        $available = [];
        foreach ($pending['destinations'] as $destination) {
            if (is_array($destination) && isset($destination['key']) && is_string($destination['key'])) {
                $available[$destination['key']] = $destination;
            }
        }

        $selected = [];
        foreach (array_unique($validated['destinations']) as $key) {
            if (isset($available[$key])) {
                $selected[] = $available[$key];
            }
        }

        if ($selected === []) {
            return redirect()
                ->route('accounts.destinations', ['platform' => $platformEnum->value])
                ->with('error', 'Select at least one destination to connect.');
        }

        $linked = 0;
        $destinationTokens = is_array($pending['destination_tokens'] ?? null) ? $pending['destination_tokens'] : [];
        foreach ($selected as $destination) {
            $destinationKey = trim((string) ($destination['key'] ?? ''));
            $mappedToken = $destinationKey !== '' ? ($destinationTokens[$destinationKey] ?? null) : null;
            $accessToken = trim((string) ($mappedToken ?? $destination['access_token'] ?? ''));
            $platformUserId = trim((string) ($destination['platform_user_id'] ?? ''));

            if ($accessToken === '' || $platformUserId === '') {
                continue;
            }

            $this->linkingService->linkAccount(
                user: Auth::user(),
                platform: $platformEnum,
                platformUserId: $platformUserId,
                accessToken: $accessToken,
                refreshToken: $pending['refresh_token'] ?? null,
                username: $destination['username'] ?? null,
                displayName: $destination['display_name'] ?? null,
                avatarUrl: $destination['avatar_url'] ?? null,
                scopes: is_array($pending['scopes'] ?? null) ? $pending['scopes'] : null,
                expiresAt: isset($pending['expires_at']) && is_string($pending['expires_at'])
                    ? \Carbon\Carbon::parse($pending['expires_at'])
                    : null,
                metadata: is_array($destination['metadata'] ?? null) ? $destination['metadata'] : [],
            );
            $linked++;
        }

        $this->pullPendingDestinationSelection($platformEnum, true);

        if ($linked < 1) {
            return redirect()->route('accounts')->with('error', 'No valid destinations were selected. Please reconnect and try again.');
        }

        return redirect()->route('accounts')->with('success', $platformEnum->label() . " connected ({$linked} destination(s)).");
    }

    private function resolvePlatform(string $slug): ?Platform
    {
        return Platform::tryFrom($slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pullPendingDestinationSelection(Platform $platform, bool $forget): ?array
    {
        $all = session(self::DESTINATION_SELECTION_SESSION_KEY, []);
        if (!is_array($all)) {
            return null;
        }

        $pending = $all[$platform->value] ?? null;
        if (!is_array($pending)) {
            return null;
        }

        if ($forget) {
            unset($all[$platform->value]);
            session([self::DESTINATION_SELECTION_SESSION_KEY => $all]);
        }

        return $pending;
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

    private function metaBusinessConfigIdForPlatform(Platform $platform): ?string
    {
        // Threads uses its own OAuth scopes and does not use Meta Login for Business config_id.
        if (!in_array($platform, [Platform::Facebook, Platform::Instagram], true)) {
            return null;
        }

        if ($platform === Platform::Instagram) {
            $instagram = trim((string) env('INSTAGRAM_BUSINESS_CONFIG_ID', ''));
            if ($instagram !== '') {
                return $instagram;
            }
        }

        $facebook = trim((string) env('FACEBOOK_BUSINESS_CONFIG_ID', ''));
        return $facebook !== '' ? $facebook : null;
    }

    /**
     * Extra authorize URL query params when the user already has this platform linked and is adding another.
     * Without this, many providers reuse the browser session and authorize the same profile again.
     *
     * LinkedIn, TikTok, Pinterest, Threads, and Reddit do not document a reliable equivalent; users may need
     * a private window or to sign out of the provider first.
     *
     * @return array<string, string>
     */
    private function oauthQueryParamsWhenLinkingAnotherAccount(Platform $platform, ?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $alreadyLinked = $user->socialAccounts()
            ->where('platform', $platform->value)
            ->exists();

        if (! $alreadyLinked) {
            return [];
        }

        return match ($platform) {
            Platform::Twitter => ['prompt' => 'login'],
            Platform::Facebook => ['auth_type' => 'reauthenticate'],
            Platform::YouTube => ['prompt' => 'select_account consent'],
            Platform::Instagram => ['auth_type' => 'reauthenticate'],
            default => [],
        };
    }

    /**
     * Socialite driver with redirect URI for account linking (may differ from social login).
     * LinkedIn uses the same OpenID app as login but must redirect to /accounts/linkedin/callback.
     */
    private function socialiteForAccountLinking(Platform $platform): \Laravel\Socialite\Contracts\Provider
    {
        $driver = Socialite::driver($this->socialiteDriver($platform));
        $slug   = $platform->value;

        if ($platform === Platform::Facebook && method_exists($driver, 'usingGraphVersion')) {
            // Socialite defaults Facebook to v3.3, which is obsolete and can cause auth failures.
            $driver->usingGraphVersion((string) env('FACEBOOK_GRAPH_VERSION', 'v21.0'));
        }

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

    private function customOAuthRedirect(Platform $platform, array $scopes, ?User $linkingUser = null): RedirectResponse
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

        $instagramParams = array_merge($params, $this->oauthQueryParamsWhenLinkingAnotherAccount(Platform::Instagram, $linkingUser));

        $googleBusinessPrompt = ($linkingUser !== null && $linkingUser->socialAccounts()
            ->where('platform', Platform::GoogleBusiness->value)
            ->exists())
            ? 'select_account consent'
            : 'consent';

        $authUrl = match ($platform) {
            Platform::Instagram => 'https://www.facebook.com/dialog/oauth?' . http_build_query($instagramParams),
            Platform::Threads   => 'https://threads.net/oauth/authorize?' . http_build_query($params),
            Platform::TikTok    => 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query(array_merge($params, ['client_key' => $config['client_id']])),
            Platform::Pinterest => 'https://www.pinterest.com/oauth/?' . http_build_query($params),
            Platform::Reddit    => 'https://www.reddit.com/api/v1/authorize?' . http_build_query(array_merge($params, ['duration' => 'permanent'])),
            Platform::GoogleBusiness => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(array_merge($params, [
                'scope'       => implode(' ', $scopes),
                'access_type' => 'offline',
                'prompt'      => $googleBusinessPrompt,
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
                'metadata' => [
                    // Default to posting on the user's own profile feed.
                    'subreddit' => isset($data['name']) && is_string($data['name']) && trim($data['name']) !== ''
                        ? ('u_' . trim($data['name']))
                        : null,
                ],
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
     * LinkedIn callback is handled explicitly to avoid provider edge-cases where
     * token exchange may be attempted without the callback code.
     */
    private function handleLinkedInOAuthCallback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            $desc = $request->input('error_description', $request->input('error'));
            throw new \InvalidArgumentException('LinkedIn authorization denied: ' . $desc);
        }

        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            Log::warning('LinkedIn callback missing authorization code', [
                'user_id' => Auth::id(),
                'query'   => array_keys($request->query()),
            ]);
            throw new \InvalidArgumentException(
                'LinkedIn did not return an authorization code. Reconnect and ensure your LinkedIn app callback URL matches this exact URL: '
                . route('accounts.callback', ['platform' => 'linkedin'], true)
            );
        }

        $config = config('platforms.linkedin');
        $clientId = trim((string) ($config['client_id'] ?? ''));
        $clientSecret = trim((string) ($config['client_secret'] ?? ''));
        $redirectUri = trim((string) ($config['redirect_uri'] ?? route('accounts.callback', ['platform' => 'linkedin'], true)));

        if ($clientId === '' || $clientSecret === '') {
            throw new \InvalidArgumentException('LinkedIn OAuth is not configured. Missing client ID or client secret.');
        }

        if ($redirectUri !== '' && !str_starts_with($redirectUri, 'http://') && !str_starts_with($redirectUri, 'https://')) {
            $redirectUri = url($redirectUri);
        }

        $tokenResponse = \Illuminate\Support\Facades\Http::asForm()
            ->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $redirectUri,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if (!$tokenResponse->successful()) {
            throw new \RuntimeException('LinkedIn token exchange failed: ' . $tokenResponse->body());
        }

        $tokenData = $tokenResponse->json();
        $accessToken = trim((string) ($tokenData['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new \RuntimeException('LinkedIn token exchange returned no access token.');
        }

        $storedScopes = config('platforms.linkedin.scopes', []);
        $storedScopes = is_array($storedScopes) ? $storedScopes : null;

        $userInfo = [];
        $userInfoResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://api.linkedin.com/v2/userinfo');

        if ($userInfoResponse->successful() && is_array($userInfoResponse->json())) {
            $userInfo = $userInfoResponse->json();
        }

        $platformUserId = trim((string) ($userInfo['sub'] ?? ''));
        $username = $userInfo['preferred_username'] ?? null;
        $displayName = $userInfo['name'] ?? null;
        $avatarUrl = is_string($userInfo['picture'] ?? null) ? $userInfo['picture'] : null;

        if ($platformUserId === '') {
            $profileResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
                ->acceptJson()
                ->get('https://api.linkedin.com/v2/me');

            if ($profileResponse->successful() && is_array($profileResponse->json())) {
                $profile = $profileResponse->json();
                $platformUserId = trim((string) ($profile['id'] ?? ''));
                $displayName = $displayName
                    ?? trim((string) (($profile['localizedFirstName'] ?? '') . ' ' . ($profile['localizedLastName'] ?? '')));
            }
        }

        if ($platformUserId === '') {
            throw new \InvalidArgumentException('Could not read your LinkedIn account ID from OAuth response. Please reconnect.');
        }

        return $this->beginLinkedInDestinationSelection(
            accessToken: $accessToken,
            refreshToken: $tokenData['refresh_token'] ?? null,
            expiresIn: isset($tokenData['expires_in']) ? (int) $tokenData['expires_in'] : null,
            oauthUserId: $platformUserId,
            oauthUsername: $username,
            oauthDisplayName: $displayName !== null && trim($displayName) !== '' ? $displayName : ($username ?? 'LinkedIn Account'),
            oauthAvatarUrl: $avatarUrl,
            scopes: $storedScopes,
        );
    }

    private function beginFacebookDestinationSelection(
        string $accessToken,
        ?string $refreshToken,
        ?int $expiresIn,
        string $oauthUserId,
        ?string $oauthUsername,
        ?string $oauthDisplayName,
        ?string $oauthAvatarUrl,
        ?array $scopes,
    ): RedirectResponse {
        $destinations = $this->discoverFacebookDestinations($accessToken, $oauthUserId, $oauthUsername, $oauthDisplayName, $oauthAvatarUrl);
        $destinationTokens = $this->facebookDestinationTokenMap($destinations);
        $destinations = $this->stripDestinationAccessTokens($destinations);

        if ($destinations === []) {
            throw new \InvalidArgumentException('No publishable Facebook pages were found for this account.');
        }

        $all = session(self::DESTINATION_SELECTION_SESSION_KEY, []);
        if (!is_array($all)) {
            $all = [];
        }

        $all[Platform::Facebook->value] = [
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresIn !== null ? now()->addSeconds($expiresIn)->toIso8601String() : null,
            'scopes' => $scopes ?? [],
            'destinations' => $destinations,
            'destination_tokens' => $destinationTokens,
        ];
        session([self::DESTINATION_SELECTION_SESSION_KEY => $all]);

        return redirect()->route('accounts.destinations', ['platform' => Platform::Facebook->value]);
    }

    private function beginLinkedInDestinationSelection(
        string $accessToken,
        ?string $refreshToken,
        ?int $expiresIn,
        string $oauthUserId,
        ?string $oauthUsername,
        ?string $oauthDisplayName,
        ?string $oauthAvatarUrl,
        ?array $scopes,
    ): RedirectResponse {
        $destinations = $this->discoverLinkedInDestinations($accessToken, $oauthUserId, $oauthUsername, $oauthDisplayName, $oauthAvatarUrl);

        if ($destinations === []) {
            throw new \InvalidArgumentException('No publishable LinkedIn destinations were found for this account.');
        }

        $all = session(self::DESTINATION_SELECTION_SESSION_KEY, []);
        if (!is_array($all)) {
            $all = [];
        }

        $all[Platform::LinkedIn->value] = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresIn !== null ? now()->addSeconds($expiresIn)->toIso8601String() : null,
            'scopes' => $scopes ?? [],
            'destinations' => $destinations,
        ];
        session([self::DESTINATION_SELECTION_SESSION_KEY => $all]);

        return redirect()->route('accounts.destinations', ['platform' => Platform::LinkedIn->value]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function discoverFacebookDestinations(
        string $accessToken,
        string $oauthUserId,
        ?string $oauthUsername,
        ?string $oauthDisplayName,
        ?string $oauthAvatarUrl,
    ): array {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://graph.facebook.com/v21.0/me/accounts', [
                'fields' => 'id,name,access_token,picture{url}',
                'limit' => 100,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Could not fetch Facebook pages: ' . $response->body());
        }

        $pages = $response->json('data');
        if (!is_array($pages)) {
            return [];
        }

        $destinations = [];
        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }
            $pageId = trim((string) ($page['id'] ?? ''));
            $pageToken = trim((string) ($page['access_token'] ?? ''));
            if ($pageId === '' || $pageToken === '') {
                continue;
            }

            $display = trim((string) ($page['name'] ?? ''));
            $avatar = $page['picture']['data']['url'] ?? null;
            if (!is_string($avatar)) {
                $avatar = null;
            }

            $destinations[] = [
                'key' => 'page:' . $pageId,
                'platform_user_id' => $pageId,
                'username' => $oauthUsername,
                'display_name' => $display !== '' ? $display : ('Facebook Page ' . $pageId),
                'avatar_url' => $avatar ?? $oauthAvatarUrl,
                'metadata' => [
                    'account_type' => 'page',
                    'parent_user_id' => $oauthUserId,
                    'oauth_display_name' => $oauthDisplayName,
                ],
                'access_token' => $pageToken,
            ];
        }

        return $destinations;
    }

    /**
     * @param array<int, array<string, mixed>> $destinations
     * @return array<string, string>
     */
    private function facebookDestinationTokenMap(array $destinations): array
    {
        $out = [];
        foreach ($destinations as $destination) {
            if (!is_array($destination)) {
                continue;
            }
            $key = trim((string) ($destination['key'] ?? ''));
            $token = trim((string) ($destination['access_token'] ?? ''));
            if ($key !== '' && $token !== '') {
                $out[$key] = $token;
            }
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $destinations
     * @return array<int, array<string, mixed>>
     */
    private function stripDestinationAccessTokens(array $destinations): array
    {
        foreach ($destinations as &$destination) {
            if (is_array($destination)) {
                unset($destination['access_token']);
            }
        }
        unset($destination);

        return $destinations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function discoverLinkedInDestinations(
        string $accessToken,
        string $oauthUserId,
        ?string $oauthUsername,
        ?string $oauthDisplayName,
        ?string $oauthAvatarUrl,
    ): array {
        $safeDisplayName = trim((string) $oauthDisplayName) !== '' ? $oauthDisplayName : ($oauthUsername ?? 'LinkedIn Profile');

        $destinations = [[
            'key' => 'person:' . $oauthUserId,
            'platform_user_id' => $oauthUserId,
            'username' => $oauthUsername,
            'display_name' => $safeDisplayName,
            'avatar_url' => $oauthAvatarUrl,
            'access_token' => $accessToken,
            'metadata' => [
                'account_type' => 'person',
                'author_urn' => 'urn:li:person:' . $oauthUserId,
                'owner_urn' => 'urn:li:person:' . $oauthUserId,
                'parent_user_id' => $oauthUserId,
            ],
        ]];

        $orgAclsResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->withHeaders($this->linkedInDiscoveryHeaders())
            ->get('https://api.linkedin.com/v2/organizationalEntityAcls', [
                'q' => 'roleAssignee',
                'role' => 'ADMINISTRATOR',
                'state' => 'APPROVED',
                'projection' => '(elements*(organizationalTarget))',
                'count' => 100,
            ]);

        if (!$orgAclsResponse->successful()) {
            return $destinations;
        }

        $elements = $orgAclsResponse->json('elements');
        if (!is_array($elements)) {
            return $destinations;
        }

        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }
            $target = trim((string) ($element['organizationalTarget'] ?? ''));
            if (!str_starts_with($target, 'urn:li:organization:')) {
                continue;
            }
            $orgId = trim((string) substr($target, strlen('urn:li:organization:')));
            if ($orgId === '') {
                continue;
            }

            $orgName = 'LinkedIn Org ' . $orgId;
            $orgAvatar = null;
            $orgResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->withHeaders($this->linkedInDiscoveryHeaders())
                ->get('https://api.linkedin.com/v2/organizations/' . $orgId, [
                    'projection' => '(id,localizedName,vanityName)',
                ]);
            if ($orgResponse->successful() && is_array($orgResponse->json())) {
                $org = $orgResponse->json();
                $candidate = trim((string) ($org['localizedName'] ?? $org['vanityName'] ?? ''));
                if ($candidate !== '') {
                    $orgName = $candidate;
                }
            }

            $destinations[] = [
                'key' => 'organization:' . $orgId,
                'platform_user_id' => $orgId,
                'username' => null,
                'display_name' => $orgName,
                'avatar_url' => $orgAvatar,
                'access_token' => $accessToken,
                'metadata' => [
                    'account_type' => 'organization',
                    'author_urn' => 'urn:li:organization:' . $orgId,
                    'owner_urn' => 'urn:li:organization:' . $orgId,
                    'parent_user_id' => $oauthUserId,
                ],
            ];
        }

        return $destinations;
    }

    /**
     * @return array<string, string>
     */
    private function linkedInDiscoveryHeaders(): array
    {
        $headers = [
            'X-Restli-Protocol-Version' => '2.0.0',
        ];

        $version = trim((string) config('platforms.linkedin.api_version', ''));
        if ($version !== '') {
            $headers['LinkedIn-Version'] = $version;
        }

        return $headers;
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
