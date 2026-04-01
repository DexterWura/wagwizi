<?php

namespace App\Console\Commands;

use App\Services\Cron\CronService;
use Illuminate\Console\Command;

class SeedCronTasks extends Command
{
    protected $signature   = 'cron:seed';
    protected $description = 'Seed the default cron tasks into the database if they do not exist yet';

    public function handle(CronService $cronService): int
    {
        $cronService->seedDefaults();

        $this->info('Default cron tasks seeded.');

        return self::SUCCESS;
    }
}
