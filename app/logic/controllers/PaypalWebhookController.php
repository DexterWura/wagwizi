<?php

namespace App\Controllers;

use App\Services\Billing\PaypalCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaypalWebhookController extends Controller
{
    public function result(Request $request, PaypalCheckoutService $checkout): Response
    {
        try {
            $raw = (string) $request->getContent();
            $checkout->handleWebhookPayload(
                $raw,
                $this->headerString($request, 'PAYPAL-AUTH-ALGO'),
                $this->headerString($request, 'PAYPAL-CERT-URL'),
                $this->headerString($request, 'PAYPAL-TRANSMISSION-ID'),
                $this->headerString($request, 'PAYPAL-TRANSMISSION-SIG'),
                $this->headerString($request, 'PAYPAL-TRANSMISSION-TIME')
            );
            Log::info('PayPal webhook processed', [
                'has_transmission_id' => $this->headerString($request, 'PAYPAL-TRANSMISSION-ID') !== null,
                'payload_bytes' => strlen($raw),
                'method' => $request->method(),
            ]);
        } catch (Throwable $e) {
            Log::error('PayPal webhook handling failed', [
                'message' => $e->getMessage(),
                'class'   => $e::class,
            ]);
        }

        return response('OK', 200);
    }

    private function headerString(Request $request, string $name): ?string
    {
        $v = $request->header($name);
        if (! is_string($v) || trim($v) === '') {
            return null;
        }

        return $v;
    }
}
