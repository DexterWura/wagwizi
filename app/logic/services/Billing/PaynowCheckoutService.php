<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Paynow\Core\StatusResponse;

final class PaynowCheckoutService
{
    public function __construct(
        private PaynowClientFactory $clientFactory,
        private SubscriptionFulfillmentService $fulfillment,
        private CurrencyDisplayService $currency
    ) {}

    public function startHostedCheckout(User $user, Plan $plan, string $returnUrl, string $resultUrl): array
    {
        $amountCents = $this->fulfillment->planChargeAmountCents($plan);
        if ($amountCents === null || $amountCents < 1) {
            throw new \InvalidArgumentException('Plan has no billable amount.');
        }

        $paynow = $this->clientFactory->make($returnUrl, $resultUrl);
        if ($paynow === null) {
            throw new \RuntimeException('Paynow is not configured.');
        }

        $reference = 'PN-' . Str::lower(Str::random(10)) . '-' . $user->id;

        $checkoutCurrency = $this->currency->resolvePaynowCheckoutCurrency();
        $checkoutMajor    = $this->currency->convertBaseMinorToCurrencyMajor($amountCents, $checkoutCurrency);
        $chargedMinor      = $this->currency->minorUnitsFromMajor($checkoutMajor);

        $transaction = PaymentTransaction::create([
            'user_id'     => $user->id,
            'plan_id'     => $plan->id,
            'gateway'     => 'paynow',
            'reference'   => $reference,
            'amount_cents'=> $chargedMinor,
            'currency'    => $checkoutCurrency,
            'status'      => 'pending',
            'meta'        => [
                'base_amount_cents' => $amountCents,
                'base_currency'     => $this->currency->baseCurrency(),
            ],
        ]);

        $amountFloat = round($checkoutMajor, 2);
        $payment     = $paynow->createPayment($reference, $user->email);
        if (config('services.paynow.send_currency_field', true)) {
            $payment->setCurrency($checkoutCurrency);
        }
        $payment->add($this->fulfillment->planCheckoutProductTitle($plan), $amountFloat);

        $response = $paynow->send($payment);

        if (! $response->success()) {
            $err = $response->errors();
            $msg = $err ?: 'unknown';
            $transaction->update([
                'status'          => 'failed',
                'failed_at'       => now(),
                'failure_message' => $msg,
                'meta'            => ['error' => $msg],
            ]);
            throw new \RuntimeException($err ?: 'Paynow initiation failed.');
        }

        $redirect = $response->redirectUrl();
        if (! is_string($redirect) || $redirect === '') {
            $msg = 'Paynow did not return a redirect URL.';
            $transaction->update([
                'status'          => 'failed',
                'failed_at'       => now(),
                'failure_message' => $msg,
            ]);
            throw new \RuntimeException($msg);
        }

        $transaction->update([
            'poll_url' => $response->pollUrl() ?: null,
        ]);

        return [
            'redirect_url' => $redirect,
            'reference'    => $reference,
        ];
    }

    public function handleResultPost(array $payload): void
    {
        $key = $this->clientFactory->integrationKey();
        if ($key === null || ! isset($payload['hash'])) {
            Log::warning('Paynow webhook ignored: missing key/hash', [
                'has_key' => $key !== null,
                'has_hash' => isset($payload['hash']),
            ]);
            return;
        }

        if (! $this->verifyPayloadHash($payload, $key)) {
            Log::warning('Paynow webhook ignored: hash verification failed', [
                'reference' => isset($payload['reference']) ? (string) $payload['reference'] : null,
            ]);
            return;
        }

        $status = new StatusResponse($payload);
        if (! $status->paid()) {
            Log::info('Paynow webhook received non-paid status', [
                'reference' => $status->reference(),
                'status' => method_exists($status, 'status') ? $status->status() : null,
            ]);
            return;
        }

        $reference = $status->reference();
        if ($reference === '') {
            return;
        }

        $transaction = PaymentTransaction::query()
            ->where('reference', $reference)
            ->where('gateway', 'paynow')
            ->first();

        if ($transaction === null || $transaction->isCompleted()) {
            Log::info('Paynow webhook ignored: transaction missing/completed', [
                'reference' => $reference,
                'found' => $transaction !== null,
                'already_completed' => $transaction?->isCompleted() ?? null,
            ]);
            return;
        }

        if (! $this->paynowReportedAmountMatchesTransaction($status, $transaction)) {
            Log::warning('Paynow webhook amount mismatch', [
                'reference' => $reference,
                'user_id' => $transaction->user_id,
                'transaction_amount_cents' => $transaction->amount_cents,
                'reported_amount' => $status->amount(),
            ]);
            return;
        }

        $plan = $transaction->plan;
        $user = $transaction->user;

        $transaction->update([
            'paynow_reference' => $status->paynowReference() ?: null,
        ]);

        Log::info('Paynow payment confirmed via webhook', [
            'reference' => $reference,
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'plan_id' => $transaction->plan_id,
            'gateway' => $transaction->gateway,
        ]);

        $this->fulfillment->fulfillAfterPayment($user, $plan, $transaction);
    }

    public function tryCompleteFromPoll(PaymentTransaction $transaction): bool
    {
        if ($transaction->isCompleted() || ! $transaction->isPending()) {
            return $transaction->isCompleted();
        }

        $pollUrl = $transaction->poll_url;
        if ($pollUrl === null || $pollUrl === '') {
            return false;
        }

        $paynow = $this->clientFactory->make(url('/plans'), url('/paynow/result'));
        if ($paynow === null) {
            return false;
        }

        try {
            $status = $paynow->pollTransaction($pollUrl);
        } catch (\Throwable) {
            return false;
        }

        if (! $status->paid()) {
            return false;
        }

        if (! $this->paynowReportedAmountMatchesTransaction($status, $transaction)) {
            return false;
        }

        $transaction->update([
            'paynow_reference' => $status->paynowReference() ?: $transaction->paynow_reference,
        ]);

        $this->fulfillment->fulfillAfterPayment($transaction->user, $transaction->plan, $transaction);

        return true;
    }

    private function paynowReportedAmountMatchesTransaction(StatusResponse $status, PaymentTransaction $transaction): bool
    {
        $reported = $status->amount();
        if ($reported < 0) {
            return true;
        }

        $expectedMajor = $transaction->amount_cents / 100;

        return abs((float) $reported - $expectedMajor) <= 0.02;
    }

    private function verifyPayloadHash(array $payload, string $key): bool
    {
        try {
            if (class_exists(\Paynow\Util\Hash::class)) {
                return \Paynow\Util\Hash::verify($payload, $key);
            }
        } catch (\Throwable) {
            // Fall through to local verification to avoid webhook hard-failure.
        }

        $string = '';
        foreach ($payload as $k => $value) {
            if (strtoupper((string) $k) === 'HASH') {
                continue;
            }
            $string .= (string) $value;
        }
        $string .= $key;
        $expected = strtoupper(hash('sha512', $string));
        $provided = strtoupper((string) ($payload['hash'] ?? ''));

        return $provided !== '' && hash_equals($expected, $provided);
    }
}
