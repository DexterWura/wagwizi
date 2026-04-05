<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveUserStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user !== null && $user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is not active. Contact support.',
            ]);
        }

        return $next($request);
    }
}

