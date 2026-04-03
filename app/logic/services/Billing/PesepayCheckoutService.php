<?php

namespace App\Services\Billing;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\User;
use Codevirtus\Payments\ErrorResponse;
use Codevirtus\Payments\Response;
use Illuminate\Support\Str;

final class PesepayCheckoutService
{
    public function __construct(
        private PesepayClientFactory $clientFactory,
        private SubscriptionFulfillmentService $fulfillment,
        private CurrencyDisplayService $currency
    ) {}

    public function startHostedCheckout(User $user, Plan $plan, string $returnUrl, string $resultUrl): array
    {
        $amountCents = $this->fulfillment->planChargeAmountCents($plan);
        if ($amountCents === null || $amountCents < 1) {
            throw new \InvalidArgumentException('Plan has no billable amount.');
        }

        $pesepay = $this->clientFactory->make();
        if ($pesepay === null) {
            throw new \RuntimeException('Pesepay is not configured.');
        }

        $reference = 'PS-' . Str::lower(Str::random(10)) . '-' . $user->id;

        $checkoutCurrency = $this->currency->resolvePaynowCheckoutCurrency();
        $checkoutMajor    = $this->currency->convertBaseMinorToCurrencyMajor($amountCents, $checkoutCurrency);
        $chargedMinor     = $this->currency->minorUnitsFromMajor($checkoutMajor);

        $transaction = PaymentTransaction::create([
            'user_id'      => $user->id,
            'plan_id'      => $plan->id,
            'gateway'      => 'pesepay',
            'reference'    => $reference,
            'amount_cents' => $chargedMinor,
            'currency'     => $checkoutCurrency,
            'status'       => 'pending',
            'meta'         => [
                'base_amount_cents' => $amountCents,
                'base_currency'     => $this->currency->baseCurrency(),
            ],
        ]);

        $pesepay->returnUrl = $returnUrl;
        $pesepay->resultUrl = $resultUrl;

        $amountFloat = round($checkoutMajor, 2);
        $pesepayTx   = $pesepay->createTransaction(
            $amountFloat,
            $checkoutCurrency,
            $plan->name . ' subscription',
            $reference
        );

        $response = $pesepay->initiateTransaction($pesepayTx);

        if ($response instanceof ErrorResponse) {
            $transaction->update(['status' => 'failed', 'meta' => ['error' => $response->message()]]);
            throw new \RuntimeException($response->message());
        }

        if (! $response->success()) {
            $transaction->update(['status' => 'failed']);
            throw new \RuntimeException('Pesepay initiation failed.');
        }

        $redirectUrl = $response->redirectUrl();
        if (! is_string($redirectUrl) || $redirectUrl === '') {
            $transaction->update(['status' => 'failed']);
            throw new \RuntimeException('Pesepay did not return a redirect URL.');
        }

        $pesepayRef = $response->referenceNumber();
        $transaction->update([
            'poll_url'         => $response->pollUrl() ?: null,
            'paynow_reference' => $pesepayRef !== '' ? $pesepayRef : null,
        ]);

        return [
            'redirect_url' => $redirectUrl,
            'reference'    => $reference,
        ];
    }

    public function handleResultPost(array $body): void
    {
        $cipher = $body['payload'] ?? null;
        if (! is_string($cipher) || $cipher === '') {
            return;
        }

        $pesepay = $this->clientFactory->make();
        if ($pesepay === null) {
            return;
        }

        $data = $pesepay->decodeCallbackPayload($cipher);
        if ($data === null) {
            return;
        }

        $this->fulfillIfPaidFromPayload($data);
    }

    private function fulfillIfPaidFromPayload(array $data): void
    {
        $merchantRef = isset($data['merchantReference']) ? (string) $data['merchantReference'] : '';
        if ($merchantRef === '') {
            return;
        }

        $status = strtoupper((string) ($data['transactionStatus'] ?? ''));
        if ($status !== 'SUCCESS') {
            return;
        }

        $transaction = PaymentTransaction::query()
            ->where('reference', $merchantRef)
            ->where('gateway', 'pesepay')
            ->first();

        if ($transaction === null || $transaction->isCompleted()) {
            return;
        }

        if (! $this->payloadAmountMatchesTransaction($data, $transaction)) {
            return;
        }

        $refNum = isset($data['referenceNumber']) ? (string) $data['referenceNumber'] : '';
        if ($refNum !== '') {
            $transaction->update(['paynow_reference' => $refNum]);
        }

        $plan = $transaction->plan;
        $user = $transaction->user;

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

        $pesepay = $this->clientFactory->make();
        if ($pesepay === null) {
            return false;
        }

        try {
            $response = $pesepay->pollTransaction($pollUrl);
        } catch (\Throwable) {
            return false;
        }

        if ($response instanceof ErrorResponse || ! $response->success()) {
            return false;
        }

        if (! $response->paid()) {
            return false;
        }

        if (! $this->pesepayResponseAmountMatchesTransaction($response, $transaction)) {
            return false;
        }

        $refNum = $response->referenceNumber();
        $transaction->update([
            'paynow_reference' => $refNum !== '' ? $refNum : $transaction->paynow_reference,
        ]);

        $this->fulfillment->fulfillAfterPayment($transaction->user, $transaction->plan, $transaction);

        return true;
    }

    private function payloadAmountMatchesTransaction(array $data, PaymentTransaction $transaction): bool
    {
        $details = $data['amountDetails'] ?? null;
        if (! is_array($details)) {
            return true;
        }
        $reported = $details['amount'] ?? null;
        if (! is_numeric($reported)) {
            return true;
        }

        $expectedMajor = $transaction->amount_cents / 100;

        return abs((float) $reported - $expectedMajor) <= 0.02;
    }

    private function pesepayResponseAmountMatchesTransaction(Response $response, PaymentTransaction $transaction): bool
    {
        $reported = $response->amount();
        if ($reported === null) {
            return true;
        }

        $expectedMajor = $transaction->amount_cents / 100;

        return abs((float) $reported - $expectedMajor) <= 0.02;
    }
}
