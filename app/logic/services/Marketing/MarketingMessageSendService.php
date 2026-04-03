<?php

namespace App\Services\Marketing;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Notifications\SystemMessageSendService;

final class MarketingMessageSendService
{
    public function __construct(
        private readonly SystemMessageSendService $systemMessageSend,
    ) {}

    /**
     * Queues a marketing email only if the user opted in.
     *
     * @param  array<string, mixed>  $vars
     */
    public function queueMarketingEmailToUser(User $user, string $templateKey, array $vars, int $campaignId): ?NotificationDelivery
    {
        if (! $user->marketing_email_opt_in) {
            return null;
        }

        return $this->systemMessageSend->queueEmailToUser($user, $templateKey, $vars, [
            'campaign_id' => $campaignId,
            'kind'        => 'marketing',
        ]);
    }
}
