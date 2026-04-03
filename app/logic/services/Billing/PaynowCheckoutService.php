<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Str;
use Paynow\Core\StatusResponse;
use Paynow\Util\Hash;

final class PaynowCheckoutService
{
    public function __construct(
        private PaynowClientFactory $clientFactory,
        private SubscriptionFulfillmentService $fulfillment,
        private CurrencyDisplayService $currency
    ) {}

    /**
     * @return array{redirect_url: string, reference: string}
     */
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
        $payment->add($plan->name . ' subscription', $amountFloat);

        $response = $paynow->send($payment);

        if (! $response->success()) {
            $err = $response->errors();
            $transaction->update(['status' => 'failed', 'meta' => ['error' => $err ?: 'unknown']]);
            throw new \RuntimeException($err ?: 'Paynow initiation failed.');
        }

        $redirect = $response->redirectUrl();
        if (! is_string($redirect) || $redirect === '') {
            $transaction->update(['status' => 'failed']);
            throw new \RuntimeException('Paynow did not return a redirect URL.');
        }

        $transaction->update([
            'poll_url' => $response->pollUrl() ?: null,
        ]);

        return [
            'redirect_url' => $redirect,
            'reference'    => $reference,
        ];
    }

    /**
     * Verify Paynow server callback and complete the subscription if paid.
     */
    public function handleResultPost(array $payload): void
    {
        $key = $this->clientFactory->integrationKey();
        if ($key === null || ! isset($payload['hash'])) {
            return;
        }

        if (! Hash::verify($payload, $key)) {
            return;
        }

        $status = new StatusResponse($payload);
        if (! $status->paid()) {
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
            return;
        }

        if (! $this->paynowReportedAmountMatchesTransaction($status, $transaction)) {
            return;
        }

        $plan = $transaction->plan;
        $user = $transaction->user;

        $transaction->update([
            'paynow_reference' => $status->paynowReference() ?: null,
        ]);

        $this->fulfillment->fulfillAfterPayment($user, $plan, $transaction);
    }

    /**
     * When the customer returns in-browser, poll Paynow and fulfill if paid.
     */
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

    /**
     * Reject fulfillment when Paynow reports a paid amount that does not match our pending transaction.
     * If amount is absent in the payload (-1), we rely on hash verification and paid status only.
     */
    private function paynowReportedAmountMatchesTransaction(StatusResponse $status, PaymentTransaction $transaction): bool
    {
        $reported = $status->amount();
        if ($reported < 0) {
            return true;
        }

        $expectedMajor = $transaction->amount_cents / 100;

        return abs((float) $reported - $expectedMajor) <= 0.02;
    }
}
