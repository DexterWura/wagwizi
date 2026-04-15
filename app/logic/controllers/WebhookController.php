<?php

namespace App\Controllers;

use App\Services\Webhook\UserWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WebhookController extends Controller
{
    public function __construct(
        private readonly UserWebhookService $userWebhookService,
    ) {}

    public function regenerate(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if (!$this->userWebhookService->userMayUseWebhooks($user)) {
            return redirect()->route('tools')
                ->with('error', 'Your current plan does not include webhooks.');
        }

        $this->userWebhookService->regenerateSecret($user);

        return redirect()->route('tools')
            ->with('success', 'Webhook secret regenerated. Update integrations using the new secret.');
    }

    public function inbound(Request $request, string $webhookKeyId): JsonResponse
    {
        $providedSecret = trim((string) $request->header('X-Webhook-Secret', ''));
        if ($providedSecret === '') {
            // Optional fallback for systems that cannot set custom headers.
            $providedSecret = trim((string) $request->input('webhook_secret', ''));
        }

        $auth = $this->userWebhookService->authenticateInbound($webhookKeyId, $providedSecret);
        if (!($auth['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => (string) ($auth['error'] ?? 'Webhook authentication failed.'),
            ], 403);
        }

        $user = $auth['user'];

        try {
            $payload = $request->json()->all();
            if (!is_array($payload) || $payload === []) {
                $payload = $request->all();
            }

            $result = $this->userWebhookService->executeInboundAction($user, $payload);

            Log::info('Inbound webhook processed', [
                'user_id' => $user->id,
                'webhook_key_id' => $webhookKeyId,
                'action' => $payload['action'] ?? 'draft',
                'post_id' => $result['post_id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook action completed.',
                'result' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Inbound webhook failed', [
                'webhook_key_id' => $webhookKeyId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Webhook execution failed due to a server error.',
            ], 500);
        }
    }
}

