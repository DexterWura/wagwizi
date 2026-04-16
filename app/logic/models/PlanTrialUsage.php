<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanTrialUsage extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'started_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
        ];
    }
}

