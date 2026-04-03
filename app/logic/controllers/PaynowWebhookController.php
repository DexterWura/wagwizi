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
            $checkout->handleResultPost($request->request->all());
        } catch (Throwable $e) {
            Log::error('Paynow webhook handling failed', [
                'message' => $e->getMessage(),
                'class'   => $e::class,
            ]);
        }

        return response('OK', 200);
    }
}
