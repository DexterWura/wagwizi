<?php

namespace App\Services\SocialAccount;

use App\Models\SocialAccount;
use App\Services\Platform\PlatformRegistry;
use App\Services\Platform\Platform;
use App\Services\Platform\TokenResult;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TokenRefreshService
{
    private const MAX_TRANSIENT_REFRESH_FAILURES = 5;
    private const REFRESH_LOCK_SECONDS = 30;

    public function __construct(
        private readonly PlatformRegistry $registry,
    ) {}

    /**
     * Refresh the token for a single social account if it's expired or nearing expiry.
     */
    public function refreshIfNeeded(SocialAccount $account): bool
    {
        if (!$this->needsRefresh($account)) {
            return true;
        }

        return $this->refresh($account);
    }

    /**
     * Force refresh the token regardless of expiry status.
     */
    public function refresh(SocialAccount $account): bool
    {
        $lockKey = 'social_account_token_refresh:' . $account->id;
        $lock = Cache::lock($lockKey, self::REFRESH_LOCK_SECONDS);

        try {
            return $lock->block(5, function () use ($account): bool {
                return $this->refreshUnlocked($account);
            });
        } catch (LockTimeoutException) {
            // Another process is already refreshing this account. Re-check state and avoid duplicate refreshes.
            $account->refresh();

            if (!$this->needsRefresh($account)) {
                return true;
            }

            Log::warning('Token refresh lock timeout', [
                'account_id' => $account->id,
                'platform'   => $account->platform,
            ]);

            return false;
        }
    }

    private function refreshUnlocked(SocialAccount $account): bool
    {
        $platform = Platform::tryFrom($account->platform);

        if ($platform === null) {
            return false;
        }

        if ($platform === Platform::Bluesky) {
            return $this->refreshBlueskySession($account);
        }

        if (!$platform->usesOAuth()) {
            return true;
        }

        if (empty($account->refresh_token)) {
            Log::warning('Cannot refresh token: no refresh_token stored', [
                'account_id' => $account->id,
                'platform'   => $account->platform,
            ]);
            return false;
        }

        try {
            $adapter = $this->registry->resolve($platform);
            $attemptedRefreshToken = (string) $account->refresh_token;
            $result  = $adapter->refreshToken($attemptedRefreshToken);

            if (!$result->success) {
                $result = $this->retryWithLatestRefreshToken(
                    account: $account,
                    attemptedRefreshToken: $attemptedRefreshToken,
                    result: $result,
                    platform: $platform
                );
            }

            if (!$result->success) {
                $isPermanent = $this->isPermanentRefreshFailure($result->errorMessage, $platform);

                Log::warning('Token refresh failed', [
                    'account_id' => $account->id,
                    'platform'   => $account->platform,
                    'error'      => $result->errorMessage,
                    'permanent'  => $isPermanent,
                ]);

                $this->recordRefreshFailure($account, (string) $result->errorMessage, $isPermanent);
                return false;
            }

            $account->update([
                'access_token'     => $result->accessToken,
                'refresh_token'    => $result->refreshToken ?? $account->refresh_token,
                'token_expires_at' => $result->expiresAt,
                'status'           => 'active',
                'metadata'         => $this->resetRefreshDiagnostics($account),
            ]);

            Log::info('Token refreshed successfully', [
                'account_id' => $account->id,
                'platform'   => $account->platform,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Token refresh exception', [
                'account_id' => $account->id,
                'platform'   => $account->platform,
                'exception'  => $e->getMessage(),
            ]);
            $this->recordRefreshFailure($account, $e->getMessage(), false);
            return false;
        }
    }

    /**
     * Refresh all tokens that are nearing expiry (within 30 minutes).
     * Skips disconnected accounts and Telegram (permanent tokens).
     */
    public function refreshExpiringSoon(): int
    {
        $accounts = SocialAccount::active()
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addMinutes(30))
            ->whereNotNull('refresh_token')
            ->where('platform', '!=', 'telegram')
            ->get();

        $refreshed = 0;

        foreach ($accounts as $account) {
            if ($this->refresh($account)) {
                $refreshed++;
            }
        }

        return $refreshed;
    }

    /**
     * Refresh OAuth / Bluesky accounts that have a refresh token but no stored expiry.
     * Run on a slow cadence (e.g. daily) so tokens are rotated even when token_expires_at was never set.
     */
    public function refreshAccountsWithUnknownExpiry(): int
    {
        $platformSlugs = [];
        foreach (Platform::cases() as $p) {
            if ($p === Platform::Telegram) {
                continue;
            }
            if ($p->usesOAuth() || $p === Platform::Bluesky) {
                $platformSlugs[] = $p->value;
            }
        }

        if ($platformSlugs === []) {
            return 0;
        }

        $accounts = SocialAccount::active()
            ->whereNull('token_expires_at')
            ->whereNotNull('refresh_token')
            ->whereIn('platform', $platformSlugs)
            ->get();

        $refreshed = 0;

        foreach ($accounts as $account) {
            if ($this->refresh($account)) {
                $refreshed++;
            }
        }

        return $refreshed;
    }

    private function needsRefresh(SocialAccount $account): bool
    {
        if ($account->token_expires_at === null) {
            return false;
        }

        return $account->token_expires_at->lte(now()->addMinutes(5));
    }

    private function refreshBlueskySession(SocialAccount $account): bool
    {
        if (empty($account->refresh_token)) {
            Log::warning('Cannot refresh Bluesky: no refresh token', [
                'account_id' => $account->id,
            ]);

            return false;
        }

        try {
            $adapter = $this->registry->resolve(Platform::Bluesky);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Bluesky refresh skipped', [
                'account_id' => $account->id,
                'reason'     => $e->getMessage(),
            ]);

            return false;
        }

        try {
            $attemptedRefreshToken = (string) $account->refresh_token;
            $result = $adapter->refreshToken($attemptedRefreshToken);

            if (!$result->success) {
                $result = $this->retryWithLatestRefreshToken(
                    account: $account,
                    attemptedRefreshToken: $attemptedRefreshToken,
                    result: $result,
                    platform: Platform::Bluesky
                );
            }

            if (!$result->success) {
                $isPermanent = $this->isPermanentRefreshFailure($result->errorMessage, Platform::Bluesky);
                Log::warning('Bluesky session refresh failed', [
                    'account_id' => $account->id,
                    'error'      => $result->errorMessage,
                    'permanent'  => $isPermanent,
                ]);
                $this->recordRefreshFailure($account, (string) $result->errorMessage, $isPermanent);

                return false;
            }

            $account->update([
                'access_token'     => $result->accessToken,
                'refresh_token'    => $result->refreshToken ?? $account->refresh_token,
                'token_expires_at' => $result->expiresAt,
                'status'           => 'active',
                'metadata'         => $this->resetRefreshDiagnostics($account),
            ]);

            Log::info('Bluesky session refreshed', ['account_id' => $account->id]);

            return true;
        } catch (\Exception $e) {
            Log::error('Bluesky refresh exception', [
                'account_id' => $account->id,
                'exception'  => $e->getMessage(),
            ]);
            $this->recordRefreshFailure($account, $e->getMessage(), false);

            return false;
        }
    }

    private function isPermanentRefreshFailure(?string $errorMessage, ?Platform $platform = null): bool
    {
        $msg = strtolower((string) $errorMessage);
        if ($msg === '') {
            return false;
        }

        if ($platform === Platform::Bluesky) {
            // Bluesky sessions can report "invalid refresh"/"replayed" during refresh-token races.
            // Treat those as transient so we do not expire accounts prematurely.
            $blueskyPermanentSignals = [
                'refresh token expired',
                'token expired',
                'expiredtoken',
                'token revoked',
                'revoked',
                'account has been taken down',
            ];

            foreach ($blueskyPermanentSignals as $signal) {
                if (str_contains($msg, $signal)) {
                    return true;
                }
            }

            return false;
        }

        $permanentSignals = [
            'invalid_grant',
            'invalid refresh',
            'refresh token is invalid',
            'refresh token expired',
            'unauthorized_client',
            'revoked',
            'token revoked',
        ];

        foreach ($permanentSignals as $signal) {
            if (str_contains($msg, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function retryWithLatestRefreshToken(
        SocialAccount $account,
        string $attemptedRefreshToken,
        TokenResult $result,
        Platform $platform,
    ): TokenResult {
        $error = strtolower((string) $result->errorMessage);
        $mayBeStaleToken = str_contains($error, 'invalid refresh')
            || str_contains($error, 'replayed')
            || str_contains($error, 'invalid_grant');

        if (!$mayBeStaleToken) {
            return $result;
        }

        $account->refresh();
        $latestRefreshToken = (string) ($account->refresh_token ?? '');

        if ($latestRefreshToken === '' || hash_equals($attemptedRefreshToken, $latestRefreshToken)) {
            return $result;
        }

        try {
            $adapter = $this->registry->resolve($platform);
            $retryResult = $adapter->refreshToken($latestRefreshToken);

            Log::info('Retried token refresh with latest stored refresh token', [
                'account_id' => $account->id,
                'platform'   => $platform->value,
                'success'    => $retryResult->success,
            ]);

            return $retryResult;
        } catch (\Exception $e) {
            Log::warning('Retry with latest refresh token failed', [
                'account_id' => $account->id,
                'platform'   => $platform->value,
                'error'      => $e->getMessage(),
            ]);

            return $result;
        }
    }

    private function recordRefreshFailure(SocialAccount $account, string $errorMessage, bool $permanent): void
    {
        $metadata = is_array($account->metadata) ? $account->metadata : [];
        $failures = (int) ($metadata['refresh_failure_count'] ?? 0) + 1;

        $metadata['refresh_failure_count'] = $failures;
        $metadata['last_refresh_error'] = mb_substr($errorMessage, 0, 400);
        $metadata['last_refresh_attempt_at'] = now()->toIso8601String();

        $newStatus = $account->status;
        if ($permanent || $failures >= self::MAX_TRANSIENT_REFRESH_FAILURES) {
            $newStatus = 'expired';
            $metadata['refresh_failure_reason'] = $permanent ? 'permanent' : 'too_many_transient_failures';
        }

        $account->update([
            'status'   => $newStatus,
            'metadata' => $metadata,
        ]);
    }

    private function resetRefreshDiagnostics(SocialAccount $account): array
    {
        $metadata = is_array($account->metadata) ? $account->metadata : [];
        unset(
            $metadata['refresh_failure_count'],
            $metadata['last_refresh_error'],
            $metadata['last_refresh_attempt_at'],
            $metadata['refresh_failure_reason']
        );

        return $metadata;
    }
}
