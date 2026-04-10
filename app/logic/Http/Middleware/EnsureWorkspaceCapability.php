<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceCapability
{
    public function handle(Request $request, Closure $next, string $capability = 'post'): Response
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        // During rollout (before migrations are applied), avoid hard-locking users out.
        try {
            $membership = $user->activeWorkspaceMembership();
        } catch (\Throwable) {
            return $next($request);
        }

        if ($membership === null) {
            return $next($request);
        }

        $role = (string) $membership->role;
        $status = (string) $membership->status;

        if ($status !== 'active') {
            return $this->forbidden($request, 'Your workspace membership is not active.');
        }

        if ($role === 'admin') {
            return $next($request);
        }

        $allowed = match ($capability) {
            'manage_workspace' => false,
            'workflow' => in_array($role, ['member'], true),
            'post' => in_array($role, ['member'], true),
            default => false,
        };

        if ($allowed) {
            return $next($request);
        }

        return $this->forbidden($request, 'Your workspace role does not allow this action.');
    }

    private function forbidden(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => $message], 403);
        }

        return redirect()->route('dashboard')->with('error', $message);
    }
}

