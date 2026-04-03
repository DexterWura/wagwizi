<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Resolved credentials for one composer AI call (BYOK vs platform billing).
 */
final class ComposerAiCredentials
{
    public function __construct(
        public readonly string $billingSource,
        public readonly string $provider,
        public readonly string $apiKey,
        public readonly string $apiBaseUrl,
        public readonly string $model,
    ) {}
}
