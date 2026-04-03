<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaign extends Model
{
    protected $fillable = [
        'name',
        'template_key',
        'email_template_id',
        'segment_rules',
        'status',
        'scheduled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'segment_rules' => 'array',
            'scheduled_at'  => 'datetime',
        ];
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
