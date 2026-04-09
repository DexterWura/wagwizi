<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SplFileInfo;

class PurgeOldLogs extends Command
{
    protected $signature   = 'logs:purge {--days=7 : Delete log files older than this many days}';
    protected $description = 'Delete archived log files older than the specified number of days';

    public function handle(): int
    {
        $maxAgeDays = (int) $this->option('days');
        $logsPath   = storage_path('logs');

        if (!is_dir($logsPath)) {
            $this->warn('Logs directory does not exist.');
            return self::SUCCESS;
        }

        $cutoff  = now()->subDays($maxAgeDays);
        $deleted = 0;
        $freed   = 0;

        $files = glob($logsPath . DIRECTORY_SEPARATOR . '*.log');

        foreach ($files as $filePath) {
            $file = new SplFileInfo($filePath);

            if (!$file->isFile() || !$file->isWritable()) {
                continue;
            }

            $lastModified = \Carbon\Carbon::createFromTimestamp($file->getMTime());

            if ($lastModified->lt($cutoff)) {
                $size = $file->getSize();

                if (@unlink($filePath)) {
                    $deleted++;
                    $freed += $size;
                    $this->line("Deleted: {$file->getFilename()} (" . $this->humanSize($size) . ')');
                } else {
                    $this->error("Failed to delete: {$file->getFilename()}");
                }
            }
        }

        $summary = $deleted === 0
            ? 'No log files older than ' . $maxAgeDays . ' days found.'
            : "Purged {$deleted} log file(s), freed " . $this->humanSize($freed) . '.';

        $this->info($summary);
        Log::info('Log purge completed', ['deleted' => $deleted, 'freed_bytes' => $freed, 'max_age_days' => $maxAgeDays]);

        return self::SUCCESS;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = 0;
        $size  = (float) $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }
}
