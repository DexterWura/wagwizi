<?php

namespace App\Controllers;

use App\Services\Notifications\EmailTemplateRenderService;
use App\Services\Notifications\EmailTemplateService;
use App\Services\Notifications\NotificationChannelConfigService;
use App\Services\Notifications\InAppNotificationService;
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
            'email_send_method' => 'required|in:smtp,SMTP',
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_encryption' => 'required|in:tls,TLS,ssl,SSL',
            'smtp_username' => 'required|string|max:255',
            'smtp_password' => 'nullable|string|max:500',
        ]);

        $validated['email_send_method'] = strtolower(trim((string) ($validated['email_send_method'] ?? 'smtp')));
        $validated['smtp_encryption'] = strtolower(trim((string) ($validated['smtp_encryption'] ?? '')));
        $validated['smtp_host'] = trim((string) ($validated['smtp_host'] ?? ''));
        $validated['smtp_username'] = trim((string) ($validated['smtp_username'] ?? ''));
        $config->updateFromAdminRequest($validated, true);

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

            try {
                app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                    'admin_critical_smtp_test',
                    'SMTP test failed',
                    mb_substr($e->getMessage(), 0, 400),
                    route('admin.notifications.settings'),
                    [],
                    'smtp_test_fail:' . md5($e->getMessage()),
                    1800,
                );
            } catch (\Throwable) {
            }

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
