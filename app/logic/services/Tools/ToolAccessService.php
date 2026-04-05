<?php

declare(strict_types=1);

namespace App\Services\Tools;

use App\Models\SiteSetting;
use App\Models\User;

final class ToolAccessService
{
    /**
     * @return array<string, array{label: string, category: string}>
     */
    public function catalog(): array
    {
        return [
            'youtube_video_download' => ['label' => 'YouTube video download', 'category' => 'Downloads'],
            'linkedin_video_download' => ['label' => 'LinkedIn video download', 'category' => 'Downloads'],
            'twitter_video_download' => ['label' => 'Twitter/X video download', 'category' => 'Downloads'],
            'facebook_video_download' => ['label' => 'Facebook video download', 'category' => 'Downloads'],
            'instagram_reels_download' => ['label' => 'Instagram Reels download', 'category' => 'Downloads'],
            'tiktok_video_download' => ['label' => 'TikTok video download', 'category' => 'Downloads'],
            'vimeo_video_download' => ['label' => 'Vimeo video download', 'category' => 'Downloads'],
            'pinterest_media_download' => ['label' => 'Pinterest media download', 'category' => 'Downloads'],
            'reddit_media_download' => ['label' => 'Reddit media download', 'category' => 'Downloads'],
            'bulk_media_import' => ['label' => 'Bulk media import', 'category' => 'Productivity'],
            'canva_export_import' => ['label' => 'Canva export/import', 'category' => 'Integrations'],
            'ai_caption_generator' => ['label' => 'AI caption generator', 'category' => 'AI'],
        ];
    }

    /**
     * @return array{allowed: bool, code: string|null, message: string|null}
     */
    public function evaluateUserAccess(User $user, string $toolSlug): array
    {
        $toolSlug = trim(strtolower($toolSlug));
        $catalog = $this->catalog();
        if (! array_key_exists($toolSlug, $catalog)) {
            return [
                'allowed' => false,
                'code' => 'tool_unknown',
                'message' => 'This tool is not recognized by the system.',
            ];
        }

        if ($user->isSuperAdmin()) {
            return ['allowed' => true, 'code' => null, 'message' => null];
        }

        if (! in_array($toolSlug, $this->globallyEnabledToolSlugs(), true)) {
            return [
                'allowed' => false,
                'code' => 'tool_globally_disabled',
                'message' => 'This tool is currently disabled by the admin.',
            ];
        }

        $user->loadMissing('subscription.planModel');
        $subscription = $user->subscription;
        $plan = $subscription?->planModel;

        if ($subscription === null || $plan === null) {
            return [
                'allowed' => false,
                'code' => 'tool_no_plan',
                'message' => 'No plan is linked to your account. Choose a plan to use this tool.',
            ];
        }

        if (! ($subscription->isActive() || $subscription->isTrialing())) {
            return [
                'allowed' => false,
                'code' => 'tool_subscription_inactive',
                'message' => 'Your subscription is not active. Renew or change plan to use this tool.',
            ];
        }

        if (! $plan->allowsTool($toolSlug)) {
            return [
                'allowed' => false,
                'code' => 'tool_plan_restricted',
                'message' => 'Your current plan does not include this tool.',
            ];
        }

        return ['allowed' => true, 'code' => null, 'message' => null];
    }

    /**
     * @return array<int, string>
     */
    public function globallyEnabledToolSlugs(): array
    {
        $catalogSlugs = array_keys($this->catalog());
        $raw = SiteSetting::get('enabled_download_tools', null);
        if ($raw === null) {
            return $catalogSlugs;
        }

        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return $catalogSlugs;
        }

        return array_values(array_unique(array_filter(
            $decoded,
            static fn ($slug): bool => is_string($slug) && in_array($slug, $catalogSlugs, true),
        )));
    }
}

