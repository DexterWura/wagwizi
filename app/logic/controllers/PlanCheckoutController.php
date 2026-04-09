<?php

namespace App\Controllers;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\PaymentGatewayConfigService;
use App\Services\Billing\PaynowCheckoutService;
use App\Services\Billing\PesepayCheckoutService;
use App\Services\Billing\PaypalCheckoutService;
use App\Services\Billing\StripeCheckoutService;
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
        PesepayCheckoutService $pesepay,
        StripeCheckoutService $stripe,
        PaypalCheckoutService $paypal
    ): JsonResponse {
        $validated = $request->validate([
            'plan_slug' => ['required', 'string', Rule::exists('plans', 'slug')->where('is_active', true)],
            'gateway'   => 'nullable|string|in:paynow,pesepay,stripe,paypal',
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
                    'message' => 'Choose a valid payment method before checkout.',
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

        Log::info('Plan checkout requested', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_slug' => $plan->slug,
            'gateway' => $gateway,
        ]);

        if ($gateway === 'paynow') {
            return $this->startPaynowBody($user, $plan, $paynow);
        }
        if ($gateway === 'stripe') {
            return $this->startStripeBody($user, $plan, $stripe);
        }
        if ($gateway === 'paypal') {
            return $this->startPaypalBody($user, $plan, $paypal);
        }

        return $this->startPesepayBody($user, $plan, $pesepay);
    }

    public function startPaynow(Request $request, PaymentGatewayConfigService $cfg, PaynowCheckoutService $paynow, PesepayCheckoutService $pesepay, StripeCheckoutService $stripe, PaypalCheckoutService $paypal): JsonResponse
    {
        $request->merge(['gateway' => 'paynow']);

        return $this->startCheckout($request, $cfg, $paynow, $pesepay, $stripe, $paypal);
    }

    public function startPesepay(Request $request, PaymentGatewayConfigService $cfg, PaynowCheckoutService $paynow, PesepayCheckoutService $pesepay, StripeCheckoutService $stripe, PaypalCheckoutService $paypal): JsonResponse
    {
        $request->merge(['gateway' => 'pesepay']);

        return $this->startCheckout($request, $cfg, $paynow, $pesepay, $stripe, $paypal);
    }

    public function startStripe(Request $request, PaymentGatewayConfigService $cfg, PaynowCheckoutService $paynow, PesepayCheckoutService $pesepay, StripeCheckoutService $stripe, PaypalCheckoutService $paypal): JsonResponse
    {
        $request->merge(['gateway' => 'stripe']);

        return $this->startCheckout($request, $cfg, $paynow, $pesepay, $stripe, $paypal);
    }

    public function startPaypal(Request $request, PaymentGatewayConfigService $cfg, PaynowCheckoutService $paynow, PesepayCheckoutService $pesepay, StripeCheckoutService $stripe, PaypalCheckoutService $paypal): JsonResponse
    {
        $request->merge(['gateway' => 'paypal']);

        return $this->startCheckout($request, $cfg, $paynow, $pesepay, $stripe, $paypal);
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

        Log::info('Plan checkout redirect created', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_slug' => $plan->slug,
            'gateway' => 'paynow',
            'reference' => $payload['reference'],
        ]);

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

        Log::info('Plan checkout redirect created', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_slug' => $plan->slug,
            'gateway' => 'pesepay',
            'reference' => $payload['reference'],
        ]);

        return response()->json([
            'success'      => true,
            'redirect_url' => $payload['redirect_url'],
            'reference'    => $payload['reference'],
        ]);
    }

    private function startStripeBody(User $user, Plan $plan, StripeCheckoutService $checkout): JsonResponse
    {
        try {
            $successUrl = route('plans.stripe.return');
            $cancelUrl = route('plans');
            $payload = $checkout->startHostedCheckout($user, $plan, $successUrl, $cancelUrl);
        } catch (\Throwable $e) {
            Log::warning('Stripe checkout start failed', [
                'user_id' => $user->id,
                'plan_slug' => $plan->slug,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not start Stripe checkout. Please try again or contact support.',
            ], 422);
        }

        Log::info('Plan checkout redirect created', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_slug' => $plan->slug,
            'gateway' => 'stripe',
            'reference' => $payload['reference'],
        ]);

        return response()->json([
            'success' => true,
            'redirect_url' => $payload['redirect_url'],
            'reference' => $payload['reference'],
        ]);
    }

    private function startPaypalBody(User $user, Plan $plan, PaypalCheckoutService $checkout): JsonResponse
    {
        try {
            $returnUrl = route('plans.paypal.return');
            $cancelUrl = route('plans');
            $payload = $checkout->startHostedCheckout($user, $plan, $returnUrl, $cancelUrl);
        } catch (\Throwable $e) {
            $paypalDetail = $this->paypalCheckoutStartErrorDetail($e);
            $paypalMeta   = $this->paypalCheckoutStartErrorMeta($e);
            Log::warning('PayPal checkout start failed', [
                'user_id'       => $user->id,
                'plan_slug'     => $plan->slug,
                'message'       => $e->getMessage(),
                'paypal_detail' => $paypalDetail,
                'paypal_meta'   => $paypalMeta,
                'exception'     => $e,
            ]);

            $message = $this->paypalCheckoutUserMessage($paypalMeta, $paypalDetail);
            if (config('app.debug')) {
                $message .= ' (' . $paypalDetail . ')';
            }

            $payload = [
                'success' => false,
                'message' => $message,
            ];
            if ($paypalMeta['paypal_error_name'] !== '') {
                $payload['paypal_error_name'] = $paypalMeta['paypal_error_name'];
            }
            if ($paypalMeta['paypal_error_issue'] !== '') {
                $payload['paypal_error_issue'] = $paypalMeta['paypal_error_issue'];
            }

            return response()->json($payload, 422);
        }

        Log::info('Plan checkout redirect created', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_slug' => $plan->slug,
            'gateway' => 'paypal',
            'reference' => $payload['reference'],
        ]);

        return response()->json([
            'success' => true,
            'redirect_url' => $payload['redirect_url'],
            'reference' => $payload['reference'],
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

    public function stripeReturn(Request $request, StripeCheckoutService $checkout): RedirectResponse
    {
        $user = Auth::user();
        $ref = $request->query('reference');
        $sessionId = $request->query('session_id');

        if (! is_string($ref) || $ref === '') {
            $latest = PaymentTransaction::query()
                ->where('user_id', $user->id)
                ->where('gateway', 'stripe')
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->first();

            if ($latest) {
                $checkout->tryCompleteFromReturn($latest, is_string($sessionId) ? $sessionId : null);
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

        $checkout->tryCompleteFromReturn($transaction, is_string($sessionId) ? $sessionId : null);
        $transaction->refresh();

        if ($transaction->isCompleted()) {
            return redirect()->route('plans')->with('success', 'Payment received. Your plan is active.');
        }

        return redirect()->route('plans')->with('info', 'Payment is still processing. This page will update when Stripe confirms.');
    }

    public function paypalReturn(Request $request, PaypalCheckoutService $checkout): RedirectResponse
    {
        $user = Auth::user();
        $ref = $request->query('reference');
        $paymentId = $request->query('paymentId');
        $payerId = $request->query('PayerID');

        if (! is_string($ref) || $ref === '') {
            $latest = PaymentTransaction::query()
                ->where('user_id', $user->id)
                ->where('gateway', 'paypal')
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->first();

            if ($latest) {
                $checkout->tryCompleteFromReturn(
                    $latest,
                    is_string($paymentId) ? $paymentId : null,
                    is_string($payerId) ? $payerId : null
                );
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

        $checkout->tryCompleteFromReturn(
            $transaction,
            is_string($paymentId) ? $paymentId : null,
            is_string($payerId) ? $payerId : null
        );
        $transaction->refresh();

        if ($transaction->isCompleted()) {
            return redirect()->route('plans')->with('success', 'Payment received. Your plan is active.');
        }

        return redirect()->route('plans')->with('info', 'Payment is still processing. This page will update when PayPal confirms.');
    }

    /**
     * Extract PayPal REST error JSON (when present) for logs and APP_DEBUG responses.
     */
    private function paypalCheckoutStartErrorDetail(\Throwable $e): string
    {
        if ($e instanceof \PayPal\Exception\PayPalConnectionException) {
            $data = $e->getData();
            if (is_string($data) && $data !== '') {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    $oauthErr = trim((string) ($decoded['error'] ?? ''));
                    $oauthDesc = trim((string) ($decoded['error_description'] ?? ''));
                    if ($oauthErr !== '') {
                        $parts = array_filter([$oauthErr, $oauthDesc], static fn (string $p): bool => $p !== '');

                        return $parts !== [] ? implode(' — ', $parts) : mb_substr($data, 0, 400);
                    }

                    $name = trim((string) ($decoded['name'] ?? ''));
                    $msg = trim((string) ($decoded['message'] ?? ''));
                    $inner = '';
                    $details = $decoded['details'] ?? null;
                    if (is_array($details) && isset($details[0]) && is_array($details[0])) {
                        $d = $details[0];
                        $inner = trim((string) ($d['issue'] ?? ''));
                        if ($inner === '') {
                            $inner = trim((string) ($d['description'] ?? ''));
                        }
                    }
                    $parts = array_filter([$name, $msg, $inner], static fn (string $p): bool => $p !== '');

                    return $parts !== [] ? implode(' — ', $parts) : mb_substr($data, 0, 400);
                }

                return mb_substr($data, 0, 400);
            }
        }

        return $e->getMessage();
    }

    /**
     * Safe subset of PayPal’s response for the client (no secrets). Helps diagnose live issues without APP_DEBUG.
     *
     * @return array{paypal_error_name: string, paypal_error_issue: string}
     */
    private function paypalCheckoutStartErrorMeta(\Throwable $e): array
    {
        $out = ['paypal_error_name' => '', 'paypal_error_issue' => ''];

        if ($e instanceof \PayPal\Exception\PayPalConnectionException) {
            $data = $e->getData();
            if (is_string($data) && $data !== '') {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    $oauthErr = trim((string) ($decoded['error'] ?? ''));
                    if ($oauthErr !== '') {
                        $out['paypal_error_name'] = $oauthErr;
                        $desc = trim((string) ($decoded['error_description'] ?? ''));
                        if ($desc !== '') {
                            $out['paypal_error_issue'] = mb_substr($desc, 0, 240);
                        }

                        return $out;
                    }

                    $out['paypal_error_name'] = trim((string) ($decoded['name'] ?? ''));
                    $details = $decoded['details'] ?? null;
                    if (is_array($details) && isset($details[0]) && is_array($details[0])) {
                        $d = $details[0];
                        $issue = trim((string) ($d['issue'] ?? ''));
                        if ($issue === '') {
                            $issue = trim((string) ($d['description'] ?? ''));
                        }
                        if ($issue !== '') {
                            $out['paypal_error_issue'] = mb_substr($issue, 0, 240);
                        }
                    }
                    if ($out['paypal_error_name'] === '' && $out['paypal_error_issue'] === '') {
                        $msg = trim((string) ($decoded['message'] ?? ''));
                        if ($msg !== '') {
                            $out['paypal_error_issue'] = mb_substr($msg, 0, 240);
                        }
                    }

                    return $out;
                }
            }

            $code = (int) $e->getCode();
            if ($code >= 400 && $code < 600) {
                $out['paypal_error_name'] = 'HTTP_' . $code;
            }

            return $out;
        }

        if ($e instanceof \PayPal\Exception\PayPalConfigurationException) {
            $out['paypal_error_name'] = 'CONFIGURATION';

            return $out;
        }

        if ($e instanceof \PayPal\Exception\PayPalInvalidCredentialException) {
            $out['paypal_error_name'] = 'INVALID_CREDENTIAL';

            return $out;
        }

        if ($e instanceof \PayPal\Exception\PayPalMissingCredentialException) {
            $out['paypal_error_name'] = 'MISSING_CREDENTIAL';

            return $out;
        }

        $out['paypal_error_name'] = 'APP_EXCEPTION';

        return $out;
    }

    /**
     * @param array{paypal_error_name: string, paypal_error_issue: string} $paypalMeta
     */
    private function paypalCheckoutUserMessage(array $paypalMeta, string $paypalDetail): string
    {
        $name = $paypalMeta['paypal_error_name'] ?? '';

        if ($name === 'invalid_client') {
            return 'PayPal could not verify your app (invalid client). Open Admin → Payment gateways, set Mode to Sandbox or Live to match your PayPal Developer app, then paste a fresh Client ID and Client secret from that same app (Apps & Credentials). If the secret was rotated, the old one no longer works.';
        }

        if (in_array($name, ['invalid_grant', 'unauthorized_client'], true)) {
            return 'PayPal rejected the authorization for this app. Confirm Client ID and secret, Sandbox vs Live mode, and that the PayPal app is allowed to request payments.';
        }

        return 'Could not start PayPal checkout. Please try again or contact support.';
    }
}
