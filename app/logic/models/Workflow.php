<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
        'trigger_type',
        'trigger_config',
        'graph',
        'graph_version',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'graph' => 'array',
            'graph_version' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class)->latest('id');
    }
}

