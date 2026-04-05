<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->isSuperAdmin() || $user->status !== 'active') {
            abort(403, 'Access denied. Super admin privileges required.');
        }

        return $next($request);
    }
}
