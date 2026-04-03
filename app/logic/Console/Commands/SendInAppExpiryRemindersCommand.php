<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Notifications\InAppNotificationService;
use Illuminate\Console\Command;

final class SendInAppExpiryRemindersCommand extends Command
{
    protected $signature = 'notifications:send-expiry-reminders';

    protected $description = 'Create in-app notifications for trials and subscriptions nearing renewal';

    public function handle(InAppNotificationService $inAppNotificationService): int
    {
        $inAppNotificationService->sendScheduledExpiryReminders();

        return self::SUCCESS;
    }
}
