<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class DashboardMetricsService
{
    public const RANGE_TODAY = 'today';

    public const RANGE_WEEK = 'week';

    public const RANGE_30D = '30d';

    public const RANGE_90D = '90d';

    public const SCOPE_ALL = 'all';

    public const SCOPE_PLATFORM = 'platform';

    /**
     * @return array{
     *   range: string,
     *   scope: string,
     *   platform: string|null,
     *   platformOptions: string[],
     *   connectedAccountsCount: int,
     *   publishedPostsCount: int,
     *   scheduledPostsCount: int,
     *   publishedSubLabel: string,
     *   scheduledSubLabel: string,
     *   recentPosts: \Illuminate\Support\Collection,
     *   nextUp: \Illuminate\Support\Collection,
     * }
     */
    public function build(User $user, Request $request): array
    {
        $range  = $this->validateRange($request->query('range'));
        $scope  = $this->validateScope($request->query('scope'));
        $tz     = $user->timezone ?: (string) config('app.timezone', 'UTC');
        $now    = Carbon::now($tz);
        $platformReq = $request->query('platform');

        $platformOptions = $user->socialAccounts()
            ->active()
            ->orderBy('platform')
            ->pluck('platform')
            ->unique()
            ->values()
            ->all();

        $platformSlug = null;
        if ($scope === self::SCOPE_PLATFORM) {
            $platformSlug = $this->resolvePlatformSlug((string) $platformReq, $platformOptions);
        }

        $emptyPlatformScope = $scope === self::SCOPE_PLATFORM && $platformSlug === null;

        [$pubStart, $pubEnd] = $this->publishedWindow($range, $now);
        [$schedStart, $schedEnd] = $this->scheduledWindow($range, $now);

        $publishedCount = $user->posts()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$pubStart, $pubEnd])
            ->when($emptyPlatformScope, static function (Builder $q): void {
                $q->whereRaw('0 = 1');
            })
            ->when(! $emptyPlatformScope && $platformSlug !== null, function (Builder $q) use ($platformSlug): void {
                $q->whereJsonContains('platforms', $platformSlug);
            })
            ->count();

        $scheduledCount = $user->posts()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$schedStart, $schedEnd])
            ->when($emptyPlatformScope, static function (Builder $q): void {
                $q->whereRaw('0 = 1');
            })
            ->when(! $emptyPlatformScope && $platformSlug !== null, function (Builder $q) use ($platformSlug): void {
                $q->whereJsonContains('platforms', $platformSlug);
            })
            ->count();

        $connectedCount = $scope === self::SCOPE_PLATFORM && $platformSlug !== null
            ? $user->socialAccounts()->active()->where('platform', $platformSlug)->count()
            : $user->socialAccounts()->active()->count();

        $recentPosts = $user->posts()
            ->whereIn('status', ['published', 'scheduled', 'draft'])
            ->where('updated_at', '>=', $pubStart)
            ->where('updated_at', '<=', $pubEnd)
            ->when($emptyPlatformScope, static function (Builder $q): void {
                $q->whereRaw('0 = 1');
            })
            ->when(! $emptyPlatformScope && $platformSlug !== null, function (Builder $q) use ($platformSlug): void {
                $q->whereJsonContains('platforms', $platformSlug);
            })
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'content', 'status', 'scheduled_at', 'published_at', 'updated_at']);

        $nextUp = $user->posts()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', $now)
            ->when($emptyPlatformScope, static function (Builder $q): void {
                $q->whereRaw('0 = 1');
            })
            ->when(! $emptyPlatformScope && $platformSlug !== null, function (Builder $q) use ($platformSlug): void {
                $q->whereJsonContains('platforms', $platformSlug);
            })
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get(['id', 'content', 'status', 'scheduled_at']);

        return [
            'range'                   => $range,
            'scope'                   => $scope,
            'platform'                => $platformSlug,
            'platformOptions'         => $platformOptions,
            'connectedAccountsCount'  => $connectedCount,
            'publishedPostsCount'     => $publishedCount,
            'scheduledPostsCount'     => $scheduledCount,
            'publishedSubLabel'       => $this->publishedSubLabel($range),
            'scheduledSubLabel'       => $this->scheduledSubLabel($range),
            'recentPosts'             => $recentPosts,
            'nextUp'                  => $nextUp,
        ];
    }

    public function validateRange(?string $value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($v, [self::RANGE_TODAY, self::RANGE_WEEK, self::RANGE_30D, self::RANGE_90D], true)
            ? $v
            : self::RANGE_30D;
    }

    public function validateScope(?string $value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : '';

        return $v === self::SCOPE_PLATFORM ? self::SCOPE_PLATFORM : self::SCOPE_ALL;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function publishedWindow(string $range, Carbon $now): array
    {
        return match ($range) {
            self::RANGE_TODAY => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
            ],
            self::RANGE_WEEK => [
                $now->copy()->startOfWeek(Carbon::MONDAY),
                $now->copy()->endOfWeek(Carbon::SUNDAY),
            ],
            self::RANGE_30D => [
                $now->copy()->subDays(30)->startOfDay(),
                $now->copy(),
            ],
            self::RANGE_90D => [
                $now->copy()->subDays(90)->startOfDay(),
                $now->copy(),
            ],
            default => [
                $now->copy()->subDays(30)->startOfDay(),
                $now->copy(),
            ],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function scheduledWindow(string $range, Carbon $now): array
    {
        return match ($range) {
            self::RANGE_TODAY => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
            ],
            self::RANGE_WEEK => [
                $now->copy()->startOfWeek(Carbon::MONDAY),
                $now->copy()->endOfWeek(Carbon::SUNDAY),
            ],
            self::RANGE_30D => [
                $now->copy()->addSecond(),
                $now->copy()->addDays(30)->endOfDay(),
            ],
            self::RANGE_90D => [
                $now->copy()->addSecond(),
                $now->copy()->addDays(90)->endOfDay(),
            ],
            default => [
                $now->copy()->addSecond(),
                $now->copy()->addDays(30)->endOfDay(),
            ],
        };
    }

    private function publishedSubLabel(string $range): string
    {
        return match ($range) {
            self::RANGE_TODAY => 'Published today',
            self::RANGE_WEEK => 'Published this week',
            self::RANGE_30D => 'Last 30 days',
            self::RANGE_90D => 'Last 90 days',
            default => 'Last 30 days',
        };
    }

    private function scheduledSubLabel(string $range): string
    {
        return match ($range) {
            self::RANGE_TODAY => 'Scheduled today',
            self::RANGE_WEEK => 'Scheduled this week',
            self::RANGE_30D => 'Next 30 days',
            self::RANGE_90D => 'Next 90 days',
            default => 'Next 30 days',
        };
    }

    /**
     * @param  list<string>  $allowed
     */
    private function resolvePlatformSlug(string $requested, array $allowed): ?string
    {
        if ($allowed === []) {
            return null;
        }

        if ($requested !== '' && in_array($requested, $allowed, true)) {
            return $requested;
        }

        return $allowed[0];
    }
}
