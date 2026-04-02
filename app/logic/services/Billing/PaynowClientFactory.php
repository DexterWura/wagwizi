<?php

namespace App\Services\Billing;

use Paynow\Payments\Paynow;

final class PaynowClientFactory
{
    public function __construct(
        private PaymentGatewayConfigService $gatewayConfig
    ) {}

    public function make(string $returnUrl, string $resultUrl): ?Paynow
    {
        $creds = $this->gatewayConfig->paynowCredentials();
        if ($creds === null) {
            return null;
        }

        if (! class_exists(Paynow::class)) {
            $autoload = base_path('gateways/Paynow/autoloader.php');
            if (! is_file($autoload)) {
                return null;
            }
            require_once $autoload;
        }

        return new Paynow(
            $creds['integration_id'],
            $creds['integration_key'],
            $returnUrl,
            $resultUrl
        );
    }

    public function integrationKey(): ?string
    {
        $creds = $this->gatewayConfig->paynowCredentials();

        return isset($creds['integration_key']) ? strtolower($creds['integration_key']) : null;
    }
}
