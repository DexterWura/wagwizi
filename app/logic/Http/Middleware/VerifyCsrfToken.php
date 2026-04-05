<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /** @var array<int, string> */
    protected $except = [
        'paynow/result',
        'pesepay/result',
        'stripe/webhook',
        'paypal/webhook',
    ];
}
