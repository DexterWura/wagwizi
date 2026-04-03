<?php

namespace App\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function updateWorkspace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_name' => 'required|string|max:255',
            'workspace_slug' => 'required|string|max:100|regex:/^[a-z0-9\-]+$/',
        ]);

        $user = Auth::user();
        $user->update([
            'workspace_name' => $validated['workspace_name'],
            'workspace_slug' => $validated['workspace_slug'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Workspace settings saved.',
        ]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_on_failure'       => 'required|boolean',
            'weekly_digest'          => 'required|boolean',
            'product_updates'        => 'required|boolean',
            'marketing_email_opt_in' => 'required|boolean',
        ]);

        $user = Auth::user();
        $user->update([
            'notification_preferences' => [
                'email_on_failure' => $validated['email_on_failure'],
                'weekly_digest'    => $validated['weekly_digest'],
                'product_updates'  => $validated['product_updates'],
            ],
            'marketing_email_opt_in' => $validated['marketing_email_opt_in'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences saved.',
        ]);
    }

    public function updateDefaultTime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'default_posting_time' => 'required|date_format:H:i',
        ]);

        $user = Auth::user();
        $user->update([
            'default_posting_time' => $validated['default_posting_time'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Default posting time saved.',
        ]);
    }

    public function updateAiSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_source'   => 'required|string|in:platform,byok',
            'ai_provider' => 'nullable|string|in:openai,anthropic,custom',
            'ai_base_url' => 'nullable|url|max:500',
        ]);

        $user = Auth::user();
        $user->update([
            'ai_source'   => $validated['ai_source'],
            'ai_provider' => $validated['ai_provider'] ?? null,
            'ai_base_url' => $validated['ai_base_url'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'AI settings saved.',
        ]);
    }
}
