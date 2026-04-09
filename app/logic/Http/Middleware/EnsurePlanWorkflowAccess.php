<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Subscription\PlanWorkflowFeatureService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanWorkflowAccess
{
    public function handle(Request $request, Closure $next): Response
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

        $allowed = app(PlanWorkflowFeatureService::class)->userMayUseWorkflows((int) $user->id);
        if (! $allowed) {
            $message = 'Your current plan does not include Workflows. Upgrade to continue.';
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'workflow_plan_restricted',
                    'message' => $message,
                ], 403);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}

