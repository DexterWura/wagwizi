<?php

namespace App\Console\Commands;

use App\Models\AuditTrail;
use Illuminate\Console\Command;

class PurgeOldAuditTrail extends Command
{
    protected $signature = 'audit:purge {--days=90 : Delete audit rows older than this many days}';

    protected $description = 'Delete old audit trail records';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $deleted = AuditTrail::query()
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} audit record(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}

