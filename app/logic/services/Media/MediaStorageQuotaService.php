<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Models\User;

final class MediaStorageQuotaService
{
    private const FALLBACK_DEFAULT_LIMIT_MB = 2048;

    public function limitMbForUser(User $user): int
    {
        $user->loadMissing('subscription.planModel');
        $planLimit = (int) ($user->subscription?->planModel?->media_storage_limit_mb ?? 0);

        return $planLimit > 0 ? $planLimit : self::FALLBACK_DEFAULT_LIMIT_MB;
    }

    public function limitBytesForUser(User $user): int
    {
        return $this->limitMbForUser($user) * 1024 * 1024;
    }

    public function usedBytesForUser(User $user): int
    {
        return (int) MediaFile::query()
            ->where('user_id', $user->id)
            ->sum('size_bytes');
    }

    public function remainingBytesForUser(User $user): int
    {
        return max(0, $this->limitBytesForUser($user) - $this->usedBytesForUser($user));
    }

    public function userCanStoreAdditionalBytes(User $user, int $incomingBytes): bool
    {
        if ($incomingBytes <= 0) {
            return true;
        }

        return $incomingBytes <= $this->remainingBytesForUser($user);
    }

    /**
     * @return array{limit_mb: int, limit_bytes: int, used_bytes: int, remaining_bytes: int, usage_percent: float}
     */
    public function summaryForUser(User $user): array
    {
        $limitBytes = $this->limitBytesForUser($user);
        $usedBytes = $this->usedBytesForUser($user);
        $remaining = max(0, $limitBytes - $usedBytes);
        $percent = $limitBytes > 0 ? min(100, max(0, ($usedBytes / $limitBytes) * 100)) : 0.0;

        return [
            'limit_mb' => $this->limitMbForUser($user),
            'limit_bytes' => $limitBytes,
            'used_bytes' => $usedBytes,
            'remaining_bytes' => $remaining,
            'usage_percent' => round($percent, 2),
        ];
    }
}

