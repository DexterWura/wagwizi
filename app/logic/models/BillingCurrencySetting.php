<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingCurrencySetting extends Model
{
    protected $fillable = [
        'base_currency',
        'default_display_currency',
        'paynow_checkout_currency',
        'exchange_rates',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rates' => 'array',
        ];
    }
}
