<?php

namespace App\Controllers;

use App\Services\Billing\PaynowCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaynowWebhookController extends Controller
{
    public function result(Request $request, PaynowCheckoutService $checkout): Response
    {
        try {
            $payload = $request->request->all();
            $checkout->handleResultPost($payload);
            Log::info('Paynow webhook processed', [
                'has_hash' => isset($payload['hash']),
                'reference' => $payload['reference'] ?? null,
                'method' => $request->method(),
            ]);
        } catch (Throwable $e) {
            Log::error('Paynow webhook handling failed', [
                'message' => $e->getMessage(),
                'class'   => $e::class,
            ]);
        }

        return response('OK', 200);
    }
}
