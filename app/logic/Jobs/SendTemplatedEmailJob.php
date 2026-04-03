<?php

namespace App\Jobs;

use App\Models\EmailTemplate;
use App\Models\NotificationDelivery;
use App\Services\Notifications\EmailTemplateRenderService;
use App\Services\Notifications\NotificationChannelConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendTemplatedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private readonly int $notificationDeliveryId,
    ) {}

    public function handle(
        EmailTemplateRenderService $renderService,
        NotificationChannelConfigService $mailConfig,
    ): void {
        $delivery = NotificationDelivery::query()->find($this->notificationDeliveryId);

        if ($delivery === null) {
            return;
        }

        if ($delivery->status !== 'queued') {
            return;
        }

        if ($delivery->channel !== 'email' || $delivery->to_address === null || $delivery->to_address === '') {
            $delivery->update([
                'status'        => 'failed',
                'error_message' => 'Missing email recipient.',
                'sent_at'       => null,
            ]);

            return;
        }

        $templateKey = $delivery->template_key;
        if ($templateKey === null || $templateKey === '') {
            $delivery->update([
                'status'        => 'failed',
                'error_message' => 'Missing template key.',
            ]);

            return;
        }

        $template = EmailTemplate::query()->where('key', $templateKey)->first();

        if ($template === null) {
            $delivery->update([
                'status'        => 'failed',
                'error_message' => "Unknown template key: {$templateKey}",
            ]);

            return;
        }

        $vars = $delivery->metadata['vars'] ?? [];

        try {
            $rendered = $renderService->renderTemplate($template, $vars);
            $mailConfig->sendHtml($delivery->to_address, $rendered['subject'], $rendered['html'], $rendered['text']);

            $delivery->update([
                'status'   => 'sent',
                'sent_at'  => now(),
                'metadata' => array_merge($delivery->metadata ?? [], [
                    'rendered_subject_preview' => mb_substr($rendered['subject'], 0, 120),
                ]),
            ]);
        } catch (Throwable $e) {
            $delivery->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
