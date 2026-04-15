<?php

declare(strict_types=1);

namespace App\Services\Ai;

final class PlatformAiConfigService
{
    public function isConfigured(): bool
    {
        $provider = (string) config('ai.platform.provider', 'openai');
        if ($provider !== 'openai' && $provider !== 'anthropic' && $provider !== 'gemini') {
            $provider = 'openai';
        }

        $key = match ($provider) {
            'anthropic' => config('ai.platform.anthropic_api_key'),
            'gemini' => config('ai.platform.gemini_api_key'),
            default => config('ai.platform.openai_api_key'),
        };

        return is_string($key) && trim($key) !== '';
    }
}
