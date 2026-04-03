<?php

namespace App\Services\SocialAccount;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Platform\Platform;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AccountLinkingService
{
    private const MAX_ACCOUNTS_PER_PLATFORM = 10;

    public function __construct(
        private readonly SocialAccountLimitService $socialAccountLimit,
    ) {}

    /**
     * Create or update a social account after a successful OAuth callback.
     */
    public function linkAccount(
        User     $user,
        Platform $platform,
        string   $platformUserId,
        string   $accessToken,
        ?string  $refreshToken,
        ?string  $username,
        ?string  $displayName,
        ?string  $avatarUrl,
        ?array   $scopes,
        ?\DateTimeInterface $expiresAt,
        array    $metadata = [],
    ): SocialAccount {
        if (trim($platformUserId) === '') {
            throw new InvalidArgumentException('Platform user ID cannot be empty.');
        }

        if (trim($accessToken) === '') {
            throw new InvalidArgumentException('Access token cannot be empty.');
        }

        $existing = SocialAccount::where('user_id', $user->id)
            ->where('platform', $platform->value)
            ->where('platform_user_id', $platformUserId)
            ->first();

        if ($existing === null) {
            $this->socialAccountLimit->assertCanAddAccount($user);

            $activeCount = SocialAccount::where('user_id', $user->id)
                ->where('platform', $platform->value)
                ->where('status', 'active')
                ->count();

            if ($activeCount >= self::MAX_ACCOUNTS_PER_PLATFORM) {
                throw new InvalidArgumentException(
                    "You can connect up to " . self::MAX_ACCOUNTS_PER_PLATFORM
                    . " {$platform->label()} accounts. Please disconnect one first."
                );
            }
        }

        $account = DB::transaction(function () use (
            $user, $platform, $platformUserId, $accessToken, $refreshToken,
            $username, $displayName, $avatarUrl, $scopes, $expiresAt, $metadata
        ): SocialAccount {
            return SocialAccount::updateOrCreate(
                [
                    'user_id'          => $user->id,
                    'platform'         => $platform->value,
                    'platform_user_id' => $platformUserId,
                ],
                [
                    'access_token'     => $accessToken,
                    'refresh_token'    => $refreshToken,
                    'username'         => $username,
                    'display_name'     => $displayName,
                    'avatar_url'       => $avatarUrl,
                    'scopes'           => $scopes,
                    'token_expires_at' => $expiresAt,
                    'status'           => 'active',
                    'metadata'         => $metadata,
                ],
            );
        });

        Log::info('Social account linked', [
            'user_id'    => $user->id,
            'platform'   => $platform->value,
            'account_id' => $account->id,
            'username'   => $username,
        ]);

        return $account;
    }

    /**
     * Store a Telegram bot connection (no OAuth flow).
     */
    public function linkTelegram(
        User   $user,
        string $botToken,
        string $chatId,
        ?string $channelName = null,
    ): SocialAccount {
        if (trim($botToken) === '') {
            throw new InvalidArgumentException('Bot token cannot be empty.');
        }

        if (trim($chatId) === '') {
            throw new InvalidArgumentException('Chat ID cannot be empty.');
        }

        return $this->linkAccount(
            user:           $user,
            platform:       Platform::Telegram,
            platformUserId: $chatId,
            accessToken:    $botToken,
            refreshToken:   null,
            username:       $channelName,
            displayName:    $channelName ?? "Chat {$chatId}",
            avatarUrl:      null,
            scopes:         null,
            expiresAt:      null,
        );
    }

    /**
     * Store a WordPress site connection (no OAuth — uses Application Passwords).
     */
    public function linkWordPress(
        User    $user,
        string  $siteUrl,
        string  $wpUsername,
        string  $appPassword,
        string  $platformUserId,
        ?string $displayName = null,
        ?string $avatarUrl = null,
    ): SocialAccount {
        if (trim($siteUrl) === '') {
            throw new InvalidArgumentException('Site URL cannot be empty.');
        }

        if (trim($appPassword) === '') {
            throw new InvalidArgumentException('Application password cannot be empty.');
        }

        return $this->linkAccount(
            user:           $user,
            platform:       Platform::WordPress,
            platformUserId: $platformUserId,
            accessToken:    $appPassword,
            refreshToken:   null,
            username:       $wpUsername,
            displayName:    $displayName ?? $wpUsername,
            avatarUrl:      $avatarUrl,
            scopes:         null,
            expiresAt:      null,
            metadata:       [
                'site_url'    => rtrim($siteUrl, '/'),
                'wp_username' => $wpUsername,
            ],
        );
    }

