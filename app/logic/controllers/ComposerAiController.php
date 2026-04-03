<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Ai\ComposerAiChatService;
use App\Services\Ai\PlatformAiPlanHasNoTokensException;
use App\Services\Ai\PlatformAiQuotaExceededException;
use App\Services\Ai\PlatformAiQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ComposerAiController extends Controller
{
    public function chat(Request $request, ComposerAiChatService $ai): JsonResponse
    {
        $user = Auth::user();
        if ($user === null) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (! $user->canAccessComposerAi()) {
            $quota = app(PlatformAiQuotaService::class);
            if ($quota->isPlatformAiQuotaExhausted($user)) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'platform_ai_quota_exhausted',
                    'message'    => 'You have used all platform AI credits for this billing period. Wait until your plan renews, or use your own API key under Settings → AI.',
                ], 429);
            }
            if ($quota->isPlatformAiDisabledOnPlan($user)) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'platform_ai_plan_no_tokens',
                    'message'    => 'Your plan does not include platform AI credits. Use your own API key under Settings → AI, or upgrade to a plan that includes credits.',
                ], 403);
            }

            return response()->json([
                'success' => false,
                'message' => 'AI assistant is not available for your account.',
            ], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:4000',
            'draft'   => 'nullable|string|max:32000',
        ]);

        try {
            $result = $ai->complete($user, $validated['message'], (string) ($validated['draft'] ?? ''));

            return response()->json([
                'success'        => true,
                'reply'          => $result->reply,
                'tokens_charged' => $result->totalTokens,
                'billing_source' => $result->billingSource,
            ]);
        } catch (PlatformAiQuotaExceededException $e) {
            return response()->json([
                'success'    => false,
                'error_code' => 'platform_ai_quota_exhausted',
                'message'    => $e->getMessage(),
            ], 429);
        } catch (PlatformAiPlanHasNoTokensException $e) {
            return response()->json([
                'success'    => false,
                'error_code' => 'platform_ai_plan_no_tokens',
                'message'    => $e->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Composer AI request failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
                'class'   => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The assistant could not complete this request. Check your AI settings or try again shortly.',
            ], 502);
        }
    }
}
