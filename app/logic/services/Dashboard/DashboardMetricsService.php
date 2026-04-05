<?php

namespace App\Services\Dashboard;

use App\Models\SocialAccount;
use App\Services\SocialAccount\TokenRefreshService;
use App\Services\Cache\UserCacheVersionService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class DashboardMetricsService
{
    private const DASHBOARD_CACHE_TTL = 120;

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
     *   totalAudienceCount: int|null,
     * }
     */
    public function build(User $user, Request $request): array
    {
        $range  = $this->validateRange($request->query('range'));
        $scope  = $this->validateScope($request->query('scope'));
        $platformReq = (string) ($request->query('platform') ?? '');
        $cacheVersion = app(UserCacheVersionService::class)->current($user->id);

        $cacheKey = 'dashboard_metrics:v1:'
            . $cacheVersion . ':'
            . $user->id . ':'
            . $range . ':'
            . $scope . ':'
            . sha1($platformReq);

        return Cache::remember($cacheKey, self::DASHBOARD_CACHE_TTL, function () use ($user, $range, $scope, $platformReq): array {
        $tz     = $user->timezone ?: (string) config('app.timezone', 'UTC');
        $now    = Carbon::now($tz);

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
                $q->whereHas('postPlatforms', fn (Builder $pp) => $pp->where('platform', $platformSlug));
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
                $q->whereHas('postPlatforms', fn (Builder $pp) => $pp->where('platform', $platformSlug));
            })
            ->count();

        $connectedCount = $scope === self::SCOPE_PLATFORM && $platformSlug !== null
            ? $user->socialAccounts()->active()->where('platform', $platformSlug)->count()
            : $user->socialAccounts()->active()->count();
        $totalAudience = $this->computeAudienceTotal($user, $scope, $platformSlug);

        $recentPosts = $user->posts()
            ->whereIn('status', ['published', 'scheduled', 'draft'])
            ->where('updated_at', '>=', $pubStart)
            ->where('updated_at', '<=', $pubEnd)
            ->when($emptyPlatformScope, static function (Builder $q): void {
                $q->whereRaw('0 = 1');
            })
            ->when(! $emptyPlatformScope && $platformSlug !== null, function (Builder $q) use ($platformSlug): void {
                $q->whereHas('postPlatforms', fn (Builder $pp) => $pp->where('platform', $platformSlug));
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
                $q->whereHas('postPlatforms', fn (Builder $pp) => $pp->where('platform', $platformSlug));
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
            'totalAudienceCount'      => $totalAudience,
        ];
        });
    }

    private function computeAudienceTotal(User $user, string $scope, ?string $platformSlug): ?int
    {
        $accounts = $user->socialAccounts()
            ->active()
            ->when($scope === self::SCOPE_PLATFORM && $platformSlug !== null, function ($q) use ($platformSlug) {
                $q->where('platform', $platformSlug);
            })
            ->get();

        if ($accounts->isEmpty()) {
            return 0;
        }

        $total = 0;
        $any = false;
        foreach ($accounts as $account) {
            $count = $this->audienceForAccount($account);
            if ($count !== null) {
                $total += max(0, $count);
                $any = true;
            }
        }

        return $any ? $total : null;
    }

    private const AUDIENCE_CACHE_TTL = 86400;

    private function audienceForAccount(SocialAccount $account): ?int
    {
        $cacheKey = "dashboard_audience:{$account->id}";

        return Cache::remember($cacheKey, self::AUDIENCE_CACHE_TTL, function () use ($account): ?int {
            return $this->fetchAudienceForAccount($account);
        });
    }

    private function fetchAudienceForAccount(SocialAccount $account): ?int
    {
        try {
            /** @var TokenRefreshService $refresh */
            $refresh = app(TokenRefreshService::class);
            $refresh->refreshIfNeeded($account);
            $account->refresh();
        } catch (\Throwable $e) {
            Log::debug('Audience refresh skipped', [
                'account_id' => $account->id,
                'platform'   => $account->platform,
                'error'      => $e->getMessage(),
            ]);
        }

        if (empty($account->access_token)) {
            return null;
        }

        return match ($account->platform) {
            'twitter'   => $this->twitterAudience($account),
            'facebook'  => $this->facebookAudience($account),
            'instagram' => $this->instagramAudience($account),
            'linkedin'  => $this->linkedinAudience($account),
            'threads'   => $this->threadsAudience($account),
            default     => $this->metadataAudience($account),
        };
    }

    private function metadataAudience(SocialAccount $account): ?int
    {
        $m = is_array($account->metadata) ? $account->metadata : [];
        foreach (['followers_count', 'follower_count', 'subscribers_count', 'connections_count', 'audience_count'] as $k) {
            if (isset($m[$k]) && is_numeric($m[$k])) {
                return (int) $m[$k];
            }
        }

        return null;
    }

    private function twitterAudience(SocialAccount $account): ?int
    {
        $resp = Http::withToken($account->access_token)
            ->timeout(12)
            ->acceptJson()
            ->get('https://api.x.com/2/users/me', [
                'user.fields' => 'public_metrics',
            ]);

        if (! $resp->successful()) {
            return $this->metadataAudience($account);
        }

        $count = $resp->json('data.public_metrics.followers_count');
        return is_numeric($count) ? (int) $count : $this->metadataAudience($account);
    }

    private function facebookAudience(SocialAccount $account): ?int
    {
        $pageId = trim((string) $account->platform_user_id);
        if ($pageId === '') {
            return $this->metadataAudience($account);
        }

        $resp = Http::withToken($account->access_token)
            ->timeout(12)
            ->acceptJson()
            ->get("https://graph.facebook.com/v21.0/{$pageId}", [
                'fields' => 'followers_count,fan_count',
            ]);

        if (! $resp->successful()) {
            return $this->metadataAudience($account);
        }

        $followers = $resp->json('followers_count');
        $fans = $resp->json('fan_count');
        if (is_numeric($followers)) return (int) $followers;
        if (is_numeric($fans)) return (int) $fans;

        return $this->metadataAudience($account);
    }

    private function instagramAudience(SocialAccount $account): ?int
    {
        $id = trim((string) $account->platform_user_id);
        if ($id === '') {
            return $this->metadataAudience($account);
        }

        $resp = Http::withToken($account->access_token)
            ->timeout(12)
            ->acceptJson()
            ->get("https://graph.facebook.com/v21.0/{$id}", [
                'fields' => 'followers_count',
            ]);

        if (! $resp->successful()) {
            return $this->metadataAudience($account);
        }

        $followers = $resp->json('followers_count');
        return is_numeric($followers) ? (int) $followers : $this->metadataAudience($account);
    }

    private function threadsAudience(SocialAccount $account): ?int
    {
        $id = trim((string) $account->platform_user_id);
        if ($id === '') {
            return $this->metadataAudience($account);
        }

        $resp = Http::withToken($account->access_token)
            ->timeout(12)
            ->acceptJson()
            ->get("https://graph.threads.net/v1.0/{$id}", [
                'fields' => 'followers_count',
            ]);

        if (! $resp->successful()) {
            return $this->metadataAudience($account);
        }

        $followers = $resp->json('followers_count');
        return is_numeric($followers) ? (int) $followers : $this->metadataAudience($account);
    }

    private function linkedinAudience(SocialAccount $account): ?int
    {
        $resp = Http::withToken($account->access_token)
            ->timeout(12)
            ->acceptJson()
            ->withHeaders($this->linkedInHeaders())
            ->get('https://api.linkedin.com/rest/memberFollowersCount', [
                'q' => 'me',
            ]);

        if ($resp->successful()) {
            $count = $resp->json('elements.0.memberFollowersCount');
            if (is_numeric($count)) {
                return (int) $count;
            }
        }

        return $this->metadataAudience($account);
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

    /**
     * @return array<string, string>
     */
    private function linkedInHeaders(): array
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
}
