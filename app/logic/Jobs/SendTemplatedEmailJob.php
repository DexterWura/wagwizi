<?php

namespace App\Jobs;

use App\Models\EmailTemplate;
use App\Models\NotificationDelivery;
use App\Services\Notifications\EmailTemplateRenderService;
use App\Services\Notifications\InAppNotificationService;
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
            $this->failDeliveryConfiguration($delivery, 'Missing email recipient.');

            return;
        }

        $templateKey = $delivery->template_key;
        if ($templateKey === null || $templateKey === '') {
            $this->failDeliveryConfiguration($delivery, 'Missing template key.');

            return;
        }

        $template = EmailTemplate::query()->where('key', $templateKey)->first();

        if ($template === null) {
            $this->failDeliveryConfiguration($delivery, "Unknown template key: {$templateKey}");

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

    public function failed(Throwable $exception): void
    {
        try {
            $delivery = NotificationDelivery::query()->find($this->notificationDeliveryId);
            $detail = $delivery !== null
                ? "Delivery #{$delivery->id}, template " . ($delivery->template_key ?? '?') . ', to ' . ($delivery->to_address ?? '?')
                : "Delivery #{$this->notificationDeliveryId} (record missing)";
            app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                'admin_critical_email_job_failed',
                'Email job failed',
                $detail . '. ' . mb_substr($exception->getMessage(), 0, 400),
                route('admin.notification-deliveries'),
                ['delivery_id' => $this->notificationDeliveryId],
                'email_job_exhausted:' . $this->notificationDeliveryId,
                7200,
            );
        } catch (Throwable) {
        }
    }

    private function failDeliveryConfiguration(NotificationDelivery $delivery, string $errorMessage): void
    {
        $delivery->update([
            'status'        => 'failed',
            'error_message' => $errorMessage,
            'sent_at'       => null,
        ]);

        try {
            app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                'admin_critical_email_delivery',
                'Transactional email misconfigured',
                $errorMessage . ($delivery->template_key ? ' (template: ' . $delivery->template_key . ')' : ''),
                route('admin.notification-deliveries'),
                ['delivery_id' => $delivery->id],
                'email_delivery_cfg:' . $delivery->id . ':' . md5($errorMessage),
                3600,
            );
        } catch (Throwable) {
        }
    }
}
