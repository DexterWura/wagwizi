<?php

namespace App\Services\Cron;

use App\Models\CronTask;
use App\Models\CronTaskRun;
use Illuminate\Support\Facades\Log;

class CronService
{
    private array $handlers = [];

    public function register(string $taskKey, callable $handler): void
    {
        $this->handlers[$taskKey] = $handler;
    }

    /**
     * Run all enabled and due tasks. Returns a summary for each.
     */
    public function runDueTasks(): array
    {
        $tasks   = CronTask::enabled()->get();
        $results = [];

        foreach ($tasks as $task) {
            if (!$task->isDue()) {
                $results[] = [
                    'key'     => $task->key,
                    'status'  => 'skipped',
                    'reason'  => 'not due yet',
                ];
                continue;
            }

            $results[] = $this->executeTask($task);
        }

        return $results;
    }

    /**
     * Force-run a single task by key regardless of schedule.
     */
    public function runTask(string $key): array
    {
        $task = CronTask::where('key', $key)->first();

        if ($task === null) {
            return ['key' => $key, 'status' => 'error', 'output' => 'Task not found.'];
        }

        return $this->executeTask($task);
    }

    /**
     * Seed default tasks if they don't exist yet.
     */
    public function seedDefaults(): void
    {
        $defaults = [
            [
                'key'              => 'publish_due_posts',
                'label'            => 'Publish scheduled posts',
                'description'      => 'Finds posts that are due and publishes them (runs publish jobs synchronously by default so a queue worker is not required).',
                'interval_minutes' => 1,
                'enabled'          => true,
            ],
            [
                'key'              => 'refresh_tokens',
                'label'            => 'Refresh expiring tokens',
                'description'      => 'Refreshes OAuth tokens for social accounts that are about to expire.',
                'interval_minutes' => 15,
                'enabled'          => true,
            ],
            [
                'key'              => 'refresh_tokens_unknown_expiry',
                'label'            => 'Refresh tokens missing expiry',
                'description'      => 'Refreshes OAuth/Bluesky accounts that have a refresh token but no stored token_expires_at.',
                'interval_minutes' => 1440,
                'enabled'          => true,
            ],
            [
                'key'              => 'purge_old_logs',
                'label'            => 'Purge old log files',
                'description'      => 'Deletes log files older than 14 days to free disk space.',
                'interval_minutes' => 1440,
                'enabled'          => true,
            ],
            [
                'key'              => 'inapp_expiry_reminders',
                'label'            => 'In-app subscription & trial reminders',
                'description'      => 'Creates dashboard notifications when trials or paid subscriptions are about to renew.',
                'interval_minutes' => 1440,
                'enabled'          => true,
            ],
            [
                'key'              => 'pending_migrations_alert',
                'label'            => 'Pending migrations admin alert',
                'description'      => 'Notifies super admins in-app when database migrations have not been applied.',
                'interval_minutes' => 60,
                'enabled'          => true,
            ],
        ];

        foreach ($defaults as $data) {
            CronTask::firstOrCreate(
                ['key' => $data['key']],
                $data,
            );
        }
    }

    private function executeTask(CronTask $task): array
    {
        if (!isset($this->handlers[$task->key])) {
            Log::warning('Cron task has no registered handler', ['key' => $task->key]);
            $task->update([
                'last_status' => 'failed',
                'last_ran_at' => now(),
                'last_duration_ms' => 0,
                'last_output' => 'No handler registered for this task.',
            ]);
            CronTaskRun::query()->create([
                'cron_task_id' => $task->id,
                'task_key' => $task->key,
                'status' => 'failed',
                'duration_ms' => 0,
                'output' => 'No handler registered for this task.',
                'ran_at' => now(),
            ]);
            return [
                'key'    => $task->key,
                'status' => 'error',
                'output' => 'No handler registered for this task.',
            ];
        }

        $task->markRunning();
        $start = hrtime(true);

        try {
            $output = call_user_func($this->handlers[$task->key]);
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            $encoded = is_string($output) ? $output : json_encode($output);
            $outputStr = is_string($encoded) ? $encoded : '';
            $task->markFinished('success', $durationMs, $outputStr);
            CronTaskRun::query()->create([
                'cron_task_id' => $task->id,
                'task_key' => $task->key,
                'status' => 'success',
                'duration_ms' => $durationMs,
                'output' => $outputStr !== '' ? mb_substr($outputStr, 0, 4000) : null,
                'ran_at' => now(),
            ]);

            Log::info('Cron task completed', [
                'key'         => $task->key,
                'duration_ms' => $durationMs,
            ]);

            return [
                'key'         => $task->key,
                'status'      => 'success',
                'duration_ms' => $durationMs,
                'output'      => $outputStr,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
            $task->markFinished('failed', $durationMs, $e->getMessage());
            CronTaskRun::query()->create([
                'cron_task_id' => $task->id,
                'task_key' => $task->key,
                'status' => 'failed',
                'duration_ms' => $durationMs,
                'output' => mb_substr($e->getMessage(), 0, 4000),
                'ran_at' => now(),
            ]);

            Log::error('Cron task failed', [
                'key'         => $task->key,
                'duration_ms' => $durationMs,
                'error'       => $e->getMessage(),
            ]);

            return [
                'key'         => $task->key,
                'status'      => 'failed',
                'duration_ms' => $durationMs,
                'output'      => $e->getMessage(),
            ];
        }
    }
}
