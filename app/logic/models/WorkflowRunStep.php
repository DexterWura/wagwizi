<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRunStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_run_id',
        'node_id',
        'node_type',
        'status',
        'position',
        'attempt',
        'input_payload',
        'output_payload',
        'error_message',
        'duration_ms',
        'ai_tokens_used',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'attempt' => 'integer',
            'input_payload' => 'array',
            'output_payload' => 'array',
            'duration_ms' => 'integer',
            'ai_tokens_used' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class, 'workflow_run_id');
    }
}

