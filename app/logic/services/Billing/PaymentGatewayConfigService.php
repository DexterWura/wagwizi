<?php

namespace App\Services\Billing;

use App\Models\BillingCurrencySetting;
use App\Models\SiteSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final class PaymentGatewayConfigService
{
    private const KEY = 'payment_gateways';

    private const MERGED_CONFIG_CACHE_KEY = 'billing:merged_gateway_config:v1';

    private const MERGED_CONFIG_TTL_SECONDS = 120;

    /** @var list<string> */
    private const SENSITIVE_DOT_PATHS = [
        'paynow.integration_key',
        'pesepay.integration_key',
        'pesepay.encryption_key',
        'stripe.secret_key',
        'stripe.webhook_secret',
        'paypal.client_secret',
    ];

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember(self::MERGED_CONFIG_CACHE_KEY, self::MERGED_CONFIG_TTL_SECONDS, function (): array {
            return $this->buildMergedConfig();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMergedConfig(): array
    {
        $raw = SiteSetting::getJson(self::KEY, []);
        $raw = $this->decryptSensitiveValues(is_array($raw) ? $raw : []);

        $merged = array_replace_recursive($this->defaults(), is_array($raw) ? $raw : []);
        unset($merged['pricing']);

        $billing = BillingCurrencySetting::query()->first();
        if ($billing !== null) {
            $merged['pricing'] = [
                'base_currency'    => $billing->base_currency,
                'default_currency' => $billing->default_display_currency,
                'exchange_rates'   => is_array($billing->exchange_rates) && $billing->exchange_rates !== []
                    ? $billing->exchange_rates
                    : [$billing->base_currency => 1.0],
            ];
            $merged['paynow']['checkout_currency']   = $billing->paynow_checkout_currency;
            $merged['paynow']['accepted_currencies'] = [$billing->paynow_checkout_currency];
            $pesepayCheckout = strtoupper(trim((string) ($merged['pesepay']['checkout_currency'] ?? '')));
            if (strlen($pesepayCheckout) !== 3) {
                $merged['pesepay']['checkout_currency'] = $billing->paynow_checkout_currency;
            }

            return $merged;
        }

        $legacyPricing = is_array($raw) ? ($raw['pricing'] ?? null) : null;
        if (is_array($legacyPricing)) {
            $merged['pricing'] = array_replace(
                [
                    'base_currency'    => 'USD',
                    'default_currency' => 'USD',
                    'exchange_rates'   => ['USD' => 1.0],
                ],
                $legacyPricing
            );
        } else {
            $merged['pricing'] = [
                'base_currency'    => 'USD',
                'default_currency' => 'USD',
                'exchange_rates'   => ['USD' => 1.0],
            ];
        }

        $paynow = $merged['paynow'] ?? [];
        $checkout = is_string($paynow['checkout_currency'] ?? null)
            ? strtoupper(trim((string) $paynow['checkout_currency']))
            : '';
        if (strlen($checkout) !== 3) {
            $acc = $paynow['accepted_currencies'] ?? ['USD'];
            $checkout = is_array($acc) && isset($acc[0]) && is_string($acc[0])
                ? strtoupper(substr(trim($acc[0]), 0, 3))
                : 'USD';
            if (strlen($checkout) !== 3) {
                $checkout = 'USD';
            }
        }
        $merged['paynow']['checkout_currency']   = $checkout;
        $merged['paynow']['accepted_currencies'] = [$checkout];
        $pesepayCheckout = strtoupper(trim((string) ($merged['pesepay']['checkout_currency'] ?? '')));
        $merged['pesepay']['checkout_currency'] = strlen($pesepayCheckout) === 3 ? $pesepayCheckout : $checkout;

        return $merged;
    }

    /**
     * @param array<string, mixed> $gateways
     */
    public function save(array $gateways): void
    {
        unset($gateways['pricing']);
        $merged = array_replace_recursive($this->defaults(), $gateways);
        unset($merged['pricing']);
        SiteSetting::setJson(self::KEY, $this->encryptSensitiveValues($merged));
        Cache::forget(self::MERGED_CONFIG_CACHE_KEY);
    }

    public function paynowIsReady(): bool
    {
        $p = $this->all()['paynow'] ?? [];

        return ! empty($p['enabled'])
            && is_string($p['integration_id'] ?? null)
            && trim($p['integration_id']) !== ''
            && is_string($p['integration_key'] ?? null)
            && trim($p['integration_key']) !== '';
    }

    public function pesepayIsReady(): bool
    {
        $p = $this->all()['pesepay'] ?? [];
        if (empty($p['enabled'])) {
            return false;
        }
        $key = isset($p['integration_key']) ? trim((string) $p['integration_key']) : '';
        $enc = isset($p['encryption_key']) ? trim((string) $p['encryption_key']) : '';
        $len = strlen($enc);

        return $key !== '' && ($len === 16 || $len === 24 || $len === 32);
    }

    public function stripeIsReady(): bool
    {
        $s = $this->all()['stripe'] ?? [];

        return ! empty($s['enabled'])
            && is_string($s['publishable_key'] ?? null)
            && trim($s['publishable_key']) !== ''
            && is_string($s['secret_key'] ?? null)
            && trim($s['secret_key']) !== ''
            && is_string($s['webhook_secret'] ?? null)
            && trim($s['webhook_secret']) !== '';
    }

    public function paypalIsReady(): bool
    {
        $p = $this->all()['paypal'] ?? [];
        $mode = strtolower(trim((string) ($p['mode'] ?? 'sandbox')));

        return ! empty($p['enabled'])
            && in_array($mode, ['sandbox', 'live'], true)
            && is_string($p['client_id'] ?? null)
            && trim($p['client_id']) !== ''
            && is_string($p['client_secret'] ?? null)
            && trim($p['client_secret']) !== '';
    }

    /** @return list<string> */
    public function availableCheckoutGateways(): array
    {
        return $this->availableCheckoutGatewaysFromConfig($this->all());
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public function availableCheckoutGatewaysFromConfig(array $config): array
    {
        $out = [];
        if ($this->paynowReadyFromConfig($config)) {
            $out[] = 'paynow';
        }
        if ($this->pesepayReadyFromConfig($config)) {
            $out[] = 'pesepay';
        }
        if ($this->stripeReadyFromConfig($config)) {
            $out[] = 'stripe';
        }
        if ($this->paypalReadyFromConfig($config)) {
            $out[] = 'paypal';
        }

        return $out;
    }

    public function checkoutRequiresGatewayChoice(): bool
    {
        return count($this->availableCheckoutGateways()) > 1;
    }

    public function activeCheckoutGateway(): string
    {
        $available = $this->availableCheckoutGateways();
        if ($available === []) {
            return 'none';
        }
        if (count($available) === 1) {
            return $available[0];
        }

        return 'both';
    }

    public function defaultCheckoutGatewayForUi(): string
    {
        $available = $this->availableCheckoutGateways();
        if ($available === []) {
            return 'none';
        }
        if (count($available) === 1) {
            return $available[0];
        }

        $pref = strtolower(trim((string) ($this->all()['checkout_gateway'] ?? 'paynow')));
        if ($pref === 'pesepay' && in_array('pesepay', $available, true)) {
            return 'pesepay';
        }
        if ($pref === 'stripe' && in_array('stripe', $available, true)) {
            return 'stripe';
        }
        if ($pref === 'paypal' && in_array('paypal', $available, true)) {
            return 'paypal';
        }
        if ($pref === 'paynow' && in_array('paynow', $available, true)) {
            return 'paynow';
        }

        return $available[0];
    }

    public function hostedCheckoutAvailable(): bool
    {
        return $this->availableCheckoutGateways() !== [];
    }

    /**
     * @return array{integration_id: string, integration_key: string}|null
     */
    public function paynowCredentials(): ?array
    {
        if (! $this->paynowIsReady()) {
            return null;
        }
        $p = $this->all()['paynow'];

        return [
            'integration_id'  => trim((string) $p['integration_id']),
            'integration_key' => trim((string) $p['integration_key']),
        ];
    }

    /**
     * @return array{integration_key: string, encryption_key: string}|null
     */
    public function pesepayCredentials(): ?array
    {
        if (! $this->pesepayIsReady()) {
            return null;
        }
        $p = $this->all()['pesepay'];

        return [
            'integration_key' => trim((string) $p['integration_key']),
            'encryption_key'    => trim((string) $p['encryption_key']),
        ];
    }

    /**
     * @return array{publishable_key: string, secret_key: string, webhook_secret: string}|null
     */
    public function stripeCredentials(): ?array
    {
        if (! $this->stripeIsReady()) {
            return null;
        }
        $s = $this->all()['stripe'];

        return [
            'publishable_key' => trim((string) $s['publishable_key']),
            'secret_key' => trim((string) $s['secret_key']),
            'webhook_secret' => trim((string) ($s['webhook_secret'] ?? '')),
        ];
    }

    /**
     * @return array{client_id: string, client_secret: string, mode: string}|null
     */
    public function paypalCredentials(): ?array
    {
        if (! $this->paypalIsReady()) {
            return null;
        }

        $p = $this->all()['paypal'];

        return [
            'client_id' => trim((string) $p['client_id']),
            'client_secret' => trim((string) $p['client_secret']),
            'mode' => strtolower(trim((string) ($p['mode'] ?? 'sandbox'))) === 'live' ? 'live' : 'sandbox',
        ];
    }

    public function paypalWebhookId(): ?string
    {
        $p = $this->all()['paypal'] ?? [];
        $id = isset($p['webhook_id']) ? trim((string) $p['webhook_id']) : '';

        return $id !== '' ? $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'checkout_gateway' => 'paynow',
            'paynow'           => [
                'enabled'         => false,
                'integration_id'  => '',
                'integration_key' => '',
            ],
            'pesepay'          => [
                'enabled'         => false,
                'integration_key' => '',
                'encryption_key'  => '',
                'checkout_currency' => '',
            ],
            'stripe'           => [
                'enabled'         => false,
                'publishable_key' => '',
                'secret_key'      => '',
                'webhook_secret'  => '',
            ],
            'paypal'           => [
                'enabled'           => false,
                'client_id'         => '',
                'client_secret'     => '',
                'webhook_id'        => '',
                'mode'              => 'sandbox',
                'checkout_currency' => '',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function paynowReadyFromConfig(array $config): bool
    {
        $p = is_array($config['paynow'] ?? null) ? $config['paynow'] : [];

        return ! empty($p['enabled'])
            && is_string($p['integration_id'] ?? null)
            && trim($p['integration_id']) !== ''
            && is_string($p['integration_key'] ?? null)
            && trim($p['integration_key']) !== '';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function pesepayReadyFromConfig(array $config): bool
    {
        $p = is_array($config['pesepay'] ?? null) ? $config['pesepay'] : [];
        if (empty($p['enabled'])) {
            return false;
        }
        $key = isset($p['integration_key']) ? trim((string) $p['integration_key']) : '';
        $enc = isset($p['encryption_key']) ? trim((string) $p['encryption_key']) : '';
        $len = strlen($enc);

        return $key !== '' && ($len === 16 || $len === 24 || $len === 32);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function stripeReadyFromConfig(array $config): bool
    {
        $s = is_array($config['stripe'] ?? null) ? $config['stripe'] : [];

        return ! empty($s['enabled'])
            && is_string($s['publishable_key'] ?? null)
            && trim($s['publishable_key']) !== ''
            && is_string($s['secret_key'] ?? null)
            && trim($s['secret_key']) !== ''
            && is_string($s['webhook_secret'] ?? null)
            && trim($s['webhook_secret']) !== '';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function paypalReadyFromConfig(array $config): bool
    {
        $p = is_array($config['paypal'] ?? null) ? $config['paypal'] : [];
        $mode = strtolower(trim((string) ($p['mode'] ?? 'sandbox')));

        return ! empty($p['enabled'])
            && in_array($mode, ['sandbox', 'live'], true)
            && is_string($p['client_id'] ?? null)
            && trim($p['client_id']) !== ''
            && is_string($p['client_secret'] ?? null)
            && trim($p['client_secret']) !== '';
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function encryptSensitiveValues(array $config): array
    {
        foreach (self::SENSITIVE_DOT_PATHS as $path) {
            $value = data_get($config, $path);
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $trimmed = trim($value);
            if (str_starts_with($trimmed, 'enc:')) {
                continue;
            }

            data_set($config, $path, 'enc:' . Crypt::encryptString($trimmed));
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function decryptSensitiveValues(array $config): array
    {
        foreach (self::SENSITIVE_DOT_PATHS as $path) {
            $value = data_get($config, $path);
            if (!is_string($value) || $value === '') {
                continue;
            }
            if (!str_starts_with($value, 'enc:')) {
                continue;
            }

            $payload = substr($value, 4);
            try {
                $decrypted = Crypt::decryptString($payload);
            } catch (DecryptException) {
                continue;
            }

            data_set($config, $path, $decrypted);
        }

        return $config;
    }
}
