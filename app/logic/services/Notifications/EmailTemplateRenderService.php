<?php

namespace App\Services\Notifications;

use App\Models\EmailTemplate;
use App\Models\NotificationChannelSetting;
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
            'ticketId'        => '42',
            'ticketSubject'   => 'Sample support request',
            'ticketUrl'       => url('/support-tickets/42'),
            'responderName'   => 'Support',
        ];
    }

    /**
     * @param  array<string, mixed>  $vars  Caller-supplied variables merged with defaults
     * @return array{subject: string, html: string, text: ?string}
     */
    public function renderTemplate(EmailTemplate $template, array $vars): array
    {
        $master = NotificationChannelSetting::current();

        $subject = $this->sanitizeSubject(
            $this->placeholders->render($template->subject, $vars, [])
        );

        $bodyHtml = $this->placeholders->render($template->body_html, $vars, []);

        $masterHtml = $master->master_template_html
            ? $this->placeholders->render($master->master_template_html, array_merge($vars, [
                'bodyHtml' => $bodyHtml,
            ]), ['bodyHtml'])
            : $bodyHtml;

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

    private function sanitizeSubject(string $subject): string
    {
        $s = str_replace(["\r", "\n"], ' ', $subject);

        return trim($s);
    }
}
