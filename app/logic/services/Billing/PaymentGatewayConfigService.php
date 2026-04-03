<?php

namespace App\Services\Billing;

use App\Models\BillingCurrencySetting;
use App\Models\SiteSetting;

final class PaymentGatewayConfigService
{
    private const KEY = 'payment_gateways';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $raw = SiteSetting::getJson(self::KEY, []);

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
        SiteSetting::setJson(self::KEY, $merged);
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

    /** @return list<string> */
    public function availableCheckoutGateways(): array
    {
        $out = [];
        if ($this->paynowIsReady()) {
            $out[] = 'paynow';
        }
        if ($this->pesepayIsReady()) {
            $out[] = 'pesepay';
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
            ],
            'stripe'           => [
                'enabled'         => false,
                'publishable_key' => '',
                'secret_key'      => '',
                'webhook_secret'  => '',
            ],
        ];
    }
}
