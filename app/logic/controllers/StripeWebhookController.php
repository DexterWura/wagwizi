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
            $checkout->handleWebhookPayload(
                (string) $request->getContent(),
                $request->header('Stripe-Signature')
            );
        } catch (Throwable $e) {
            Log::error('Stripe webhook handling failed', [
                'message' => $e->getMessage(),
                'class' => $e::class,
            ]);
        }

        return response('OK', 200);
    }
}

