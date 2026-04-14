<?php

namespace App\Services\Platform;

use App\Models\Plan;
use App\Models\SiteSetting;
use InvalidArgumentException;

class PlatformRegistry
{
    /** @var array<string, PlatformAdapterInterface> */
    private array $adapters = [];
    /** @var string[]|null */
    private ?array $cachedAdminEnabledSlugs = null;

    public function register(PlatformAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->platform()->value] = $adapter;
    }

    public function resolve(Platform $platform): PlatformAdapterInterface
    {
        $key = $platform->value;

        if (!isset($this->adapters[$key])) {
            throw new InvalidArgumentException("No adapter registered for platform: {$key}");
        }

        if (!$this->isEnabled($platform)) {
            throw new InvalidArgumentException("Platform is disabled: {$key}");
        }

        return $this->adapters[$key];
    }

    public function resolveBySlug(string $slug): PlatformAdapterInterface
    {
        $platform = Platform::tryFrom($slug);

        if ($platform === null) {
            throw new InvalidArgumentException("Unknown platform slug: {$slug}");
        }

        return $this->resolve($platform);
    }

    public function isEnabled(Platform $platform): bool
    {
        $configEnabled = config("platforms.{$platform->value}.enabled", false);
        if (!$configEnabled) {
            return false;
        }

        $adminEnabled = $this->adminEnabledSlugs();
        if (empty($adminEnabled)) {
            return true;
        }

        return in_array($platform->value, $adminEnabled, true);
    }

    /** @return Platform[] */
    public function enabledPlatforms(): array
    {
        return array_values(
            array_filter(
                Platform::cases(),
                fn (Platform $p) => $this->isEnabled($p) && isset($this->adapters[$p->value])
            )
        );
    }

    /**
     * Platforms enabled globally AND allowed by the given plan.
     * @return Platform[]
     */
    public function enabledForPlan(?Plan $plan): array
    {
        $enabled = $this->enabledPlatforms();

        if ($plan === null) {
            return $enabled;
        }

        return array_values(
            array_filter($enabled, fn (Platform $p) => $plan->allowsPlatform($p->value))
        );
    }

    /** @return array<string, PlatformAdapterInterface> */
    public function all(): array
    {
        return $this->adapters;
    }

    /** @return string[] */
    private function adminEnabledSlugs(): array
    {
        if (is_array($this->cachedAdminEnabledSlugs)) {
            return $this->cachedAdminEnabledSlugs;
        }

        try {
            $this->cachedAdminEnabledSlugs = SiteSetting::getJson('enabled_platforms', []);
        } catch (\Throwable) {
            $this->cachedAdminEnabledSlugs = [];
        }

        return $this->cachedAdminEnabledSlugs;
    }
}
