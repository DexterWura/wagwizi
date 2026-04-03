<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'gateway',
        'reference',
        'amount_cents',
        'currency',
        'status',
        'completed_at',
        'failed_at',
        'failure_message',
        'poll_url',
        'paynow_reference',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta'         => 'array',
            'completed_at' => 'datetime',
            'failed_at'    => 'datetime',
        ];
    }

    public function resolvedFailureMessage(): ?string
    {
        if (is_string($this->failure_message) && trim($this->failure_message) !== '') {
            return $this->failure_message;
        }
        $m = $this->meta;
        if (is_array($m) && isset($m['error']) && is_string($m['error']) && $m['error'] !== '') {
            return $m['error'];
        }

        return null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
