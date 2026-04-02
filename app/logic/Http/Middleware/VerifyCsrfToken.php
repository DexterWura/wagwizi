<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Paynow posts server-to-server; validated via Paynow hash instead.
     *
     * @var array<int, string>
     */
    protected $except = [
        'paynow/result',
    ];
}
