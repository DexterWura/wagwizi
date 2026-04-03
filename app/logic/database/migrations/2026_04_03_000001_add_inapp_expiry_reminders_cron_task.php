<?php

use App\Models\CronTask;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CronTask::firstOrCreate(
            ['key' => 'inapp_expiry_reminders'],
            [
                'label'            => 'In-app subscription & trial reminders',
                'description'      => 'Creates dashboard notifications when trials or paid subscriptions are about to renew.',
                'interval_minutes' => 1440,
                'enabled'          => true,
            ]
        );
    }

    public function down(): void
    {
        CronTask::query()->where('key', 'inapp_expiry_reminders')->delete();
    }
};
