<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronTask extends Model
{
    protected $fillable = [
        'key',
        'label',
        'description',
        'enabled',
        'interval_minutes',
        'last_ran_at',
        'last_duration_ms',
        'last_status',
        'last_output',
    ];

    protected function casts(): array
    {
        return [
            'enabled'     => 'boolean',
            'last_ran_at' => 'datetime',
        ];
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function isDue(): bool
    {
        if ($this->last_ran_at === null) {
            return true;
        }

        return $this->last_ran_at->addMinutes($this->interval_minutes)->lte(now());
    }

    public function markRunning(): void
    {
        $this->update([
            'last_status' => 'running',
            'last_ran_at' => now(),
        ]);
    }

    public function markFinished(string $status, int $durationMs, ?string $output = null): void
    {
        $this->update([
            'last_status'      => $status,
            'last_duration_ms' => $durationMs,
            'last_output'      => $output !== null ? mb_substr($output, 0, 2000) : null,
        ]);
    }
}
