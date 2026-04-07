<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Jobs\QueueTemplatedEmailForUserJob;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class InAppNotificationService
{
    public function notifyUserPlanChangedByAdmin(User $user, Plan $newPlan): void
    {
        $this->create(
            $user->id,
            'user_plan_changed_admin',
            'Your plan was updated by admin',
            "An administrator changed your plan to {$newPlan->name}.",
            ['action_url' => route('plans')]
        );
    }

    public function notifyUserPlanGiftedByAdmin(User $user, Plan $newPlan): void
    {
        $this->create(
            $user->id,
            'user_plan_gifted_admin',
            'You have been gifted a plan',
            "An administrator gifted you the {$newPlan->name} plan.",
            ['action_url' => route('plans')]
        );
    }

    public function notifySuperAdminsNewUser(User $newUser): void
    {
        $url = route('admin.users', ['search' => $newUser->email]);
        foreach ($this->superAdminRecipientIds() as $adminId) {
            $this->create(
                $adminId,
                'admin_new_user',
                'New user registered',
                "{$newUser->name} ({$newUser->email}) created an account.",
                ['action_url' => $url]
            );
        }
    }

    public function notifySuperAdminsNewSubscription(User $subscriber, Plan $plan): void
    {
        $url = route('admin.subscriptions');
        foreach ($this->superAdminRecipientIds() as $adminId) {
            $this->create(
                $adminId,
                'admin_new_subscription',
                'Ka-ching! New subscription',
                "{$subscriber->name} subscribed to {$plan->name}.",
                ['action_url' => $url]
            );
        }
    }

    public function notifySuperAdminsSubscriptionRenewal(User $subscriber, Plan $plan): void
    {
        $url = route('admin.subscriptions');
        foreach ($this->superAdminRecipientIds() as $adminId) {
            $this->create(
                $adminId,
                'admin_subscription_renewal',
                'Ka-ching! Subscription renewed',
                "{$subscriber->name} renewed {$plan->name} — hear that? That’s the sound of recurring revenue.",
                ['action_url' => $url]
            );
        }
    }

    /**
     * Sends a transactional email to every active super admin (SMTP from notification settings).
     */
    public function emailSuperAdminsNewUser(User $newUser): void
    {
        $adminUrl = route('admin.users', ['search' => $newUser->email]);
        $subject = 'New user: ' . $newUser->name;
        $inner = '<p><strong>Someone new just walked in.</strong></p>'
            . '<p>' . e($newUser->name) . ' (' . e((string) $newUser->email) . ') created an account.</p>'
            . '<p><a href="' . e($adminUrl) . '">Open in admin</a></p>';

        $this->sendHtmlToSuperAdminEmails($subject, $this->simpleAdminEmailDocument($inner));
    }

    /**
     * @param  bool  $isRenewal  Same paid plan, payment after at least one prior completed payment.
     */
    public function emailSuperAdminsPaidSubscription(User $subscriber, Plan $plan, bool $isRenewal): void
    {
        $adminUrl = route('admin.subscriptions');
        if ($isRenewal) {
            $subject = 'Ka-ching! Subscription renewed — ' . $plan->name;
            $inner = '<p><strong>Ka-ching!</strong> Subscription renewal — the meter’s still running and the coffee’s still hot.</p>'
                . '<p>' . e($subscriber->name) . ' (' . e((string) $subscriber->email) . ') renewed <strong>' . e($plan->name) . '</strong>.</p>'
                . '<p><a href="' . e($adminUrl) . '">Subscriptions in admin</a></p>';
        } else {
            $subject = 'Ka-ching! New subscription — ' . $plan->name;
            $inner = '<p><strong>Ka-ching!</strong> New subscription — someone just turned enthusiasm into revenue.</p>'
                . '<p>' . e($subscriber->name) . ' (' . e((string) $subscriber->email) . ') subscribed to <strong>' . e($plan->name) . '</strong>.</p>'
                . '<p><a href="' . e($adminUrl) . '">Subscriptions in admin</a></p>';
        }

        $this->sendHtmlToSuperAdminEmails($subject, $this->simpleAdminEmailDocument($inner));
    }

    /**
     * Super-admin in-app alert when something breaks (email, publishing, queue, etc.).
     * When $dedupeKey is non-empty, identical alerts are suppressed for $dedupeTtlSeconds to avoid floods.
     *
     * @param  array<string, mixed>  $extraData
     */
    public function notifySuperAdminsOperationalAlert(
        string $inAppType,
        string $title,
        string $body,
        ?string $actionUrl = null,
        array $extraData = [],
        string $dedupeKey = '',
        int $dedupeTtlSeconds = 1800,
    ): void {
        if ($dedupeKey !== '') {
            $cacheKey = 'op_alert:v1:' . hash('sha256', $dedupeKey);
            if (! Cache::add($cacheKey, 1, max(60, $dedupeTtlSeconds))) {
                return;
            }
        }

        $data = $extraData;
        if ($actionUrl !== null && $actionUrl !== '') {
            $data['action_url'] = $actionUrl;
        }

        foreach ($this->superAdminRecipientIds() as $adminId) {
            $this->create($adminId, $inAppType, $title, $body, $data);
        }
    }

    public function notifySuperAdminsTrialStarted(User $subscriber, Plan $plan): void
    {
        $url = route('admin.subscriptions');
        foreach ($this->superAdminRecipientIds() as $adminId) {
            $this->create(
                $adminId,
                'admin_new_trial',
                'New trial started',
                "{$subscriber->name} started a trial on {$plan->name}.",
                ['action_url' => $url]
            );
        }
    }

    public function notifyStaffNewSupportTicket(SupportTicket $ticket): void
    {
        $ticket->loadMissing('user');
        $userName = $ticket->user?->name ?? 'User';
        $url = route('admin.tickets');
        foreach ($this->staffRecipientIds() as $id) {
            $this->create(
                $id,
                'admin_new_support_ticket',
                'New support ticket',
                "#{$ticket->id}: {$ticket->subject} — {$userName}",
                ['action_url' => $url]
            );
        }
    }

    /**
     * Notifies the ticket owner when staff adds a reply (not used for the customer’s own replies).
     */
    public function notifyUserSupportTicketReplied(SupportTicket $ticket, User $responder): void
    {
        if ($ticket->user_id === $responder->id) {
            return;
        }

        $url = route('support-tickets.show', $ticket->id);
        $this->create(
            $ticket->user_id,
            'support_ticket_replied',
            'Support replied to your ticket',
            "{$responder->name} replied to #{$ticket->id}: {$ticket->subject}",
            ['action_url' => $url]
        );
    }

    public function sendScheduledExpiryReminders(): void
    {
        $this->remindTrialsEnding();
        $this->remindSubscriptionsRenewing();
    }

    private function remindTrialsEnding(): void
    {
        $subs = Subscription::query()
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->with(['user', 'planModel'])
            ->get();

        foreach ($subs as $sub) {
            $user = $sub->user;
            $plan = $sub->planModel;
            if ($user === null || $user->status !== 'active') {
                continue;
            }

            $daysLeft = $this->calendarDaysUntil($sub->trial_ends_at);
            if (! in_array($daysLeft, [0, 1, 3], true)) {
                continue;
            }

            if ($this->wasReminderSentToday($user->id, 'user_trial_ending', $daysLeft)) {
                continue;
            }

            $planName = $plan?->name ?? 'your plan';
            $label = $daysLeft === 0
                ? 'Your trial ends today'
                : "Your trial ends in {$daysLeft} days";

            $this->create(
                $user->id,
                'user_trial_ending',
                $label,
                "Renew or choose a plan to keep access to {$planName} features.",
                ['action_url' => route('plans'), 'days_left' => $daysLeft]
            );

            QueueTemplatedEmailForUserJob::dispatch($user->id, 'subscription.trial_ending', [
                'planName' => $planName,
                'daysLeft' => $daysLeft,
                'trialEndsAt' => (string) $sub->trial_ends_at?->toDateString(),
            ]);
        }
    }

    private function remindSubscriptionsRenewing(): void
    {
        $subs = Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '>', now())
            ->with(['user', 'planModel'])
            ->get();

        foreach ($subs as $sub) {
            $user = $sub->user;
            $plan = $sub->planModel;
            if ($user === null || $user->status !== 'active' || $plan === null || $plan->is_free || $plan->is_lifetime) {
                continue;
            }

            $daysLeft = $this->calendarDaysUntil($sub->current_period_end);
            if (! in_array($daysLeft, [0, 1, 7], true)) {
                continue;
            }

            if ($this->wasReminderSentToday($user->id, 'user_subscription_renewal', $daysLeft)) {
                continue;
            }

            $label = match ($daysLeft) {
                0 => 'Subscription renews today',
                1 => 'Subscription renews tomorrow',
                default => 'Subscription renews in 7 days',
            };

            $this->create(
                $user->id,
                'user_subscription_renewal',
                $label,
                "Your {$plan->name} plan will renew soon. Review billing on the plans page.",
                ['action_url' => route('plans'), 'days_left' => $daysLeft]
            );

            QueueTemplatedEmailForUserJob::dispatch($user->id, 'subscription.reminder', [
                'planName' => $plan->name,
                'daysLeft' => $daysLeft,
                'renewsAt' => (string) $sub->current_period_end?->toDateString(),
            ]);
        }
    }

    private function calendarDaysUntil(CarbonInterface $end): int
    {
        $tz = (string) config('app.timezone', 'UTC');
        $endDay = $end->copy()->timezone($tz)->startOfDay();
        $today = now()->timezone($tz)->startOfDay();

        if ($endDay->lessThan($today)) {
            return -1;
        }

        return (int) $today->diffInDays($endDay);
    }

    private function wasReminderSentToday(int $userId, string $type, int $daysLeft): bool
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->whereDate('created_at', today())
            ->get()
            ->contains(static function (Notification $n) use ($daysLeft): bool {
                return (int) ($n->data['days_left'] ?? -1) === $daysLeft;
            });
    }

    /** @return list<int> */
    private function superAdminRecipientIds(): array
    {
        return User::query()
            ->where('role', 'super_admin')
            ->where('status', 'active')
            ->pluck('id')
            ->all();
    }

    /** @return list<string> */
    private function superAdminEmails(): array
    {
        $emails = User::query()
            ->where('role', 'super_admin')
            ->where('status', 'active')
            ->whereNotNull('email')
            ->pluck('email');

        return Collection::make($emails)
            ->filter(static fn (mixed $email): bool => is_string($email) && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }

    private function simpleAdminEmailDocument(string $innerHtml): string
    {
        $app = e(config('app.name'));

        return '<!DOCTYPE html><html><body style="font-family:system-ui,sans-serif;line-height:1.5;color:#111">'
            . '<p style="color:#666;font-size:14px;margin:0 0 1em">' . $app . ' — admin alert</p>'
            . $innerHtml
            . '</body></html>';
    }

    private function sendHtmlToSuperAdminEmails(string $subject, string $html): void
    {
        $mail = app(NotificationChannelConfigService::class);
        foreach ($this->superAdminEmails() as $to) {
            try {
                $mail->sendHtml($to, $subject, $html);
            } catch (\Throwable $e) {
                Log::warning('Failed to send super-admin email', [
                    'to' => $to,
                    'subject' => $subject,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /** @return list<int> */
    private function staffRecipientIds(): array
    {
        return User::query()
            ->whereIn('role', ['super_admin', 'support'])
            ->where('status', 'active')
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(int $userId, string $type, string $title, string $body, array $data): void
    {
        Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ]);
    }
}
