<?php

namespace App\Services\Billing;

use App\Models\SiteSetting;

final class PaymentGatewayConfigService
{
    private const KEY = 'payment_gateways';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $raw = SiteSetting::getJson(self::KEY, []);

        return array_replace_recursive($this->defaults(), is_array($raw) ? $raw : []);
    }

    /**
     * @param array<string, array<string, mixed>> $gateways
     */
    public function save(array $gateways): void
    {
        SiteSetting::setJson(self::KEY, array_replace_recursive($this->defaults(), $gateways));
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
     * @return array<string, array<string, mixed>>
     */
    private function defaults(): array
    {
        return [
            'paynow' => [
                'enabled'          => false,
                'integration_id'   => '',
                'integration_key'  => '',
            ],
            'stripe' => [
                'enabled'        => false,
                'publishable_key' => '',
                'secret_key'     => '',
                'webhook_secret' => '',
            ],
        ];
    }
}
