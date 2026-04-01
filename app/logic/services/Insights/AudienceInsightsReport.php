<?php

namespace App\Services\Insights;

class AudienceInsightsReport
{
    public function __construct(
        public readonly bool $hasEngagementMetrics,
        public readonly array $hourlyScores,
        public readonly array $weekdayScores,
        public readonly ?int $bestHour,
        public readonly ?int $bestWeekday,
        public readonly array $topHourSlots,
        public readonly array $topPosts,
        public readonly ?array $leadingPlatform,
        public readonly array $platformMix,
        public readonly float $blendedEngagementRateEstimate,
        public readonly array $schedulingSuggestions,
        public readonly string $composerSummary,
        public readonly int $sampleSize,
    ) {}
}
