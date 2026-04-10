<?php

namespace App\Services\Billing;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Str;

final class StripeCheckoutService
{
    public function __construct(
        private StripeClientFactory $clientFactory,
        private SubscriptionFulfillmentService $fulfillment,
        private CurrencyDisplayService $currency
    ) {}

    public function startHostedCheckout(User $user, Plan $plan, string $successBaseUrl, string $cancelBaseUrl, string $billingInterval = 'monthly'): array
    {
        $billingInterval = $billingInterval === 'yearly' ? 'yearly' : 'monthly';
        $amountCents = $this->fulfillment->planChargeAmountCents($plan, $billingInterval);
        if ($amountCents === null || $amountCents < 1) {
            throw new \InvalidArgumentException('Plan has no billable amount.');
        }

        $creds = $this->clientFactory->credentials();
        if ($creds === null || ! $this->clientFactory->bootstrapSdk()) {
            throw new \RuntimeException('Stripe is not configured.');
        }

        \Stripe\Stripe::setApiKey($creds['secret_key']);

        $reference = 'ST-' . Str::lower(Str::random(10)) . '-' . $user->id;

        $checkoutCurrency = $this->currency->resolvePaynowCheckoutCurrency();
        $checkoutMajor = $this->currency->convertBaseMinorToCurrencyMajor($amountCents, $checkoutCurrency);
        $chargedMinor = $this->currency->minorUnitsFromMajor($checkoutMajor);

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'stripe',
            'reference' => $reference,
            'amount_cents' => $chargedMinor,
            'currency' => $checkoutCurrency,
            'status' => 'pending',
            'meta' => [
                'base_amount_cents' => $amountCents,
                'base_currency' => $this->currency->baseCurrency(),
                'billing_interval' => $billingInterval,
            ],
        ]);

        $successUrl = $successBaseUrl
            . (str_contains($successBaseUrl, '?') ? '&' : '?')
            . 'reference=' . urlencode($reference)
            . '&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $cancelBaseUrl
            . (str_contains($cancelBaseUrl, '?') ? '&' : '?')
            . 'reference=' . urlencode($reference)
            . '&cancelled=1';

        try {
            /** @var \Stripe\Checkout\Session $session */
            $session = \Stripe\Checkout\Session::create([
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => $reference,
                'customer_email' => (string) $user->email,
                'metadata' => [
                    'reference' => $reference,
                    'user_id' => (string) $user->id,
                    'plan_slug' => $plan->slug,
                    'billing_interval' => $billingInterval,
                ],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($checkoutCurrency),
                        'unit_amount' => $chargedMinor,
                        'product_data' => [
                            'name' => $this->fulfillment->planCheckoutProductTitle($plan, $billingInterval),
                        ],
                    ],
                ]],
            ]);
        } catch (\Throwable $e) {
            $transaction->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_message' => $e->getMessage(),
                'meta' => array_merge($transaction->meta ?? [], ['error' => $e->getMessage()]),
            ]);

            throw $e;
        }

        $meta = $transaction->meta ?? [];
        $meta['stripe_checkout_session_id'] = (string) $session->id;
        $transaction->update([
            'paynow_reference' => (string) $session->id,
            'meta' => $meta,
        ]);

        if (! is_string($session->url ?? null) || $session->url === '') {
            $transaction->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_message' => 'Stripe did not return a checkout URL.',
            ]);
            throw new \RuntimeException('Stripe did not return a checkout URL.');
        }

        return [
            'redirect_url' => $session->url,
            'reference' => $reference,
        ];
    }

    public function handleWebhookPayload(string $payload, ?string $signatureHeader): void
    {
        $creds = $this->clientFactory->credentials();
        if ($creds === null || ! $this->clientFactory->bootstrapSdk()) {
            return;
        }
        if (! is_string($creds['webhook_secret']) || $creds['webhook_secret'] === '') {
            return;
        }
        if (! is_string($signatureHeader) || $signatureHeader === '') {
            return;
        }

        \Stripe\Stripe::setApiKey($creds['secret_key']);

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signatureHeader, $creds['webhook_secret']);
        } catch (\Throwable) {
            return;
        }

        $type = (string) ($event->type ?? '');
        if (! in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            return;
        }

        $session = $event->data->object ?? null;
        if ($session === null) {
            return;
        }

        $this->completeFromSessionObject($session);
    }

    public function tryCompleteFromReturn(PaymentTransaction $transaction, ?string $sessionId = null): bool
    {
        if ($transaction->isCompleted() || ! $transaction->isPending()) {
            return $transaction->isCompleted();
        }

        $creds = $this->clientFactory->credentials();
        if ($creds === null || ! $this->clientFactory->bootstrapSdk()) {
            return false;
        }

        \Stripe\Stripe::setApiKey($creds['secret_key']);

        $sid = is_string($sessionId) && $sessionId !== '' ? $sessionId : (string) (($transaction->meta['stripe_checkout_session_id'] ?? ''));
        if ($sid === '') {
            return false;
        }

        try {
            $session = \Stripe\Checkout\Session::retrieve($sid);
        } catch (\Throwable) {
            return false;
        }

        $this->completeFromSessionObject($session);
        $transaction->refresh();

        return $transaction->isCompleted();
    }

    private function completeFromSessionObject(object $session): void
    {
        $metaReference = '';
        $metadata = $session->metadata ?? null;
        if (is_array($metadata) && isset($metadata['reference'])) {
            $metaReference = (string) $metadata['reference'];
        } elseif (is_object($metadata) && isset($metadata->reference)) {
            $metaReference = (string) $metadata->reference;
        }

        $reference = (string) ($session->client_reference_id ?? $metaReference);
        if ($reference === '') {
            return;
        }

        $transaction = PaymentTransaction::query()
            ->where('reference', $reference)
            ->where('gateway', 'stripe')
            ->first();

        if ($transaction === null || $transaction->isCompleted()) {
            return;
        }

        $paymentStatus = (string) ($session->payment_status ?? '');
        if ($paymentStatus !== 'paid') {
            return;
        }

        $reportedAmount = (int) ($session->amount_total ?? 0);
        if ($reportedAmount > 0 && abs($reportedAmount - (int) $transaction->amount_cents) > 1) {
            return;
        }

        $reportedCurrency = strtoupper((string) ($session->currency ?? ''));
        if ($reportedCurrency !== '' && $reportedCurrency !== strtoupper((string) $transaction->currency)) {
            return;
        }

        $meta = $transaction->meta ?? [];
        $meta['stripe_checkout_session_id'] = (string) ($session->id ?? ($meta['stripe_checkout_session_id'] ?? ''));
        $meta['stripe_payment_intent'] = (string) ($session->payment_intent ?? '');
        $transaction->update([
            'paynow_reference' => (string) ($session->payment_intent ?? $session->id ?? $transaction->paynow_reference),
            'meta' => $meta,
        ]);

        $this->fulfillment->fulfillAfterPayment($transaction->user, $transaction->plan, $transaction);
    }
}

