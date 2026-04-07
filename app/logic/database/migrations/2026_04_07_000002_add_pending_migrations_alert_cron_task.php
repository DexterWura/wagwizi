<?php

use App\Models\CronTask;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CronTask::firstOrCreate(
            ['key' => 'pending_migrations_alert'],
            [
                'label'            => 'Pending migrations admin alert',
                'description'      => 'Notifies super admins in-app when database migrations have not been applied.',
                'interval_minutes' => 60,
                'enabled'          => true,
            ]
        );
    }

    public function down(): void
    {
        CronTask::query()->where('key', 'pending_migrations_alert')->delete();
    }
};
