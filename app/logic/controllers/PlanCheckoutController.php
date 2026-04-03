<?php

namespace App\Controllers;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Services\Billing\PaynowCheckoutService;
use App\Services\Billing\SubscriptionFulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PlanCheckoutController extends Controller
{
    public function startPaynow(Request $request, PaynowCheckoutService $checkout): JsonResponse
    {
        $validated = $request->validate([
            'plan_slug' => ['required', 'string', Rule::exists('plans', 'slug')->where('is_active', true)],
        ]);

        $user = Auth::user();
        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        $fulfillment = app(SubscriptionFulfillmentService::class);
        if (! $fulfillment->requiresOnlinePayment($plan)) {
            return response()->json([
                'success' => false,
                'message' => 'This plan does not require Paynow checkout.',
            ], 422);
        }

        if ($plan->is_lifetime && $plan->hasReachedLifetimeCap()) {
            return response()->json([
                'success' => false,
                'message' => 'This lifetime deal has reached its subscriber limit.',
            ], 422);
        }

        try {
            $returnUrl = route('plans.paynow.return');
            $resultUrl = url('/paynow/result');
            $payload   = $checkout->startHostedCheckout($user, $plan, $returnUrl, $resultUrl);
        } catch (\Throwable $e) {
            Log::warning('Paynow checkout start failed', [
                'user_id'     => $user->id,
                'plan_slug'   => $validated['plan_slug'],
                'exception'   => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not start checkout. Please try again or contact support.',
            ], 422);
        }

        return response()->json([
            'success'       => true,
            'redirect_url'  => $payload['redirect_url'],
            'reference'     => $payload['reference'],
        ]);
    }

    public function paynowReturn(Request $request, PaynowCheckoutService $checkout): RedirectResponse
    {
        $user = Auth::user();
        $ref  = $request->query('reference');

        if (! is_string($ref) || $ref === '') {
            $latest = PaymentTransaction::query()
                ->where('user_id', $user->id)
                ->where('gateway', 'paynow')
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->first();

            if ($latest) {
                $checkout->tryCompleteFromPoll($latest);
                $latest->refresh();
                if ($latest->isCompleted()) {
                    return redirect()->route('plans')->with('success', 'Payment received. Your plan is active.');
                }
            }

            return redirect()->route('plans')->with('info', 'If you completed payment, your plan will update shortly. Refresh this page in a moment.');
        }

        $transaction = PaymentTransaction::query()
            ->where('user_id', $user->id)
            ->where('reference', $ref)
            ->first();

        if ($transaction === null) {
            return redirect()->route('plans')->with('error', 'We could not find that payment.');
        }

        if ($transaction->isCompleted()) {
            return redirect()->route('plans')->with('success', 'Your subscription is active.');
        }

        $checkout->tryCompleteFromPoll($transaction);
        $transaction->refresh();

        if ($transaction->isCompleted()) {
            return redirect()->route('plans')->with('success', 'Payment received. Your plan is active.');
        }

        return redirect()->route('plans')->with('info', 'Payment is still processing. This page will update when Paynow confirms.');
    }
}
