<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonInterface;

final class InAppNotificationService
{
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
                'New subscription',
                "{$subscriber->name} subscribed to {$plan->name}.",
                ['action_url' => $url]
            );
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