    /**
     * Store a Discord webhook connection (no OAuth — user provides a webhook URL).
     */
    /**
     * Bluesky: handle (or email) + App Password. Tokens are JWTs from AT Proto session.
     */
    public function linkBluesky(
        User               $user,
        string             $identifier,
        string             $did,
        string             $handle,
        string             $accessJwt,
        string             $refreshJwt,
        ?string            $avatarUrl,
        ?\DateTimeInterface $expiresAt,
    ): SocialAccount {
        if (trim($did) === '') {
            throw new InvalidArgumentException('Bluesky DID cannot be empty.');
        }

        return $this->linkAccount(
            user:           $user,
            platform:       Platform::Bluesky,
            platformUserId: $did,
            accessToken:    $accessJwt,
            refreshToken:   $refreshJwt,
            username:       $handle,
            displayName:    $handle,
            avatarUrl:      $avatarUrl,
            scopes:         null,
            expiresAt:      $expiresAt,
            metadata:       [
                'identifier' => $identifier,
            ],
        );
    }

    /**
     * WhatsApp Cloud API: permanent or long-lived token, phone number ID, and API "to" recipient (channel / group / phone).
     */
    public function linkWhatsappChannels(
        User   $user,
        string $accessToken,
        string $phoneNumberId,
        string $channelRecipient,
        string $recipientType,
        ?string $displayName = null,
    ): SocialAccount {
        $token = trim($accessToken);
        $phoneId = trim($phoneNumberId);
        $to = trim($channelRecipient);
        $recipientType = trim($recipientType);

        if ($token === '') {
            throw new InvalidArgumentException('Access token cannot be empty.');
        }
        if ($phoneId === '') {
            throw new InvalidArgumentException('Phone number ID cannot be empty.');
        }
        if ($to === '') {
            throw new InvalidArgumentException('Channel or recipient ID cannot be empty.');
        }
        if (!in_array($recipientType, ['individual', 'group'], true)) {
            throw new InvalidArgumentException('Recipient type must be individual or group.');
        }

        return $this->linkAccount(
            user:           $user,
            platform:       Platform::WhatsappChannels,
            platformUserId: $to,
            accessToken:    $token,
            refreshToken:   null,
            username:       $displayName,
            displayName:    $displayName ?? $to,
            avatarUrl:      null,
            scopes:         null,
            expiresAt:      null,
            metadata:       [
                'phone_number_id' => $phoneId,
                'recipient_type'  => $recipientType,
            ],
        );
    }

    public function linkDiscord(
        User    $user,
        string  $webhookUrl,
        string  $webhookId,
        ?string $channelName = null,
        ?string $avatarUrl = null,
    ): SocialAccount {
        if (trim($webhookUrl) === '') {
            throw new InvalidArgumentException('Webhook URL cannot be empty.');
        }

        return $this->linkAccount(
            user:           $user,
            platform:       Platform::Discord,
            platformUserId: $webhookId,
            accessToken:    $webhookUrl,
            refreshToken:   null,
            username:       $channelName,
            displayName:    $channelName ?? 'Discord Webhook',
            avatarUrl:      $avatarUrl,
            scopes:         null,
            expiresAt:      null,
            metadata:       [
                'webhook_url' => $webhookUrl,
            ],
        );
    }

    public function disconnect(User $user, int $accountId): bool
    {
        $account = SocialAccount::where('user_id', $user->id)
            ->where('id', $accountId)
            ->first();

        if ($account === null) {
            return false;
        }

        if ($account->status === 'disconnected') {
            return false;
        }

        $hasPendingPosts = $account->postPlatforms()
            ->whereIn('status', ['pending', 'publishing'])
            ->exists();

        if ($hasPendingPosts) {
            throw new InvalidArgumentException(
                'This account has pending or publishing posts. Cancel them before disconnecting.'
            );
        }

        $account->update([
            'status'        => 'disconnected',
            'access_token'  => null,
            'refresh_token' => null,
        ]);

        Log::info('Social account disconnected', [
            'user_id'    => $user->id,
            'account_id' => $accountId,
            'platform'   => $account->platform,
        ]);

        return true;
    }

    /**
     * Get all active accounts for a user, optionally filtered by platform.
     */
    public function getAccounts(User $user, ?Platform $platform = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = $user->socialAccounts()->active();

        if ($platform !== null) {
            $query->where('platform', $platform->value);
        }

        return $query->get();
    }
}
