<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\User;
use RuntimeException;

/**
 * Chooses whose API key and endpoint to use:
 * - BYOK: always the user's encrypted key + their provider / base URL (never the platform key).
 * - Platform: server OPENAI_* config only, and only for entitled subscribers (enforced before resolve).
 */
final class ComposerAiCredentialResolver
{
    public function __construct(
        private readonly PlatformAiConfigService $platformAiConfig,
        private readonly AiOutboundUrlValidator $outboundUrl,
    ) {}

    public function resolve(User $user): ComposerAiCredentials
    {
        if ($user->ai_source === 'byok' && $user->hasAiApiKeyStored()) {
            return $this->resolveByok($user);
        }

        return $this->resolvePlatform($user);
    }

    private function resolveByok(User $user): ComposerAiCredentials
    {
        $key = $user->ai_api_key;
        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('BYOK is selected but no API key is stored.');
        }

        $key = trim($key);
        $provider = in_array($user->ai_provider, ['openai', 'anthropic', 'gemini', 'custom'], true)
            ? $user->ai_provider
            : 'openai';

        if ($provider === 'anthropic') {
            $base = (string) config('ai.byok.anthropic_base_url');

            return new ComposerAiCredentials(
                'byok',
                'anthropic',
                $key,
                $base,
                (string) config('ai.byok.anthropic_model'),
            );
        }

        if ($provider === 'custom') {
            $base = trim((string) ($user->ai_base_url ?? ''));
            if ($base === '') {
                throw new RuntimeException('Custom provider requires an API base URL in settings.');
            }

            $this->outboundUrl->assertSafeForServerSideHttp($base);

            return new ComposerAiCredentials(
                'byok',
                'openai_compatible',
                $key,
                rtrim($base, '/'),
                (string) config('ai.byok.openai_model'),
            );
        }

        if ($provider === 'gemini') {
            $base = (string) config('ai.byok.gemini_base_url');

            return new ComposerAiCredentials(
                'byok',
                'gemini',
                $key,
                rtrim($base, '/'),
                (string) config('ai.byok.gemini_model'),
            );
        }

        $base = (string) config('ai.byok.openai_base_url');

        return new ComposerAiCredentials(
            'byok',
            'openai_compatible',
            $key,
            rtrim($base, '/'),
            (string) config('ai.byok.openai_model'),
        );
    }

    private function resolvePlatform(User $user): ComposerAiCredentials
    {
        if (! $this->platformAiConfig->isConfigured()) {
            throw new RuntimeException('Platform AI is not configured for the selected provider.');
        }

        $provider = (string) config('ai.platform.provider', 'openai');
        if (! in_array($provider, ['openai', 'anthropic', 'gemini'], true)) {
            $provider = 'openai';
        }

        if ($provider === 'anthropic') {
            $key = trim((string) config('ai.platform.anthropic_api_key'));
            $base = (string) config('ai.platform.anthropic_base_url');

            return new ComposerAiCredentials(
                'platform',
                'anthropic',
                $key,
                rtrim($base, '/'),
                (string) config('ai.platform.anthropic_model'),
            );
        }

        if ($provider === 'gemini') {
            $key = trim((string) config('ai.platform.gemini_api_key'));
            $base = (string) config('ai.platform.gemini_base_url');

            return new ComposerAiCredentials(
                'platform',
                'gemini',
                $key,
                rtrim($base, '/'),
                (string) config('ai.platform.gemini_model'),
            );
        }

        $key = trim((string) config('ai.platform.openai_api_key'));
        $base = (string) config('ai.platform.openai_base_url');

        return new ComposerAiCredentials(
            'platform',
            'openai_compatible',
            $key,
            rtrim($base, '/'),
            (string) config('ai.platform.openai_model'),
        );
    }
}
