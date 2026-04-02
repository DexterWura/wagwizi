<?php

namespace App\Controllers;

use App\Services\Billing\PaynowCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaynowWebhookController extends Controller
{
    public function result(Request $request, PaynowCheckoutService $checkout): Response
    {
        try {
            $checkout->handleResultPost($request->request->all());
        } catch (\Throwable) {
            // Paynow expects a response; avoid leaking details.
        }

        return response('OK', 200);
    }
}
