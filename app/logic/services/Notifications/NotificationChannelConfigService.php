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
            'driver'               => $s->driver,
            'from_name'            => $s->from_name,
            'from_address'         => $s->from_address,
            'smtp_host'            => $s->smtp_host,
            'smtp_port'            => $s->smtp_port,
            'smtp_encryption'      => $s->smtp_encryption,
            'smtp_username'        => $s->smtp_username,
            'smtp_password_masked' => $s->smtp_password ? '********' : '',
            'smtp_timeout'         => $s->smtp_timeout,
            'reply_to'             => $s->reply_to,
            'sms_provider'         => $s->sms_provider,
            'sms_credentials_set'  => ! empty($s->sms_credentials),
            'twilio_account_sid'   => $s->sms_credentials['account_sid'] ?? '',
            'twilio_auth_token_masked' => ! empty($s->sms_credentials['auth_token'] ?? null) ? '********' : '',
            'master_template_html' => $s->master_template_html,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateFromAdminRequest(array $input, bool $smtpPasswordBlankMeansKeep): void
    {
        $s = $this->getSettings();

        $s->driver = $input['driver'] ?? $s->driver;
        $s->from_name = $input['from_name'] ?? null;
        $s->from_address = $input['from_address'] ?? null;
        $s->smtp_host = $input['smtp_host'] ?? null;
        $s->smtp_port = isset($input['smtp_port']) ? (int) $input['smtp_port'] : null;
        $s->smtp_encryption = $input['smtp_encryption'] ?? null;
        $s->smtp_username = $input['smtp_username'] ?? null;
        $s->smtp_timeout = isset($input['smtp_timeout']) ? (int) $input['smtp_timeout'] : null;
        $s->reply_to = $input['reply_to'] ?? null;
        $s->sms_provider = $input['sms_provider'] ?? 'none';

        if (! $smtpPasswordBlankMeansKeep || ! empty($input['smtp_password'])) {
            $s->smtp_password = $input['smtp_password'] ?? null;
        }

        if (array_key_exists('sms_credentials', $input) && is_array($input['sms_credentials'])) {
            $s->sms_credentials = $input['sms_credentials'] !== [] ? $input['sms_credentials'] : null;
        }

        if (array_key_exists('master_template_html', $input)) {
            $s->master_template_html = $input['master_template_html'];
        }

        if (($s->sms_provider ?? '') === 'none') {
            $s->sms_credentials = null;
        }

        $s->save();
    }

    public function applyDynamicMailerConfig(): void
    {
        $settings = $this->getSettings();
        $name = self::DYNAMIC_MAILER;

        $mailer = match ($settings->driver) {
            'smtp' => $this->buildSmtpMailerConfig($settings),
            'sendmail' => [
                'transport' => 'sendmail',
                'path'      => config('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs -i'),
            ],
            'log' => [
                'transport' => 'log',
                'channel'     => env('MAIL_LOG_CHANNEL'),
            ],
            default => [
                'transport' => 'log',
                'channel'     => env('MAIL_LOG_CHANNEL'),
            ],
        };

        Config::set("mail.mailers.{$name}", $mailer);

        $fromAddress = $settings->from_address ?: (config('mail.from.address') ?: 'hello@example.com');
        $fromName    = $settings->from_name ?: (config('mail.from.name') ?: config('app.name'));

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
            if ($settings->reply_to) {
                $message->replyTo($settings->reply_to);
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
            'timeout'      => $settings->smtp_timeout,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST)),
        ];
    }
}
