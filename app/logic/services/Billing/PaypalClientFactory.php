<?php

namespace App\Services\Billing;

final class PaypalClientFactory
{
    private static bool $autoloadRegistered = false;

    public function __construct(
        private PaymentGatewayConfigService $gatewayConfig
    ) {}

    public function makeApiContext(): ?\PayPal\Rest\ApiContext
    {
        $creds = $this->gatewayConfig->paypalCredentials();
        if ($creds === null) {
            return null;
        }
        if (! $this->bootstrapSdk()) {
            return null;
        }

        $apiContext = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                $creds['client_id'],
                $creds['client_secret']
            )
        );

        $apiContext->setConfig([
            'mode' => $creds['mode'],
            'http.ConnectionTimeOut' => 30,
            'log.LogEnabled' => false,
        ]);

        return $apiContext;
    }

    public function bootstrapSdk(): bool
    {
        if (class_exists(\PayPal\Rest\ApiContext::class)) {
            return true;
        }

        if (! self::$autoloadRegistered) {
            $base = base_path('gateways/Paypal/lib');
            spl_autoload_register(static function (string $class) use ($base): void {
                if (! str_starts_with($class, 'PayPal\\')) {
                    return;
                }
                $path = $base . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            });
            self::$autoloadRegistered = true;
        }

        return class_exists(\PayPal\Rest\ApiContext::class);
    }
}

