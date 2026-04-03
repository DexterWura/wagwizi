<?php

namespace App\Services\Billing;

use App\Models\Plan;

final class CurrencyDisplayService
{
    public function __construct(
        private PaymentGatewayConfigService $gatewayConfig
    ) {}

    public function baseCurrency(): string
    {
        $c = strtoupper(trim((string) ($this->pricing()['base_currency'] ?? 'USD')));

        return strlen($c) === 3 ? $c : 'USD';
    }

    public function defaultCurrency(): string
    {
        $c = strtoupper(trim((string) ($this->pricing()['default_currency'] ?? 'USD')));

        return strlen($c) === 3 ? $c : 'USD';
    }

    /**
     * Units of $currency per one unit of base currency (base row must be 1).
     *
     * @return array<string, float>
     */
    public function exchangeRates(): array
    {
        $raw = $this->pricing()['exchange_rates'] ?? [];
        if (! is_array($raw)) {
            return [$this->baseCurrency() => 1.0];
        }
        $out = [];
        foreach ($raw as $k => $v) {
            if (! is_string($k) && ! is_int($k)) {
                continue;
            }
            $code = strtoupper(trim((string) $k));
            if (strlen($code) !== 3) {
                continue;
            }
            if (! is_numeric($v)) {
                continue;
            }
            $f = (float) $v;
            if ($f > 0) {
                $out[$code] = $f;
            }
        }
        $out[$this->baseCurrency()] = 1.0;

        return $out;
    }

    /** Units of $currency per one unit of base currency. */
    public function rateTo(string $currency): float
    {
        $code = strtoupper(trim($currency));
        $rates = $this->exchangeRates();

        return $rates[$code] ?? ($code === $this->baseCurrency() ? 1.0 : 1.0);
    }

    /**
     * @return list<string>
     */
    public function paynowAcceptedCurrencies(): array
    {
        $raw = $this->gatewayConfig->all()['paynow']['accepted_currencies'] ?? ['USD'];
        if (! is_array($raw)) {
            return ['USD'];
        }
        $out = [];
        foreach ($raw as $c) {
            if (! is_string($c) || strlen(trim($c)) !== 3) {
                continue;
            }
            $out[] = strtoupper(trim($c));
        }

        return $out !== [] ? array_values(array_unique($out)) : ['USD'];
    }

    public function resolvePaynowCheckoutCurrency(): string
    {
        $accepted = $this->paynowAcceptedCurrencies();
        $def = $this->defaultCurrency();
        if (in_array($def, $accepted, true)) {
            return $def;
        }

        return $accepted[0];
    }

    /**
     * Convert stored plan amounts (minor units of base currency) to major units in $targetCurrency.
     */
    public function convertBaseMinorToCurrencyMajor(int $baseMinor, string $targetCurrency): float
    {
        $baseMajor = $baseMinor / 100;
        $rBase = $this->rateTo($this->baseCurrency());
        $rTarget = $this->rateTo($targetCurrency);
        if ($rBase <= 0) {
            $rBase = 1.0;
        }

        return $baseMajor * ($rTarget / $rBase);
    }

    public function convertBaseMinorToDefaultMajor(int $baseMinor): float
    {
        return $this->convertBaseMinorToCurrencyMajor($baseMinor, $this->defaultCurrency());
    }

    public function minorUnitsFromMajor(float $major): int
    {
        return (int) round($major * 100);
    }

    public function symbol(string $code): string
    {
        $c = strtoupper(trim($code));

        return match ($c) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            default => $c,
        };
    }

    /**
     * Format a plan monthly price for UI (uses default display currency).
     */
    public function formatBaseMinorForDisplay(?int $baseMinor, bool $integerIfWhole = true): string
    {
        if ($baseMinor === null) {
            return 'Custom';
        }
        $major = $this->convertBaseMinorToDefaultMajor($baseMinor);
        $def = $this->defaultCurrency();
        if ($integerIfWhole && abs($major - round($major)) < 0.001) {
            $num = (string) (int) round($major);
        } else {
            $num = number_format($major, 2, '.', ',');
        }
        $sym = $this->symbol($def);
        if (strlen($sym) === 1) {
            return $sym . $num;
        }

        return $num . ' ' . $def;
    }

    /**
     * @return array{monthly: float, yearly_total: float}
     */
    public function landingPricingMajors(Plan $plan): array
    {
        $monthly = $plan->monthly_price_cents !== null
            ? $this->convertBaseMinorToDefaultMajor((int) $plan->monthly_price_cents)
            : 0.0;
        $yearlyTotal = $plan->yearly_price_cents !== null
            ? $this->convertBaseMinorToDefaultMajor((int) $plan->yearly_price_cents)
            : $monthly * 12;

        return [
            'monthly'       => $monthly,
            'yearly_total'  => $yearlyTotal,
        ];
    }

    private function pricing(): array
    {
        $p = $this->gatewayConfig->all()['pricing'] ?? [];

        return is_array($p) ? $p : [];
    }
}
