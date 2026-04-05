<?php

namespace App\Services\Notifications;

use App\Jobs\SendTemplatedEmailJob;
use App\Models\NotificationDelivery;
use App\Models\User;

class SystemMessageSendService
{
    public function __construct(
        private readonly EmailTemplateRenderService $renderService,
    ) {}

    /**
     * Queue a transactional email. Does not check marketing opt-in.
     *
     * @param  array<string, mixed>  $vars  Merged with base user vars (siteName, userName, unsubscribeUrl)
     * @param  array<string, mixed>  $metadataExtra  Merged into delivery metadata (e.g. campaign_id)
     */
    public function queueEmailToUser(User $user, string $templateKey, array $vars = [], array $metadataExtra = []): ?NotificationDelivery
    {
        if ($user->email === null || $user->email === '') {
            return null;
        }

        $merged = array_merge($this->renderService->baseVarsForUser($user), $vars);

        $metadata = array_merge(['vars' => $merged], $metadataExtra);

        $delivery = NotificationDelivery::query()->create([
            'channel'      => 'email',
            'template_key' => $templateKey,
            'user_id'      => $user->id,
            'to_address'   => $user->email,
            'status'       => 'queued',
            'metadata'     => $metadata,
        ]);

        // Reliability-first: for HTTP flows, run right after the response even without a queue worker.
        // For CLI/queue contexts, keep normal queued dispatch.
        if (app()->runningInConsole()) {
            SendTemplatedEmailJob::dispatchSync($delivery->id);
        } else {
            SendTemplatedEmailJob::dispatchAfterResponse($delivery->id);
        }

        return $delivery;
    }
}
