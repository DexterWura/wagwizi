<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannelSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class NotificationChannelConfigService
{
    public const DYNAMIC_MAILER = 'db_notification';

    public function getSettings(): NotificationChannelSetting
    {
        return NotificationChannelSetting::current();
    }

    /**
     * @return array<string, mixed> Safe for Blade forms (passwords masked, SMS not exposed)
     */
    public function getSettingsForAdminForm(): array
    {
        $s = $this->getSettings();

        return [
            'email_send_method'    => $s->email_send_method,
            'smtp_host'            => $s->smtp_host,
            'smtp_port'            => $s->smtp_port,
            'smtp_encryption'      => $s->smtp_encryption,
            'smtp_username'        => $s->smtp_username,
            'smtp_password_masked' => $s->smtp_password ? '********' : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateFromAdminRequest(array $input, bool $smtpPasswordBlankMeansKeep): void
    {
        $s = $this->getSettings();

        $s->email_send_method = $input['email_send_method'] ?? 'smtp';
        $s->smtp_host = $input['smtp_host'] ?? null;
        $s->smtp_port = isset($input['smtp_port']) ? (int) $input['smtp_port'] : null;
        $s->smtp_encryption = $input['smtp_encryption'] ?? null;
        $s->smtp_username = $input['smtp_username'] ?? null;

        if (! $smtpPasswordBlankMeansKeep || ! empty($input['smtp_password'])) {
            $s->smtp_password = $input['smtp_password'] ?? null;
        }

        $s->save();
    }

    public function applyDynamicMailerConfig(): void
    {
        $settings = $this->getSettings();
        $name = self::DYNAMIC_MAILER;

        $mailer = match ($settings->email_send_method) {
            'smtp' => $this->buildSmtpMailerConfig($settings),
            default => [
                'transport' => 'smtp',
                'url' => null,
                'host' => $settings->smtp_host ?: '127.0.0.1',
                'port' => $settings->smtp_port ?: 587,
                'encryption' => $settings->smtp_encryption ?: null,
                'username' => $settings->smtp_username,
                'password' => $settings->smtp_password,
                'timeout' => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST)),
            ],
        };

        Config::set("mail.mailers.{$name}", $mailer);

        $smtpFrom = (string) ($settings->smtp_username ?? '');
        $fromAddress = filter_var($smtpFrom, FILTER_VALIDATE_EMAIL)
            ? $smtpFrom
            : (config('mail.from.address') ?: 'hello@example.com');
        $fromName    = config('mail.from.name') ?: config('app.name');

        Config::set('mail.from', [
            'address' => $fromAddress,
            'name'    => $fromName,
        ]);
    }

    public function sendHtml(string $to, string $subject, string $html, ?string $textPlain = null): void
    {
        $this->applyDynamicMailerConfig();
        $settings = $this->getSettings();

        $mailer = Mail::mailer(self::DYNAMIC_MAILER);
        // Rendered template content is raw HTML/text, not Blade view names.
        // Always use html() so Laravel does not attempt to resolve $textPlain as a view path.
        $mailer->html($html, function ($message) use ($to, $subject, $settings) {
            $message->to($to)->subject($subject);
            $smtpFrom = (string) ($settings->smtp_username ?? '');
            if ($smtpFrom !== '' && filter_var($smtpFrom, FILTER_VALIDATE_EMAIL)) {
                $message->from($smtpFrom, (string) config('app.name'));
            }
        });
    }

    private function buildSmtpMailerConfig(NotificationChannelSetting $settings): array
    {
        $host = $settings->smtp_host ?: '127.0.0.1';
        $port = $settings->smtp_port ?: 587;

        return [
            'transport'    => 'smtp',
            'url'          => null,
            'host'         => $host,
            'port'         => $port,
            'encryption'   => $settings->smtp_encryption ?: null,
            'username'     => $settings->smtp_username,
            'password'     => $settings->smtp_password,
            'timeout'      => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST)),
        ];
    }
}
