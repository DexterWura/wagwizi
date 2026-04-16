<?php

namespace App\Services\Billing;

use Codevirtus\Payments\Pesepay;

final class PesepayClientFactory
{
    public function __construct(
        private PaymentGatewayConfigService $gatewayConfig
    ) {}

    public function make(): ?Pesepay
    {
        $creds = $this->gatewayConfig->pesepayCredentials();
        if ($creds === null) {
            return null;
        }

        if (! class_exists(Pesepay::class)) {
            $autoload = base_path('gateways/Pesepay/autoloader.php');
            if (! is_file($autoload)) {
                return null;
            }
            require_once $autoload;
        }

        $cfg = $this->gatewayConfig->all()['pesepay'] ?? [];
        $mode = strtolower(trim((string) ($cfg['mode'] ?? 'live')));
        $baseUrl = $mode === 'sandbox'
            ? 'https://api.test.sandbox.pesepay.com/payments-engine'
            : 'https://api.pesepay.com/api/payments-engine';

        return new Pesepay($creds['integration_key'], $creds['encryption_key'], $baseUrl);
    }
}
