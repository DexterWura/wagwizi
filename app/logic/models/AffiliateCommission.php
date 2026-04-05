<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'payment_transaction_id',
        'base_amount_cents',
        'commission_amount_cents',
        'currency',
        'commission_percent',
        'status',
        'awarded_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_percent' => 'float',
            'awarded_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}

