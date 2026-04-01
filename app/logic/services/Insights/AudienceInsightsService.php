<?php

namespace App\Services\Insights;

use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\User;
use App\Services\Platform\Platform;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class AudienceInsightsService
{
    private const REPOST_WEIGHT = 2.0;

    private const COMMENT_WEIGHT = 1.5;

    private const LIKE_WEIGHT = 1.0;

    private const IMPRESSION_WEIGHT = 0.01;

    public function buildForUser(User $user, ?Carbon $from = null, ?Carbon $to = null): AudienceInsightsReport
    {
        $tz = $this->resolveTimezone($user);
        $from = $from?->copy()->timezone($tz) ?? Carbon::now($tz)->subDays(90);
        $to = $to?->copy()->timezone($tz)->endOfDay() ?? Carbon::now($tz)->endOfDay();

        $rows = $this->loadPublishedRows($user->id, $from, $to);

        if ($rows->isEmpty()) {
            return $this->emptyReport();
        }

        $hasMetrics = $rows->contains(fn (PostPlatform $pp) => $this->rowHasMetrics($pp));

        $hourlyRaw = array_fill(0, 24, 0.0);
        $weekdayRaw = array_fill(0, 7, 0.0);
        $postScores = [];
        $platformTotals = [];
        $platformCounts = [];
        $totalWeighted = 0.0;
        $totalImpliedReach = 0.0;

        foreach ($rows as $pp) {
            $at = $this->effectivePublishedAt($pp, $tz);
            if ($at === null) {
                continue;
            }

            $w = $this->rowWeight($pp, $hasMetrics);
            $hour = (int) $at->format('G');
            $dow = (int) $at->dayOfWeekIso - 1;

            $hourlyRaw[$hour] += $w;
            $weekdayRaw[$dow] += $w;

            $pid = $pp->post_id;
            if (!isset($postScores[$pid])) {
                $postScores[$pid] = ['weight' => 0.0, 'post' => $pp->post, 'platforms' => []];
            }
            $postScores[$pid]['weight'] += $w;
            $postScores[$pid]['platforms'][$pp->platform] = true;

            $platformTotals[$pp->platform] = ($platformTotals[$pp->platform] ?? 0) + $w;
            $platformCounts[$pp->platform] = ($platformCounts[$pp->platform] ?? 0) + 1;

            $totalWeighted += $w;
            $totalImpliedReach += $this->impliedReach($pp);
        }

        $hourlyNorm = $this->normalizeToPercentBars($hourlyRaw);
        $weekdayNorm = $this->normalizeToPercentBars($weekdayRaw);

        $bestHour = $this->argMax($hourlyRaw);
        $bestWeekday = $this->argMax($weekdayRaw);

        $topHourSlots = $this->topSlots($hourlyRaw, $tz, 3);
        uasort($postScores, fn ($a, $b) => $b['weight'] <=> $a['weight']);
        $topPosts = $this->formatTopPosts(array_slice($postScores, 0, 5, true), $hasMetrics);

        $leading = $this->pickLeadingPlatform($platformTotals, $platformCounts);
        $platformMix = $this->platformMixPercents($platformTotals);

        $engagementRate = 0.0;
        if ($hasMetrics && $totalImpliedReach > 0) {
            $rawInteractions = $rows->sum(function (PostPlatform $pp) {
                return (int) ($pp->likes_count ?? 0)
                    + (int) ($pp->reposts_count ?? 0)
                    + (int) ($pp->comments_count ?? 0);
            });
            $engagementRate = min(99.9, round(100 * $rawInteractions / $totalImpliedReach, 1));
        }

        $suggestions = $this->buildSuggestions(
            $tz,
            $topHourSlots,
            $leading,
            $hasMetrics,
            $bestWeekday
        );

        $composerSummary = $this->buildComposerSummary($topHourSlots, $leading, $hasMetrics);

        return new AudienceInsightsReport(
            hasEngagementMetrics: $hasMetrics,
            hourlyScores: $hourlyNorm,
            weekdayScores: $weekdayNorm,
            bestHour: $bestHour,
            bestWeekday: $bestWeekday,
            topHourSlots: $topHourSlots,
            topPosts: $topPosts,
            leadingPlatform: $leading,
            platformMix: $platformMix,
            blendedEngagementRateEstimate: $engagementRate,
            schedulingSuggestions: $suggestions,
            composerSummary: $composerSummary,
            sampleSize: $rows->count(),
        );
    }

    private function resolveTimezone(User $user): string
    {
        $name = $user->timezone;
        if (is_string($name) && $name !== '') {
            try {
                new \DateTimeZone($name);
                return $name;
            } catch (\Throwable) {
            }
        }

        return 'UTC';
    }

    /**
     * @return Collection<int, PostPlatform>
     */
    private function loadPublishedRows(int $userId, Carbon $from, Carbon $to): Collection
    {
        $tz = $from->getTimezone()->getName();

        return PostPlatform::query()
            ->where('status', 'published')
            ->whereHas('post', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->with(['post:id,user_id,content,status,published_at'])
            ->get()
            ->filter(function (PostPlatform $pp) use ($from, $to, $tz) {
                $at = $this->effectivePublishedAt($pp, $tz);
                if ($at === null) {
                    return false;
                }

                return $at->between($from, $to);
            })
            ->values();
    }

    private function effectivePublishedAt(PostPlatform $pp, string $tz): ?Carbon
    {
        $raw = $pp->published_at ?? $pp->post?->published_at;
        if ($raw === null) {
            return null;
        }

        return $raw->copy()->timezone($tz);
    }

    private function rowHasMetrics(PostPlatform $pp): bool
    {
        return $pp->likes_count !== null
            || $pp->reposts_count !== null
            || $pp->comments_count !== null
            || $pp->impressions_count !== null;
    }

    private function rowWeight(PostPlatform $pp, bool $hasAnyMetricsInDataset): float
    {
        if (!$hasAnyMetricsInDataset) {
            return 1.0;
        }

        $likes = (float) ($pp->likes_count ?? 0);
        $reposts = (float) ($pp->reposts_count ?? 0);
        $comments = (float) ($pp->comments_count ?? 0);
        $impr = (float) ($pp->impressions_count ?? 0);

        $score = self::LIKE_WEIGHT * $likes
            + self::REPOST_WEIGHT * $reposts
            + self::COMMENT_WEIGHT * $comments
            + self::IMPRESSION_WEIGHT * $impr;

        if ($score <= 0 && !$this->rowHasMetrics($pp)) {
            return 1.0;
        }

        return max(1.0, $score);
    }

    private function impliedReach(PostPlatform $pp): float
    {
        if ($pp->impressions_count !== null && $pp->impressions_count > 0) {
            return (float) $pp->impressions_count;
        }

        $likes = (float) ($pp->likes_count ?? 0);
        $reposts = (float) ($pp->reposts_count ?? 0);
        $comments = (float) ($pp->comments_count ?? 0);

        if ($likes + $reposts + $comments <= 0) {
            return 100.0;
        }

        return max(100.0, ($likes + $reposts + $comments) * 25);
    }

    /**
     * @param  array<int, float>  $values
     * @return array<int, int> 0–100 for UI bars
     */
    private function normalizeToPercentBars(array $values): array
    {
        $max = max($values) ?: 1.0;
        $out = [];
        foreach ($values as $i => $v) {
            $out[$i] = (int) round(100 * $v / $max);
        }

        return $out;
    }

    /**
     * @param  array<int, float>  $values
     */
    private function argMax(array $values): ?int
    {
        if (max($values) <= 0) {
            return null;
        }

        return array_keys($values, max($values), true)[0];
    }

    /**
     * @param  array<int, float>  $hourlyRaw
     * @return list<array{hour:int,label:string,score:float}>
     */
    private function topSlots(array $hourlyRaw, string $tz, int $n): array
    {
        $indexed = [];
        foreach ($hourlyRaw as $h => $score) {
            $indexed[] = ['hour' => $h, 'score' => $score];
        }
        usort($indexed, fn ($a, $b) => $b['score'] <=> $a['score']);

        $out = [];
        foreach (array_slice($indexed, 0, $n) as $item) {
            if ($item['score'] <= 0) {
                break;
            }
            $label = Carbon::createFromTime($item['hour'], 0, 0, $tz)->format('g:i A');
            $out[] = [
                'hour'  => $item['hour'],
                'label' => $label,
                'score' => $item['score'],
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array{weight:float,post:?Post,platforms:array}>  $postScores
     * @return list<array{id:int,excerpt:string,score:float,platforms:string,has_metrics:bool}>
     */
    private function formatTopPosts(array $postScores, bool $hasMetrics): array
    {
        $out = [];
        foreach ($postScores as $data) {
            /** @var Post|null $post */
            $post = $data['post'];
            if ($post === null) {
                continue;
            }
            $out[] = [
                'id'          => $post->id,
                'excerpt'     => Str::limit($post->content, 80),
                'score'       => round($data['weight'], 2),
                'platforms'   => implode(', ', array_keys($data['platforms'])),
                'has_metrics' => $hasMetrics,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $totals
     * @param  array<string, int>  $counts
     * @return array{slug:string,label:string,total_score:float,avg_score:float}|null
     */
    private function pickLeadingPlatform(array $totals, array $counts): ?array
    {
        if ($totals === []) {
            return null;
        }

        arsort($totals);
        $slug = array_key_first($totals);
        $enum = Platform::tryFrom($slug);
        $count = max(1, $counts[$slug] ?? 1);

        return [
            'slug'         => $slug,
            'label'        => $enum?->label() ?? ucfirst(str_replace('_', ' ', $slug)),
            'total_score'  => $totals[$slug],
            'avg_score'    => round($totals[$slug] / $count, 2),
        ];
    }

    /**
     * @param  array<string, float>  $totals
     * @return list<array{slug:string,label:string,pct:float}>
     */
    private function platformMixPercents(array $totals): array
    {
        $sum = array_sum($totals) ?: 1.0;
        $out = [];
        foreach ($totals as $slug => $v) {
            $enum = Platform::tryFrom($slug);
            $out[] = [
                'slug'  => $slug,
                'label' => $enum?->label() ?? $slug,
                'pct'   => round(100 * $v / $sum, 1),
            ];
        }
        usort($out, fn ($a, $b) => $b['pct'] <=> $a['pct']);

        return $out;
    }

    /**
     * @param  list<array{hour:int,label:string,score:float}>  $topHours
     * @param  array{slug:string,label:string,total_score:float,avg_score:float}|null  $leading
     * @return list<string>
     */
    private function buildSuggestions(
        string $tz,
        array $topHours,
        ?array $leading,
        bool $hasMetrics,
        ?int $bestWeekday
    ): array {
        $suggestions = [];
        $basis = $hasMetrics
            ? 'weighted by likes, reposts, and comments where available'
            : 'based on when your posts went live (add synced metrics for finer tuning)';

        if ($topHours !== []) {
            $labels = array_column($topHours, 'label');
            $suggestions[] = 'Peak activity windows: ' . implode(', ', $labels) . " ({$basis}).";
        }

        if ($bestWeekday !== null) {
            $name = Carbon::now($tz)->startOfWeek(Carbon::MONDAY)->addDays($bestWeekday)->format('l');
            $suggestions[] = "Strongest weekday signal: {$name} — consider stacking important posts there.";
        }

        if ($leading !== null) {
            $suggestions[] = "Highest relative performance on {$leading['label']}; prioritize that channel for high-impact updates.";
        }

        if ($suggestions === []) {
            $suggestions[] = 'Publish a few more posts to unlock personalized timing recommendations.';
        }

        return $suggestions;
    }

    /**
     * @param  list<array{hour:int,label:string,score:float}>  $topHours
     * @param  array{slug:string,label:string,total_score:float,avg_score:float}|null  $leading
     */
    private function buildComposerSummary(array $topHours, ?array $leading, bool $hasMetrics): string
    {
        $parts = [];
        if ($topHours !== []) {
            $parts[] = 'Suggested slots: ' . implode(', ', array_column($topHours, 'label')) . '.';
        }
        if ($leading !== null) {
            $parts[] = "Top channel lately: {$leading['label']}.";
        }
        if (!$hasMetrics) {
            $parts[] = 'Sync engagement metrics on published posts for smarter suggestions.';
        }

        return implode(' ', $parts) ?: 'Keep publishing — we will refine suggestions as data grows.';
    }

    private function emptyReport(): AudienceInsightsReport
    {
        return new AudienceInsightsReport(
            hasEngagementMetrics: false,
            hourlyScores: array_fill(0, 24, 0),
            weekdayScores: array_fill(0, 7, 0),
            bestHour: null,
            bestWeekday: null,
            topHourSlots: [],
            topPosts: [],
            leadingPlatform: null,
            platformMix: [],
            blendedEngagementRateEstimate: 0.0,
            schedulingSuggestions: ['No published posts in this range yet — schedule or publish to see audience timing insights.'],
            composerSummary: 'Publish posts to unlock smart scheduling hints.',
            sampleSize: 0,
        );
    }
}
