<?php

namespace App\Services\Notifications;

use App\Models\EmailTemplate;
use App\Models\SiteSetting;
use App\Models\User;

class EmailTemplateRenderService
{
    public function __construct(
        private readonly SafeEmailPlaceholderRenderer $placeholders,
    ) {}
    public function siteName(): string
    {
        return (string) SiteSetting::get('app_name', config('app.name'));
    }

    /**
     * @return array{siteName: string, userName: string, unsubscribeUrl: string}
     */
    public function baseVarsForUser(User $user): array
    {
        return [
            'siteName'       => $this->siteName(),
            'userName'       => $user->name,
            'unsubscribeUrl' => url('/'),
        ];
    }

    /**
     * Fixed sample data for admin preview (no user input executed as PHP).
     *
     * @return array<string, string>
     */
    public function samplePreviewVars(): array
    {
        return [
            'siteName'        => $this->siteName(),
            'userName'        => 'Sample User',
            'unsubscribeUrl'  => url('/'),
            'resetUrl'        => url('/reset-password'),
            'ticketId'        => '42',
            'ticketSubject'   => 'Sample support request',
            'ticketUrl'       => url('/support-tickets/42'),
            'responderName'   => 'Support',
            'newUserName'     => 'Jane Doe',
            'newUserEmail'    => 'jane@example.com',
            'adminUsersUrl'   => url('/admin/users'),
            'subscriberName'  => 'John Subscriber',
            'subscriberEmail' => 'john@example.com',
            'planName'        => 'Pro',
            'subscriptionsUrl'=> url('/admin/subscriptions'),
            'sentAt'          => now()->toDateTimeString(),
            'otpCode'         => '123456',
            'otpExpiresMinutes' => '10',
        ];
    }

    /**
     * @param  array<string, mixed>  $vars  Caller-supplied variables merged with defaults
     * @return array{subject: string, html: string, text: ?string}
     */
    public function renderTemplate(EmailTemplate $template, array $vars): array
    {
        $subject = $this->sanitizeSubject(
            $this->placeholders->render($template->subject, $vars, [])
        );

        $bodyHtml = $this->placeholders->render($template->body_html, $vars, []);

        $masterHtml = $this->defaultWrapper($bodyHtml, (string) ($vars['siteName'] ?? $this->siteName()));

        $text = null;
        if ($template->body_text) {
            $text = $this->placeholders->render($template->body_text, $vars, []);
        }

        return [
            'subject' => $subject,
            'html'    => $masterHtml,
            'text'    => $text,
        ];
    }

    private function defaultWrapper(string $bodyHtml, string $siteName): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'
            . e($siteName)
            . '</title></head><body style="font-family:system-ui,sans-serif;line-height:1.5;"><div style="max-width:600px;margin:0 auto;padding:24px;">'
            . $bodyHtml
            . '<hr style="border:none;border-top:1px solid #eee;margin:24px 0;"><p style="font-size:12px;color:#666;">'
            . e($siteName)
            . '</p></div></body></html>';
    }

    private function sanitizeSubject(string $subject): string
    {
        $s = str_replace(["\r", "\n"], ' ', $subject);

        return trim($s);
    }
}
