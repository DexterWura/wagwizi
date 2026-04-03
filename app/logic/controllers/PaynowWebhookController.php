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
        }

        return response('OK', 200);
    }
}
