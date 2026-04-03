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
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'paynow' => [
                'enabled'         => false,
                'integration_id'  => '',
                'integration_key' => '',
            ],
            'stripe' => [
                'enabled'         => false,
                'publishable_key' => '',
                'secret_key'      => '',
                'webhook_secret'  => '',
            ],
        ];
    }
}
