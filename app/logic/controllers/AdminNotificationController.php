<?php

namespace App\Controllers;

use App\Services\Notifications\EmailTemplateRenderService;
use App\Services\Notifications\EmailTemplateService;
use App\Services\Notifications\NotificationChannelConfigService;
use App\Services\Notifications\NotificationDeliveryLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminNotificationController extends Controller
{
    public function notificationSettings(NotificationChannelConfigService $config): View
    {
        $settings = $config->getSettingsForAdminForm();

        return view('admin.notification-settings', [
            'activePage' => 'admin-notification-settings',
            'settings'   => $settings,
        ]);
    }

    public function updateNotificationSettings(Request $request, NotificationChannelConfigService $config): RedirectResponse
    {
        $validated = $request->validate([
            'driver'            => 'required|in:smtp,SMTP,sendmail,SENDMAIL,log,LOG',
            'from_name'         => 'nullable|string|max:255',
            'from_address'      => 'nullable|email|max:255',
            'smtp_host'         => 'nullable|string|max:255',
            'smtp_port'         => 'nullable|integer|min:1|max:65535',
            'smtp_encryption'   => 'nullable|in:tls,TLS,ssl,SSL',
            'smtp_username'     => 'nullable|string|max:255',
            'smtp_password'     => 'nullable|string|max:500',
            'smtp_timeout'      => 'nullable|integer|min:1|max:300',
            'reply_to'          => 'nullable|email|max:255',
            'sms_provider'      => 'required|in:none,twilio,vonage',
            'twilio_account_sid' => 'nullable|string|max:255',
            'twilio_auth_token' => 'nullable|string|max:500',
            'master_template_html' => 'nullable|string',
        ]);

        $smsCredentials = [];
        $validated['driver'] = strtolower(trim((string) ($validated['driver'] ?? 'log')));
        $validated['smtp_encryption'] = strtolower(trim((string) ($validated['smtp_encryption'] ?? '')));
        if ($validated['smtp_encryption'] === '') {
            $validated['smtp_encryption'] = null;
        }
        $validated['smtp_host'] = trim((string) ($validated['smtp_host'] ?? ''));
        $validated['smtp_username'] = trim((string) ($validated['smtp_username'] ?? ''));

        if (($validated['sms_provider'] ?? '') === 'twilio') {
            $existing = $config->getSettings()->sms_credentials ?? [];
            $sid      = $request->filled('twilio_account_sid')
                ? $request->string('twilio_account_sid')->toString()
                : (string) ($existing['account_sid'] ?? '');
            $token = $existing['auth_token'] ?? '';
            if ($request->filled('twilio_auth_token')) {
                $token = $request->string('twilio_auth_token')->toString();
            }
            $smsCredentials = [
                'account_sid' => $sid,
                'auth_token'  => $token,
            ];
        }

        $payload = $validated;
        $payload['smtp_encryption'] = $validated['smtp_encryption'] ?? null;
        unset($payload['twilio_account_sid'], $payload['twilio_auth_token']);
        $payload['sms_credentials'] = $smsCredentials;

        $config->updateFromAdminRequest($payload, true);

        return redirect()->route('admin.notifications.settings')->with('success', 'Notification settings saved.');
    }

    public function sendTestEmail(NotificationChannelConfigService $mailConfig): RedirectResponse
    {
        $user = Auth::user();
        if ($user === null || $user->email === null || $user->email === '') {
            return redirect()->route('admin.notifications.settings')->with('error', 'No email on your account.');
        }

        try {
            // Direct SMTP probe for admin UX: immediate success/failure feedback.
            $html = view('emails.smtp-test', [
                'user' => $user,
                'sentAt' => now(),
            ])->render();

            $mailConfig->sendHtml(
                $user->email,
                config('app.name') . ' SMTP test email',
                $html,
                'SMTP test email sent at ' . now()->toDateTimeString()
            );
        } catch (\Throwable $e) {
            Log::warning('Admin test email failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.notifications.settings')
                ->with('error', 'Test email failed. Please verify SMTP host, port, encryption, and credentials.');
        }

        return redirect()->route('admin.notifications.settings')->with('success', 'Test email sent successfully to your address.');
    }

    public function emailTemplates(EmailTemplateService $templates): View
    {
        $list = $templates->listAllOrderedByKey();

        return view('admin.email-templates', [
            'activePage' => 'admin-email-templates',
            'templates'  => $list,
        ]);
    }

    public function editEmailTemplate(int $id, EmailTemplateService $templates): View
    {
        $template = $templates->findOrFail($id);

        return view('admin.email-template-edit', [
            'activePage' => 'admin-email-templates',
            'template'   => $template,
        ]);
    }

    public function updateEmailTemplate(Request $request, int $id, EmailTemplateService $templates): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'subject'     => 'required|string|max:500',
            'body_html'   => 'required|string',
            'body_text'   => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $templates->update($id, $validated);

        return redirect()->route('admin.email-templates.edit', $id)->with('success', 'Template saved.');
    }

    public function previewEmailTemplate(int $id, EmailTemplateService $templates, EmailTemplateRenderService $render): Response
    {
        $template = $templates->findOrFail($id);
        $vars     = $render->samplePreviewVars();

        $out = $render->renderTemplate($template, $vars);

        return response()->view('admin.email-template-preview', [
            'subject' => $out['subject'],
            'html'    => $out['html'],
        ]);
    }

    public function notificationDeliveries(Request $request, NotificationDeliveryLogService $logService): View
    {
        $deliveries = $logService->paginateFiltered($request, 25);

        return view('admin.notification-deliveries', [
            'activePage' => 'admin-notification-deliveries',
            'deliveries' => $deliveries,
            'filters'    => $request->only(['channel', 'template_key', 'status', 'date_from', 'date_to', 'user_search']),
        ]);
    }
}
