<?php

namespace App\Controllers;

use App\Services\Ai\AiOutboundUrlValidator;
use App\Services\Ai\PlatformAiQuotaService;
use App\Services\Workspace\WorkspaceAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
    ) {}

    public function updateWorkspace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_name' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        try {
            $this->workspaceAccess->ensureAdmin($user);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $workspace = $this->workspaceAccess->activeWorkspace($user);
        if ($workspace !== null) {
            $workspace->update(['name' => $validated['workspace_name']]);
        }
        $user->update(['workspace_name' => $validated['workspace_name']]);

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

    public function updateTheme(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme_preference' => 'required|string|in:light,dark',
        ]);

        $user = Auth::user();
        $user->update([
            'theme_preference' => $validated['theme_preference'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Theme preference saved.',
        ]);
    }

    public function updateAiSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_source'         => 'required|string|in:platform,byok',
            'ai_provider'       => 'nullable|string|in:openai,anthropic,gemini,custom',
            'ai_base_url'       => 'nullable|url|max:500',
            'ai_api_key'        => 'nullable|string|max:8192',
            'ai_personality'    => 'nullable|string|max:2000',
            'ai_clear_api_key'  => 'sometimes|boolean',
        ]);

        $provider = $validated['ai_provider'] ?? null;
        if ($provider === 'custom' && ! empty($validated['ai_base_url'])) {
            try {
                app(AiOutboundUrlValidator::class)->assertSafeForServerSideHttp($validated['ai_base_url']);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'ai_base_url' => [$e->getMessage()],
                ]);
            }
        }

        $user = Auth::user();

        $attrs = [
            'ai_source'   => $validated['ai_source'],
            'ai_provider' => $validated['ai_provider'] ?? null,
            'ai_base_url' => $validated['ai_base_url'] ?? null,
        ];

        if (array_key_exists('ai_personality', $validated)) {
            $p = trim(strip_tags((string) $validated['ai_personality']));
            $attrs['ai_personality'] = $p !== '' ? $p : null;
        }

        if ($request->boolean('ai_clear_api_key')) {
            $attrs['ai_api_key'] = null;
        } elseif ($request->filled('ai_api_key')) {
            $attrs['ai_api_key'] = $validated['ai_api_key'];
        }

        $user->update($attrs);
        $user->refresh();
        app(PlatformAiQuotaService::class)->invalidateLayoutSummaryCache((int) $user->id);

        return response()->json([
            'success'     => true,
            'message'     => 'AI settings saved.',
            'has_api_key' => $user->hasAiApiKeyStored(),
        ]);
    }
}
