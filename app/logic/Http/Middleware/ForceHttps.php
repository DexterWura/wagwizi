<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects plain HTTP to HTTPS when the app is configured to require TLS.
 * Skips local/testing so `php artisan serve` and installers work without certificates.
 */
final class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->mustEnforce()) {
            return $next($request);
        }

        if ($request->secure()) {
            return $next($request);
        }

        return redirect()->secure($request->getRequestUri(), 301);
    }

    private function mustEnforce(): bool
    {
        if (! filter_var(config('app.force_https', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return ! app()->environment('local', 'testing');
    }
}
