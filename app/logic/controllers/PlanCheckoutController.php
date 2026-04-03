<?php

namespace App\Controllers;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\PaymentGatewayConfigService;
use App\Services\Billing\PaynowCheckoutService;
use App\Services\Billing\PesepayCheckoutService;
use App\Services\Billing\SubscriptionFulfillmentService;
use App\Services\Subscription\SubscriptionAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PlanCheckoutController extends Controller
{
    public function startCheckout(
        Request $request,
        PaymentGatewayConfigService $cfg,
        PaynowCheckoutService $paynow,
        PesepayCheckoutService $pesepay
    ): JsonResponse {
        $validated = $request->validate([
            'plan_slug' => ['required', 'string', Rule::exists('plans', 'slug')->where('is_active', true)],
            'gateway'   => 'nullable|string|in:paynow,pesepay',
        ]);

        $available = $cfg->availableCheckoutGateways();
        if ($available === []) {
            return response()->json([
                'success' => false,
                'message' => 'No payment gateway is configured.',
            ], 422);
        }

        $gateway = $validated['gateway'] ?? null;
        if (count($available) === 1) {
            $gateway = $available[0];
        } else {
            if (! is_string($gateway) || ! in_array($gateway, $available, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Choose a payment method (Paynow or Pesepay).',
                ], 422);
            }
        }

        $user = Auth::user();
        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        $block = $this->validatePlanForPaidCheckout($plan);
        if ($block !== null) {
            return $block;
        }

        $access = app(SubscriptionAccessService::class);
        if ($access->userHasActiveAccessToPlan($user, $plan)) {
            return response()->json([
                'success' => false,
                'message' => 'You are already subscribed to this plan. No payment needed.',
            ], 422);
        }

        if ($gateway === 'paynow') {
            return $this->startPaynowBody($user, $plan, $paynow);
        }

        return $this->startPesepayBody($user, $plan, $pesepay);
    }

    public function startPaynow(Request $request, PaymentGatewayConfigService $cfg, PaynowCheckoutService $paynow, PesepayCheckoutService $pesepay): JsonResponse
    {
        $request->merge(['gateway' => 'paynow']);

        return $this->startCheckout($request, $cfg, $paynow, $pesepay);
    }

    public function startPesepay(Request $request, PaymentGatewayConfigService $cfg, PaynowCheckoutService $paynow, PesepayCheckoutService $pesepay): JsonResponse
    {
        $request->merge(['gateway' => 'pesepay']);

        return $this->startCheckout($request, $cfg, $paynow, $pesepay);
    }

    private function validatePlanForPaidCheckout(Plan $plan): ?JsonResponse
    {
        $fulfillment = app(SubscriptionFulfillmentService::class);
        if (! $fulfillment->requiresOnlinePayment($plan)) {
            return response()->json([
                'success' => false,
                'message' => 'This plan does not require online checkout.',
            ], 422);
        }

        if ($plan->is_lifetime && $plan->hasReachedLifetimeCap()) {
            return response()->json([
                'success' => false,
                'message' => 'This lifetime deal has reached its subscriber limit.',
            ], 422);
        }

        return null;
    }

    private function startPaynowBody(User $user, Plan $plan, PaynowCheckoutService $checkout): JsonResponse
    {
        try {
            $returnUrl = route('plans.paynow.return');
            $resultUrl = url('/paynow/result');
            $payload   = $checkout->startHostedCheckout($user, $plan, $returnUrl, $resultUrl);
        } catch (\Throwable $e) {
            Log::warning('Paynow checkout start failed', [
                'user_id'   => $user->id,
                'plan_slug' => $plan->slug,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not start checkout. Please try again or contact support.',
            ], 422);
        }

        return response()->json([
            'success'      => true,
            'redirect_url' => $payload['redirect_url'],
            'reference'    => $payload['reference'],
        ]);
    }

    private function startPesepayBody(User $user, Plan $plan, PesepayCheckoutService $checkout): JsonResponse
    {
        try {
            $returnUrl = route('plans.pesepay.return');
            $resultUrl = url('/pesepay/result');
            $payload   = $checkout->startHostedCheckout($user, $plan, $returnUrl, $resultUrl);
        } catch (\Throwable $e) {
            Log::warning('Pesepay checkout start failed', [
                'user_id'   => $user->id,
                'plan_slug' => $plan->slug,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not start checkout. Please try again or contact support.',
            ], 422);
        }

        return response()->json([
            'success'      => true,
            'redirect_url' => $payload['redirect_url'],
            'reference'    => $payload['reference'],
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

    public function pesepayReturn(Request $request, PesepayCheckoutService $checkout): RedirectResponse
    {
        $user = Auth::user();
        $ref  = $request->query('reference');
        if (! is_string($ref) || $ref === '') {
            $mr = $request->query('merchantReference');
            $ref = is_string($mr) ? $mr : '';
        }

        if (! is_string($ref) || $ref === '') {
            $latest = PaymentTransaction::query()
                ->where('user_id', $user->id)
                ->where('gateway', 'pesepay')
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

        return redirect()->route('plans')->with('info', 'Payment is still processing. This page will update when Pesepay confirms.');
    }
}
