<?php

namespace App\Services\SocialAccount;

use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Platform\Platform;
use App\Services\Cache\UserCacheVersionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AccountLinkingService
{
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
            $this->socialAccountLimit->assertCanAddPlatformAccount($user, $platform->value);
        }

        $account = DB::transaction(function () use (
            $user, $platform, $platformUserId, $accessToken, $refreshToken,
            $username, $displayName, $avatarUrl, $scopes, $expiresAt, $metadata, $existing
        ): SocialAccount {
            // Keep the previously stored refresh token when providers omit it on subsequent OAuth callbacks.
            $effectiveRefreshToken = $refreshToken ?? $existing?->refresh_token;

            return SocialAccount::updateOrCreate(
                [
                    'user_id'          => $user->id,
                    'platform'         => $platform->value,
                    'platform_user_id' => $platformUserId,
                ],
                [
                    'access_token'     => $accessToken,
                    'refresh_token'    => $effectiveRefreshToken,
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

        Cache::forget("dashboard_audience:{$account->id}");
        app(UserCacheVersionService::class)->bump($user->id);

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

    public function disconnect(User $user, int $accountId, bool $force = false): array
    {
        $account = SocialAccount::where('user_id', $user->id)
            ->where('id', $accountId)
            ->first();

        if ($account === null) {
            return ['disconnected' => false, 'cancelled_pending' => 0];
        }

        if ($account->status === 'disconnected') {
            return ['disconnected' => false, 'cancelled_pending' => 0];
        }

        $pendingQuery = $account->postPlatforms()
            ->whereIn('status', ['pending', 'publishing'])
            ->whereNull('published_at');
        $hasPendingPosts = $pendingQuery->exists();

        if ($hasPendingPosts) {
            if (! $force) {
                throw new InvalidArgumentException(
                    'This account has pending or publishing posts. Disconnecting will automatically cancel them. Confirm to continue.'
                );
            }

            $cancelled = $pendingQuery->update([
                'status' => 'failed',
                'error_message' => 'Disconnected account: posting cancelled by user.',
            ]);

            Log::info('Pending platform posts cancelled on force disconnect', [
                'user_id' => $user->id,
                'account_id' => $account->id,
                'platform' => $account->platform,
                'cancelled_count' => $cancelled,
            ]);

            $this->finalizeDisconnect($user, $account, 'Social account force-disconnected with pending post cancellation');
            return ['disconnected' => true, 'cancelled_pending' => $cancelled];
        }

        $this->finalizeDisconnect($user, $account, 'Social account disconnected');

        return ['disconnected' => true, 'cancelled_pending' => 0];
    }

    private const PLAN_ENFORCEMENT_FAIL_MESSAGE = 'This connection was removed because your subscription plan no longer allows it.';

    /**
     * Disconnect when the user's plan no longer allows this platform or profile cap was reduced.
     * Pending/publishing rows for this account are marked failed so disconnect is never blocked.
     */
    public function disconnectForPlanEnforcement(User $user, SocialAccount $account): void
    {
        if ($account->user_id !== $user->id) {
            throw new InvalidArgumentException('Account does not belong to this user.');
        }

        $this->disconnectAccountsForPlanEnforcement([$account]);
    }

    /**
     * Batch disconnect for plan reconciliation. Uses per-model {@see SocialAccount::update} so encrypted
     * token columns are written correctly (query-builder mass update would bypass casts).
     *
     * @param  iterable<SocialAccount>|Collection<int, SocialAccount>  $accounts
     * @return list<int> User IDs that had at least one account disconnected (for cache invalidation)
     */
    public function disconnectAccountsForPlanEnforcement(iterable $accounts): array
    {
        $collection = Collection::wrap($accounts)
            ->filter(static fn ($a): bool => $a instanceof SocialAccount && $a->status !== 'disconnected')
            ->unique('id')
            ->values();

        if ($collection->isEmpty()) {
            return [];
        }

        $ids = $collection->pluck('id')->all();

        PostPlatform::query()
            ->whereIn('social_account_id', $ids)
            ->whereIn('status', ['pending', 'publishing'])
            ->update([
                'status'        => 'failed',
                'error_message' => self::PLAN_ENFORCEMENT_FAIL_MESSAGE,
            ]);

        foreach ($collection as $account) {
            $account->update([
                'status'        => 'disconnected',
                'access_token'  => '',
                'refresh_token' => null,
            ]);
        }

        $affectedUserIds = [];
        foreach ($collection->groupBy(static fn (SocialAccount $a): int => (int) $a->user_id) as $userId => $group) {
            $uid = (int) $userId;
            $affectedUserIds[] = $uid;
            Log::info('Social accounts disconnected (plan enforcement)', [
                'user_id'     => $uid,
                'account_ids' => $group->pluck('id')->values()->all(),
                'platforms'   => $group->pluck('platform')->values()->all(),
            ]);
            app(UserCacheVersionService::class)->bump($uid);
        }

        return array_values(array_unique($affectedUserIds));
    }

    private function finalizeDisconnect(User $user, SocialAccount $account, string $logMessage): void
    {
        $account->update([
            'status'        => 'disconnected',
            'access_token'  => '',
            'refresh_token' => null,
        ]);

        Log::info($logMessage, [
            'user_id'    => $user->id,
            'account_id' => $account->id,
            'platform'   => $account->platform,
        ]);

        app(UserCacheVersionService::class)->bump($user->id);
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
