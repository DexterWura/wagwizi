<?php

namespace App\Jobs;

use App\Models\MarketingCampaign;
use App\Services\Marketing\AudienceQueryService;
use App\Services\Marketing\MarketingMessageSendService;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendMarketingCampaignBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        private readonly int $marketingCampaignId,
    ) {}

    public function handle(
        AudienceQueryService $audience,
        MarketingMessageSendService $marketing,
    ): void {
        $campaign = MarketingCampaign::query()->with('emailTemplate')->find($this->marketingCampaignId);

        if ($campaign === null) {
            return;
        }

        if (! in_array($campaign->status, ['draft', 'scheduled', 'sending'], true)) {
            return;
        }

        $templateKey = $campaign->template_key ?? $campaign->emailTemplate?->key;

        if ($templateKey === null || $templateKey === '') {
            $campaign->update(['status' => 'cancelled']);
            try {
                app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                    'admin_critical_marketing_campaign',
                    'Marketing campaign blocked',
                    'Campaign "' . ($campaign->name ?? 'unknown') . '" has no email template key; sending was cancelled.',
                    route('admin.marketing-campaigns.index'),
                    ['campaign_id' => $campaign->id],
                    'marketing_campaign_no_template:' . $campaign->id,
                    86_400,
                );
            } catch (Throwable) {
            }

            return;
        }

        $campaign->update(['status' => 'sending']);

        try {
            $base = $audience->queryUsers($campaign->segment_rules ?? []);
            $base->where('marketing_email_opt_in', true);

            $base->chunkById(500, function ($users) use ($campaign, $marketing, $templateKey): void {
                foreach ($users as $user) {
                    $marketing->queueMarketingEmailToUser($user, $templateKey, [
                        'campaignName' => $campaign->name,
                    ], $campaign->id);
                }
            });

            $campaign->update(['status' => 'completed']);
        } catch (Throwable $e) {
            $campaign->update(['status' => 'cancelled']);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                'admin_critical_marketing_campaign',
                'Marketing campaign aborted',
                'Campaign #' . $this->marketingCampaignId . ' failed: ' . mb_substr($exception->getMessage(), 0, 400),
                route('admin.marketing-campaigns.index'),
                ['campaign_id' => $this->marketingCampaignId],
                'marketing_campaign_abort:' . $this->marketingCampaignId,
                3600,
            );
        } catch (Throwable) {
        }
    }
}
