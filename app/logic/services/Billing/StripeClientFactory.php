<?php

namespace App\Services\Billing;

final class StripeClientFactory
{
    public function __construct(
        private PaymentGatewayConfigService $gatewayConfig
    ) {}

    public function bootstrapSdk(): bool
    {
        if (! class_exists(\Stripe\Stripe::class)) {
            $autoload = base_path('gateways/stripe/init.php');
            if (! is_file($autoload)) {
                return false;
            }
            require_once $autoload;
        }

        return class_exists(\Stripe\Stripe::class);
    }

    /**
     * @return array{publishable_key: string, secret_key: string, webhook_secret: string}|null
     */
    public function credentials(): ?array
    {
        return $this->gatewayConfig->stripeCredentials();
    }
}

