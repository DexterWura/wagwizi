<?php

declare(strict_types=1);

namespace App\Services\Ai;

final class PlatformAiConfigService
{
    public function isConfigured(): bool
    {
        $key = config('ai.platform.openai_api_key');

        return is_string($key) && trim($key) !== '';
    }
}
