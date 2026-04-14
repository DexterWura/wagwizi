<?php

namespace App\Services\Dashboard;

use App\Models\SocialAccount;
use App\Services\Insights\AudienceInsightsService;
use App\Services\Platform\Platform;
use App\Services\SocialAccount\TokenRefreshService;
use App\Services\Cache\UserCacheVersionService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class DashboardMetricsService
{
    /** Seconds. Full dashboard metrics bundle (charts, counts, engagement rate, etc.). */
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
     *   platformFilterOptions: list<array{slug:string,label:string}>,
     *   connectedAccountsCount: int,
     *   publishedPostsCount: int,
     *   scheduledPostsCount: int,
     *   publishedSubLabel: string,
     *   scheduledSubLabel: string,
     *   recentPosts: \Illuminate\Support\Collection,
     *   nextUp: \Illuminate\Support\Collection,
     *   totalAudienceCount: int|null,
     *   engagementRateDisplay: string|null,
     *   engagementRateSubLabel: string,
     *   activityChart: array{labels: list<string>, labelsIso: list<string>, series: list<array{id: string, label: string, values: list<int>}>},
     *   platformMix: list<array{slug:string,label:string,pct:float}>,
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

        $filterSlugs = $this->dashboardPlatformFilterSlugs();
        $platformFilterOptions = $this->dashboardPlatformFilterOptions($filterSlugs);

        $platformSlug = null;
        if ($scope === self::SCOPE_PLATFORM) {
            $platformSlug = $this->resolvePlatformSlug((string) $platformReq, $platformOptions, $filterSlugs);
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

        [$engagementRateDisplay, $engagementRateSubLabel] = $this->computeEngagementRateCard(
            $user,
            $pubStart,
            $pubEnd,
            $emptyPlatformScope,
            $platformSlug,
            $connectedCount,
            $range
        );

        $activityChart = $this->buildActivityChartSeries(
            $user,
            $range,
            $emptyPlatformScope,
            $platformSlug,
            $now,
            $tz
        );

        $platformMix = [];
        if (! $emptyPlatformScope) {
            $mixFilter = $scope === self::SCOPE_PLATFORM && $platformSlug !== null ? $platformSlug : null;
            $platformMix = app(AudienceInsightsService::class)->platformMixForWindow(
                $user,
                $pubStart,
                $pubEnd,
                $mixFilter
            );
        }

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
            'platformFilterOptions'   => $platformFilterOptions,
            'connectedAccountsCount'  => $connectedCount,
            'publishedPostsCount'     => $publishedCount,
            'scheduledPostsCount'     => $scheduledCount,
            'publishedSubLabel'       => $this->publishedSubLabel($range),
            'scheduledSubLabel'       => $this->scheduledSubLabel($range),
            'recentPosts'             => $recentPosts,
            'nextUp'                  => $nextUp,
            'totalAudienceCount'      => $totalAudience,
            'engagementRateDisplay'   => $engagementRateDisplay,
            'engagementRateSubLabel'  => $engagementRateSubLabel,
            'activityChart'           => $activityChart,
            'platformMix'             => $platformMix,
        ];
        });
    }

    /**
     * Engagement card metric for published posts in the dashboard window.
     * Prefers classic engagement rate when impressions exist, and falls back
     * to average interactions/post when APIs omit impression fields.
     *
     * @return array{0: string|null, 1: string}
     */
    private function computeEngagementRateCard(
        User $user,
        Carbon $pubStart,
        Carbon $pubEnd,
        bool $emptyPlatformScope,
        ?string $platformSlug,
        int $connectedCount,
        string $range,
    ): array {
        if ($connectedCount === 0) {
            return [null, 'Connect accounts for analytics'];
        }

        if ($emptyPlatformScope) {
            return [null, 'Choose a network under “Per platform” to see engagement rate'];
        }

        $startUtc = $pubStart->copy()->utc();
        $endUtc = $pubEnd->copy()->utc();

        $q = DB::table('post_platforms')
            ->join('posts', 'posts.id', '=', 'post_platforms.post_id')
            ->where('posts.user_id', $user->id)
            ->where('posts.status', 'published')
            ->whereNotNull('posts.published_at')
            ->where('posts.published_at', '>=', $startUtc)
            ->where('posts.published_at', '<=', $endUtc);

        if ($platformSlug !== null) {
            $q->where('post_platforms.platform', $platformSlug);
        }

        $row = $q->selectRaw(
            'SUM(COALESCE(post_platforms.impressions_count, 0)) as imp, '
            . 'SUM(COALESCE(post_platforms.likes_count, 0) + COALESCE(post_platforms.comments_count, 0) + COALESCE(post_platforms.reposts_count, 0)) as eng, '
            . 'COUNT(*) as row_count, '
            . 'SUM(CASE WHEN post_platforms.impressions_count IS NOT NULL THEN 1 ELSE 0 END) as rows_with_impressions, '
            . 'SUM(CASE WHEN post_platforms.likes_count IS NOT NULL OR post_platforms.comments_count IS NOT NULL OR post_platforms.reposts_count IS NOT NULL THEN 1 ELSE 0 END) as rows_with_interaction_fields'
        )->first();

        $impressions = (int) ($row->imp ?? 0);
        $engagement = (int) ($row->eng ?? 0);
        $rowCount = max(0, (int) ($row->row_count ?? 0));
        $rowsWithImpressions = max(0, (int) ($row->rows_with_impressions ?? 0));
        $rowsWithInteractionFields = max(0, (int) ($row->rows_with_interaction_fields ?? 0));

        $periodHint = $this->engagementRatePeriodHint($range);

        if ($rowCount <= 0) {
            return [null, 'No analytics yet for published posts '.$periodHint];
        }

        if ($impressions > 0) {
            $pct = round(($engagement / $impressions) * 100, 1);
            $display = $this->formatEngagementRatePercent($pct);

            return [
                $display,
                'Reactions ÷ impressions on published posts '.$periodHint,
            ];
        }

        // Fallback for platforms/APIs that return likes/comments/reposts but no impressions.
        if ($rowsWithImpressions === 0 && $rowsWithInteractionFields > 0) {
            $avgInteractions = $rowCount > 0 ? round($engagement / $rowCount, 1) : 0.0;
            $display = $this->formatInteractionsPerPost($avgInteractions);

            return [
                $display,
                'Avg interactions per published post (impressions unavailable) '.$periodHint,
            ];
        }

        if ($rowsWithImpressions > 0) {
            return ['0%', 'Reactions ÷ impressions on published posts '.$periodHint];
        }

        return [null, 'Analytics fields are not available from connected networks '.$periodHint];
    }

    private function engagementRatePeriodHint(string $range): string
    {
        return match ($range) {
            self::RANGE_TODAY => '(today)',
            self::RANGE_WEEK => '(this week)',
            self::RANGE_30D => '(last 30 days)',
            self::RANGE_90D => '(last 90 days)',
            default => '(this period)',
        };
    }

    private function formatEngagementRatePercent(float $pct): string
    {
        if (! is_finite($pct)) {
            return '0%';
        }
        if (abs($pct - round($pct)) < 0.05) {
            return ((int) round($pct)).'%';
        }

        return rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.').'%';
    }

    private function formatInteractionsPerPost(float $value): string
    {
        if (! is_finite($value)) {
            return '0 / post';
        }
        if (abs($value - round($value)) < 0.05) {
            return ((int) round($value)).' / post';
        }

        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.').' / post';
    }

    /**
     * Daily buckets in the user timezone for the same window as published post metrics.
     *
     * @return array{labels: list<string>, labelsIso: list<string>, series: list<array{id: string, label: string, values: list<int>}>}
     */
    private function buildActivityChartSeries(
        User $user,
        string $range,
        bool $emptyPlatformScope,
        ?string $platformSlug,
        Carbon $now,
        string $tz
    ): array {
        [$pubStart, $pubEnd] = $this->publishedWindow($range, $now);

        $keys = [];
        $cursor = $pubStart->copy()->startOfDay();
        $lastDay = $pubEnd->copy()->startOfDay();
        while ($cursor->lte($lastDay)) {
            $keys[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }
        if ($keys === []) {
            $keys[] = $pubStart->format('Y-m-d');
        }

        $labels = [];
        $labelsIso = [];
        foreach ($keys as $ymd) {
            $labelsIso[] = $ymd;
            $labels[] = Carbon::createFromFormat('Y-m-d', $ymd, $tz)->format('M j');
        }

        $postsByDay = array_fill_keys($keys, 0);
        $impressionsByDay = array_fill_keys($keys, 0);
        $engagementByDay = array_fill_keys($keys, 0);

        $startUtc = $pubStart->copy()->utc();
        $endUtc = $pubEnd->copy()->utc();

        if (! $emptyPlatformScope) {
            $postRows = $user->posts()
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '>=', $startUtc)
                ->where('published_at', '<=', $endUtc)
                ->when($platformSlug !== null, function (Builder $q) use ($platformSlug): void {
                    $q->whereHas('postPlatforms', fn (Builder $pp) => $pp->where('platform', $platformSlug));
                })
                ->get(['published_at']);

            foreach ($postRows as $post) {
                $k = $post->published_at->timezone($tz)->format('Y-m-d');
                if (isset($postsByDay[$k])) {
                    $postsByDay[$k]++;
                }
            }

            $metricQuery = DB::table('post_platforms')
                ->join('posts', 'posts.id', '=', 'post_platforms.post_id')
                ->where('posts.user_id', $user->id)
                ->where('posts.status', 'published')
                ->whereNotNull('posts.published_at')
                ->where('posts.published_at', '>=', $startUtc)
                ->where('posts.published_at', '<=', $endUtc)
                ->when($platformSlug !== null, function ($q) use ($platformSlug): void {
                    $q->where('post_platforms.platform', $platformSlug);
                })
                ->select([
                    'posts.published_at',
                    'post_platforms.impressions_count',
                    'post_platforms.likes_count',
                    'post_platforms.comments_count',
                    'post_platforms.reposts_count',
                ]);

            foreach ($metricQuery->get() as $row) {
                $k = Carbon::parse((string) $row->published_at, 'UTC')->timezone($tz)->format('Y-m-d');
                if (! isset($impressionsByDay[$k])) {
                    continue;
                }
                $impressionsByDay[$k] += max(0, (int) ($row->impressions_count ?? 0));
                $engagementByDay[$k] += max(0, (int) ($row->likes_count ?? 0))
                    + max(0, (int) ($row->comments_count ?? 0))
                    + max(0, (int) ($row->reposts_count ?? 0));
            }
        }

        $series = [
            [
                'id'     => 'posts',
                'label'  => 'Posts published',
                'values' => array_values(array_map(static fn (string $k) => $postsByDay[$k], $keys)),
            ],
            [
                'id'     => 'impressions',
                'label'  => 'Impressions',
                'values' => array_values(array_map(static fn (string $k) => $impressionsByDay[$k], $keys)),
            ],
            [
                'id'     => 'engagement',
                'label'  => 'Engagement',
                'values' => array_values(array_map(static fn (string $k) => $engagementByDay[$k], $keys)),
            ],
        ];

        return [
            'labels'    => $labels,
            'labelsIso' => $labelsIso,
            'series'    => $series,
        ];
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

    /** Seconds. Per-account follower counts used for “Total audience” on the dashboard. */
    private const AUDIENCE_CACHE_TTL = 3600;

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
        $metadata = is_array($account->metadata) ? $account->metadata : [];
        $accountType = strtolower(trim((string) ($metadata['account_type'] ?? 'page')));
        if ($accountType !== 'page') {
            return $this->metadataAudience($account);
        }

        $pageId = trim((string) $account->platform_user_id);
        if ($pageId === '') {
            return $this->metadataAudience($account);
        }

        $pageToken = trim((string) ($metadata['page_access_token'] ?? ''));
        if ($pageToken === '') {
            $pageToken = (string) $account->access_token;
        }

        $resp = Http::withToken($pageToken)
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
        $metadata = is_array($account->metadata) ? $account->metadata : [];
        $accountType = strtolower(trim((string) ($metadata['account_type'] ?? 'person')));
        $authorUrn = trim((string) ($metadata['author_urn'] ?? ''));
        if ($authorUrn === '') {
            $id = trim((string) $account->platform_user_id);
            $authorUrn = $accountType === 'organization'
                ? "urn:li:organization:{$id}"
                : "urn:li:person:{$id}";
        }

        if ($accountType === 'organization') {
            $resp = Http::withToken($account->access_token)
                ->timeout(12)
                ->acceptJson()
                ->withHeaders($this->linkedInHeaders())
                ->get('https://api.linkedin.com/v2/networkSizes/' . rawurlencode($authorUrn), [
                    'edgeType' => 'CompanyFollowedByMember',
                ]);

            if ($resp->successful()) {
                $count = $resp->json('firstDegreeSize');
                if (is_numeric($count)) {
                    return (int) $count;
                }
            }
        }

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
     * Platforms available in the dashboard filter: keys present in config/platforms.php and defined on {@see Platform}.
     *
     * @return list<string>
     */
    private function dashboardPlatformFilterSlugs(): array
    {
        $cfg = config('platforms', []);
        $slugs = [];
        if (is_array($cfg)) {
            foreach (Platform::cases() as $case) {
                if (array_key_exists($case->value, $cfg)) {
                    $slugs[] = $case->value;
                }
            }
        }
        if ($slugs === []) {
            foreach (Platform::cases() as $case) {
                $slugs[] = $case->value;
            }
        }
        sort($slugs);

        return $slugs;
    }

    /**
     * @param  list<string>  $filterSlugs
     * @return list<array{slug:string,label:string}>
     */
    private function dashboardPlatformFilterOptions(array $filterSlugs): array
    {
        $out = [];
        foreach ($filterSlugs as $slug) {
            $enum = Platform::tryFrom($slug);
            $out[] = [
                'slug'  => $slug,
                'label' => $enum?->label() ?? ucfirst(str_replace('_', ' ', $slug)),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $connectedSlugs  Platforms the user has active accounts for
     * @param  list<string>  $filterSlugs     Valid dashboard filter values
     */
    private function resolvePlatformSlug(string $requested, array $connectedSlugs, array $filterSlugs): ?string
    {
        if ($filterSlugs === []) {
            return null;
        }

        if ($requested !== '' && in_array($requested, $filterSlugs, true)) {
            return $requested;
        }

        foreach ($connectedSlugs as $c) {
            if (in_array($c, $filterSlugs, true)) {
                return $c;
            }
        }

        return $filterSlugs[0];
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
