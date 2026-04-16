<?php

namespace App\Controllers;

use App\Services\Billing\StripeCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class StripeWebhookController extends Controller
{
    public function result(Request $request, StripeCheckoutService $checkout): Response
    {
        try {
            $raw = (string) $request->getContent();
            $checkout->handleWebhookPayload(
                $raw,
                $request->header('Stripe-Signature')
            );
            Log::info('Stripe webhook processed', [
                'has_signature' => (string) $request->header('Stripe-Signature') !== '',
                'payload_bytes' => strlen($raw),
                'method' => $request->method(),
            ]);
        } catch (Throwable $e) {
            Log::error('Stripe webhook handling failed', [
                'message' => $e->getMessage(),
                'class' => $e::class,
            ]);

            $status = $e instanceof \InvalidArgumentException ? 400 : 500;

            return response('Webhook processing failed', $status);
        }

        return response('OK', 200);
    }
}

