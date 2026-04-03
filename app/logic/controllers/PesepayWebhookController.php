<?php

namespace App\Controllers;

use App\Services\Billing\PesepayCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class PesepayWebhookController extends Controller
{
    public function result(Request $request, PesepayCheckoutService $checkout): Response
    {
        try {
            $body = $request->all();
            if ($body === []) {
                $raw = $request->getContent();
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    $body = is_array($decoded) ? $decoded : [];
                }
            }
            if ($body === [] && $request->isMethod('get')) {
                $q = $request->query('payload');
                if (is_string($q) && $q !== '') {
                    $body = ['payload' => $q];
                }
            }
            $checkout->handleResultPost($body);
        } catch (Throwable $e) {
            Log::error('Pesepay webhook handling failed', [
                'message' => $e->getMessage(),
                'class'   => $e::class,
            ]);
        }

        return response('OK', 200);
    }
}
