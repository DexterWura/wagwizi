<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronTaskRun extends Model
{
    protected $fillable = [
        'cron_task_id',
        'task_key',
        'status',
        'duration_ms',
        'output',
        'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
        ];
    }

    public function cronTask(): BelongsTo
    {
        return $this->belongsTo(CronTask::class);
    }
}

