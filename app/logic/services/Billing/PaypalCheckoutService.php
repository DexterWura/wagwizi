<?php

namespace App\Services\Billing;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Str;

final class PaypalCheckoutService
{
    public function __construct(
        private PaypalClientFactory $clientFactory,
        private SubscriptionFulfillmentService $fulfillment,
        private CurrencyDisplayService $currency,
        private PaymentGatewayConfigService $gatewayConfig
    ) {}

    public function startHostedCheckout(User $user, Plan $plan, string $returnBaseUrl, string $cancelBaseUrl): array
    {
        $amountCents = $this->fulfillment->planChargeAmountCents($plan);
        if ($amountCents === null || $amountCents < 1) {
            throw new \InvalidArgumentException('Plan has no billable amount.');
        }

        $apiContext = $this->clientFactory->makeApiContext();
        if ($apiContext === null) {
            throw new \RuntimeException('PayPal is not configured.');
        }

        $reference = 'PP-' . Str::lower(Str::random(10)) . '-' . $user->id;

        $checkoutCurrency = $this->currency->resolvePaynowCheckoutCurrency();
        $checkoutMajor = $this->currency->convertBaseMinorToCurrencyMajor($amountCents, $checkoutCurrency);
        $chargedMinor = $this->currency->minorUnitsFromMajor($checkoutMajor);

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'paypal',
            'reference' => $reference,
            'amount_cents' => $chargedMinor,
            'currency' => $checkoutCurrency,
            'status' => 'pending',
            'meta' => [
                'base_amount_cents' => $amountCents,
                'base_currency' => $this->currency->baseCurrency(),
            ],
        ]);

        $returnUrl = $returnBaseUrl
            . (str_contains($returnBaseUrl, '?') ? '&' : '?')
            . 'reference=' . urlencode($reference);
        $cancelUrl = $cancelBaseUrl
            . (str_contains($cancelBaseUrl, '?') ? '&' : '?')
            . 'reference=' . urlencode($reference)
            . '&cancelled=1';

        $payer = new \PayPal\Api\Payer();
        $payer->setPaymentMethod('paypal');

        $amount = new \PayPal\Api\Amount();
        $amount->setCurrency($checkoutCurrency);
        $amount->setTotal(number_format($checkoutMajor, 2, '.', ''));

        $transactionData = new \PayPal\Api\Transaction();
        $transactionData->setAmount($amount);
        $transactionData->setDescription($plan->name . ' subscription');
        $transactionData->setCustom($reference);
        $transactionData->setInvoiceNumber($reference);

        $redirectUrls = new \PayPal\Api\RedirectUrls();
        $redirectUrls->setReturnUrl($returnUrl);
        $redirectUrls->setCancelUrl($cancelUrl);

        $payment = new \PayPal\Api\Payment();
        $payment->setIntent('sale');
        $payment->setPayer($payer);
        $payment->setTransactions([$transactionData]);
        $payment->setRedirectUrls($redirectUrls);

        try {
            $payment->create($apiContext);
        } catch (\Throwable $e) {
            $transaction->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_message' => $e->getMessage(),
                'meta' => array_merge($transaction->meta ?? [], ['error' => $e->getMessage()]),
            ]);
            throw $e;
        }

        $approvalUrl = null;
        foreach ((array) $payment->getLinks() as $link) {
            if ((string) $link->getRel() === 'approval_url') {
                $approvalUrl = (string) $link->getHref();
                break;
            }
        }
        if (! is_string($approvalUrl) || $approvalUrl === '') {
            $transaction->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_message' => 'PayPal did not return an approval URL.',
            ]);
            throw new \RuntimeException('PayPal did not return an approval URL.');
        }

        $transaction->update([
            'paypal_payment_id' => (string) $payment->getId(),
            'paynow_reference' => (string) $payment->getId(),
        ]);

        return [
            'redirect_url' => $approvalUrl,
            'reference' => $reference,
        ];
    }

    public function tryCompleteFromReturn(PaymentTransaction $transaction, ?string $paymentId, ?string $payerId): bool
    {
        if ($transaction->isCompleted() || ! $transaction->isPending()) {
            return $transaction->isCompleted();
        }

        $apiContext = $this->clientFactory->makeApiContext();
        if ($apiContext === null) {
            return false;
        }

        $paypalPaymentId = is_string($paymentId) && $paymentId !== ''
            ? $paymentId
            : (string) ($transaction->paypal_payment_id ?? '');
        $paypalPayerId = is_string($payerId) && $payerId !== ''
            ? $payerId
            : (string) ($transaction->paypal_payer_id ?? '');

        if ($paypalPaymentId === '' || $paypalPayerId === '') {
            return false;
        }
        if (is_string($transaction->paypal_payment_id) && $transaction->paypal_payment_id !== '' && $transaction->paypal_payment_id !== $paypalPaymentId) {
            return false;
        }

        try {
            $payment = \PayPal\Api\Payment::get($paypalPaymentId, $apiContext);
            $execution = new \PayPal\Api\PaymentExecution();
            $execution->setPayerId($paypalPayerId);
            $result = $payment->execute($execution, $apiContext);
        } catch (\Throwable) {
            return false;
        }

        if (strtolower((string) $result->getState()) !== 'approved') {
            return false;
        }

        $txs = (array) $result->getTransactions();
        if ($txs === []) {
            return false;
        }
        $paypalTx = $txs[0];
        $reportedReference = trim((string) ($paypalTx->getCustom() ?: $paypalTx->getInvoiceNumber()));
        if ($reportedReference !== '' && $reportedReference !== (string) $transaction->reference) {
            return false;
        }
        $amt = $paypalTx->getAmount();
        if ($amt === null) {
            return false;
        }

        $reportedCurrency = strtoupper((string) $amt->getCurrency());
        if ($reportedCurrency !== '' && $reportedCurrency !== strtoupper((string) $transaction->currency)) {
            return false;
        }
        $reportedTotal = (float) $amt->getTotal();
        $expectedMajor = ((int) $transaction->amount_cents) / 100;
        if (abs($reportedTotal - $expectedMajor) > 0.02) {
            return false;
        }

        $saleId = '';
        foreach ((array) $paypalTx->getRelatedResources() as $resource) {
            $sale = $resource->getSale();
            if ($sale && is_string($sale->getId()) && $sale->getId() !== '') {
                $saleId = $sale->getId();
                break;
            }
        }

        $transaction->update([
            'paypal_payment_id' => $paypalPaymentId,
            'paypal_payer_id' => $paypalPayerId,
            'paynow_reference' => $saleId !== '' ? $saleId : $paypalPaymentId,
            'meta' => array_merge($transaction->meta ?? [], ['paypal_state' => (string) $result->getState()]),
        ]);

        $this->fulfillment->fulfillAfterPayment($transaction->user, $transaction->plan, $transaction);

        return true;
    }

    /**
     * Verifies the webhook with PayPal, then on PAYMENT.SALE.COMPLETED loads the Payment from the API
     * and fulfills the matching pending transaction (idempotent with the return URL flow).
     */
    public function handleWebhookPayload(
        string $rawBody,
        ?string $authAlgo,
        ?string $certUrl,
        ?string $transmissionId,
        ?string $transmissionSig,
        ?string $transmissionTime
    ): void {
        $webhookId = $this->gatewayConfig->paypalWebhookId();
        if ($webhookId === null) {
            throw new \RuntimeException('PayPal webhook ID is not configured.');
        }

        $apiContext = $this->clientFactory->makeApiContext();
        if ($apiContext === null) {
            throw new \RuntimeException('PayPal is not configured.');
        }

        foreach (
            [
                [$authAlgo, 'auth algo'],
                [$certUrl, 'cert URL'],
                [$transmissionId, 'transmission id'],
                [$transmissionSig, 'transmission sig'],
                [$transmissionTime, 'transmission time'],
            ] as [$v, $label]
        ) {
            if (! is_string($v) || trim($v) === '') {
                throw new \InvalidArgumentException('Missing PayPal webhook header: ' . $label . '.');
            }
        }

        $verify = new \PayPal\Api\VerifyWebhookSignature();
        $verify->setAuthAlgo($authAlgo);
        $verify->setCertUrl($certUrl);
        $verify->setTransmissionId($transmissionId);
        $verify->setTransmissionSig($transmissionSig);
        $verify->setTransmissionTime($transmissionTime);
        $verify->setWebhookId($webhookId);
        $verify->setRequestBody($rawBody);

        $verifyResponse = $verify->post($apiContext);
        if (strtoupper((string) $verifyResponse->getVerificationStatus()) !== 'SUCCESS') {
            throw new \InvalidArgumentException('PayPal webhook signature verification failed.');
        }

        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Invalid PayPal webhook JSON.');
        }

        $eventType = (string) ($decoded['event_type'] ?? '');
        if ($eventType !== 'PAYMENT.SALE.COMPLETED') {
            return;
        }

        $resource = $decoded['resource'] ?? null;
        if (! is_array($resource)) {
            return;
        }

        $parentPaymentId = trim((string) ($resource['parent_payment'] ?? ''));
        $saleId = trim((string) ($resource['id'] ?? ''));
        $saleState = strtolower((string) ($resource['state'] ?? ''));
        if ($parentPaymentId === '' || $saleId === '' || $saleState !== 'completed') {
            return;
        }

        $transaction = PaymentTransaction::query()
            ->where('gateway', 'paypal')
            ->where('paypal_payment_id', $parentPaymentId)
            ->where('status', 'pending')
            ->first();

        if ($transaction === null) {
            return;
        }

        try {
            $payment = \PayPal\Api\Payment::get($parentPaymentId, $apiContext);
        } catch (\Throwable) {
            return;
        }

        if ($this->applyVerifiedPaymentToTransaction($transaction, $payment, $saleId)) {
            $this->fulfillment->fulfillAfterPayment($transaction->user, $transaction->plan, $transaction);
        }
    }

    private function applyVerifiedPaymentToTransaction(
        PaymentTransaction $transaction,
        \PayPal\Api\Payment $payment,
        string $expectedSaleId
    ): bool {
        if ($transaction->isCompleted() || ! $transaction->isPending()) {
            return false;
        }

        if (strtolower((string) $payment->getState()) !== 'approved') {
            return false;
        }

        $txs = (array) $payment->getTransactions();
        if ($txs === []) {
            return false;
        }
        $paypalTx = $txs[0];
        $reportedReference = trim((string) ($paypalTx->getCustom() ?: $paypalTx->getInvoiceNumber()));
        if ($reportedReference !== '' && $reportedReference !== (string) $transaction->reference) {
            return false;
        }
        $amt = $paypalTx->getAmount();
        if ($amt === null) {
            return false;
        }

        $reportedCurrency = strtoupper((string) $amt->getCurrency());
        if ($reportedCurrency !== '' && $reportedCurrency !== strtoupper((string) $transaction->currency)) {
            return false;
        }
        $reportedTotal = (float) $amt->getTotal();
        $expectedMajor = ((int) $transaction->amount_cents) / 100;
        if (abs($reportedTotal - $expectedMajor) > 0.02) {
            return false;
        }

        $matchedSaleId = '';
        foreach ((array) $paypalTx->getRelatedResources() as $resource) {
            $sale = $resource->getSale();
            if ($sale
                && is_string($sale->getId())
                && $sale->getId() === $expectedSaleId
                && strtolower((string) $sale->getState()) === 'completed'
            ) {
                $matchedSaleId = $sale->getId();
                break;
            }
        }
        if ($matchedSaleId === '') {
            return false;
        }

        $payerId = '';
        $payer = $payment->getPayer();
        if ($payer !== null) {
            $info = $payer->getPayerInfo();
            if ($info !== null && is_string($info->getPayerId()) && $info->getPayerId() !== '') {
                $payerId = (string) $info->getPayerId();
            }
        }

        $paypalPaymentId = (string) $payment->getId();
        $transaction->update([
            'paypal_payment_id' => $paypalPaymentId,
            'paypal_payer_id'   => $payerId !== '' ? $payerId : $transaction->paypal_payer_id,
            'paynow_reference'  => $matchedSaleId,
            'meta'              => array_merge($transaction->meta ?? [], [
                'paypal_state'        => (string) $payment->getState(),
                'paypal_webhook_sale' => $matchedSaleId,
            ]),
        ]);

        return true;
    }
}

