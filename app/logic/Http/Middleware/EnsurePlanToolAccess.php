<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tools\ToolAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanToolAccess
{
    public function handle(Request $request, Closure $next, string $toolSlug): Response
    {
        $user = Auth::user();
        if ($user === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'unauthenticated',
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            abort(401, 'Unauthenticated.');
        }

        $decision = app(ToolAccessService::class)->evaluateUserAccess($user, $toolSlug);
        if (! ($decision['allowed'] ?? false)) {
            $message = (string) ($decision['message'] ?? 'You are not allowed to use this tool.');
            $code = (string) ($decision['code'] ?? 'tool_not_allowed');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error_code' => $code,
                    'message' => $message,
                ], 403);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}

