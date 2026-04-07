<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * When {@see SiteSetting} {@code under_construction} is enabled, only active super-admins may use the app.
 * Payment webhooks, cron, password reset, login, logout, and leaving impersonation stay reachable.
 */
class EnforceUnderConstructionMode
{
    private const SETTING_KEY = 'under_construction';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isEnabled()) {
            return $next($request);
        }

        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        if ($this->actingUserIsSuperAdmin()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'The application is temporarily unavailable. Please try again later.',
                'under_construction' => true,
            ], 503);
        }

        return response()
            ->view('under-construction', [], 503)
            ->header('Retry-After', '3600');
    }

    private function isEnabled(): bool
    {
        return SiteSetting::get(self::SETTING_KEY, '0') === '1';
    }

    private function shouldBypass(Request $request): bool
    {
        if ($request->routeIs(
            'login',
            'password.request',
            'password.email',
            'password.reset.form',
            'password.update',
            'logout',
            'impersonation.leave',
        )) {
            return true;
        }

        return $request->is(
            'stripe/webhook',
            'paypal/webhook',
            'paynow/result',
            'pesepay/result',
            'cron',
            'status',
        );
    }

    private function actingUserIsSuperAdmin(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->isSuperAdmin()
            && $user->status === 'active';
    }
}
