<?php

namespace App\Services\SocialAccount;

use App\Models\SocialAccount;
use App\Services\Platform\PlatformRegistry;
use App\Services\Platform\Platform;
use Illuminate\Support\Facades\Log;

class TokenRefreshService
{
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
            $result  = $adapter->refreshToken($account->refresh_token);

            if (!$result->success) {
                Log::warning('Token refresh failed', [
                    'account_id' => $account->id,
                    'platform'   => $account->platform,
                    'error'      => $result->errorMessage,
                ]);

                $account->update(['status' => 'expired']);
                return false;
            }

            $account->update([
                'access_token'     => $result->accessToken,
                'refresh_token'    => $result->refreshToken ?? $account->refresh_token,
                'token_expires_at' => $result->expiresAt,
                'status'           => 'active',
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
            $result = $adapter->refreshToken($account->refresh_token);

            if (!$result->success) {
                Log::warning('Bluesky session refresh failed', [
                    'account_id' => $account->id,
                    'error'      => $result->errorMessage,
                ]);
                $account->update(['status' => 'expired']);

                return false;
            }

            $account->update([
                'access_token'     => $result->accessToken,
                'refresh_token'    => $result->refreshToken ?? $account->refresh_token,
                'token_expires_at' => $result->expiresAt,
                'status'           => 'active',
            ]);

            Log::info('Bluesky session refreshed', ['account_id' => $account->id]);

            return true;
        } catch (\Exception $e) {
            Log::error('Bluesky refresh exception', [
                'account_id' => $account->id,
                'exception'  => $e->getMessage(),
            ]);

            return false;
        }
    }
}
