<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ComposerAiChatService
{
    public function __construct(
        private readonly ComposerAiCredentialResolver $credentials,
        private readonly PlatformAiQuotaService $quota,
    ) {}

    public function complete(User $user, string $userMessage, string $draft): ComposerAiResult
    {
        $creds = $this->credentials->resolve($user);
        $system = $this->systemPrompt();

        $usePlatformReservation = $creds->billingSource === 'platform' && ! $user->isSuperAdmin();
        $reserved = 0;
        if ($usePlatformReservation) {
            $reserved = $this->quota->reservePlatformTokens($user);
        }

        $billedTokens = 0;
        try {
            $isPlatform = $creds->billingSource === 'platform';

            if ($creds->provider === 'anthropic') {
                $maxAnthropicOut = $isPlatform
                    ? max(256, (int) config('ai.platform.anthropic_max_tokens', 2048))
                    : 2048;
                [$text, $tokens] = $this->callAnthropic($creds, $system, $userMessage, $draft, $maxAnthropicOut);
            } else {
                $maxCompletion = $isPlatform
                    ? max(256, (int) config('ai.platform.max_completion_tokens', 2048))
                    : null;
                [$text, $tokens] = $this->callOpenAiCompatible($creds, $system, $userMessage, $draft, $maxCompletion);
            }

            $billedTokens = $tokens;

            return new ComposerAiResult(
                reply: $text,
                totalTokens: $tokens,
                billingSource: $creds->billingSource,
            );
        } finally {
            if ($usePlatformReservation) {
                $this->quota->finalizePlatformTokenReservation($user, $reserved, $billedTokens);
            }
        }
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
You are a concise social media writing assistant. The user edits a "master" draft for multiple networks.
Follow their instruction (tone, length, hashtags, CTA, platform quirks). Prefer a single ready-to-post revision.
If they only asked for ideas or bullets, answer directly. Do not mention API keys or billing.
TXT;
    }

    private function userPayload(string $userMessage, string $draft): string
    {
        $draft = trim($draft);
        if ($draft === '') {
            return $userMessage;
        }

        return "Current master draft:\n---\n{$draft}\n---\n\nRequest:\n{$userMessage}";
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function callOpenAiCompatible(
        ComposerAiCredentials $creds,
        string $system,
        string $userMessage,
        string $draft,
        ?int $maxCompletionTokens,
    ): array {
        $url = $creds->apiBaseUrl.'/chat/completions';
        $payload = [
            'model'    => $creds->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $this->userPayload($userMessage, $draft)],
            ],
        ];
        if ($maxCompletionTokens !== null) {
            $payload['max_tokens'] = $maxCompletionTokens;
        }

        $response = Http::withOptions(['allow_redirects' => false])
            ->withToken($creds->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(60)
            ->post($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI-compatible API error: HTTP '.$response->status());
        }

        $json = $response->json();
        $content = data_get($json, 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Empty response from language model.');
        }

        $tokens = $this->parseOpenAiUsage($json);

        return [trim($content), $tokens];
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function parseOpenAiUsage(?array $json): int
    {
        if (! is_array($json)) {
            return 1;
        }

        $total = data_get($json, 'usage.total_tokens');
        if (is_numeric($total)) {
            return max(1, (int) $total);
        }

        $prompt     = (int) data_get($json, 'usage.prompt_tokens', 0);
        $completion = (int) data_get($json, 'usage.completion_tokens', 0);
        $sum        = $prompt + $completion;

        return $sum > 0 ? $sum : 1;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function callAnthropic(
        ComposerAiCredentials $creds,
        string $system,
        string $userMessage,
        string $draft,
        int $maxTokens,
    ): array {
        $url = $creds->apiBaseUrl.'/messages';
        $response = Http::withOptions(['allow_redirects' => false])
            ->withHeaders([
                'x-api-key'         => $creds->apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->acceptJson()
            ->asJson()
            ->timeout(60)
            ->post($url, [
                'model'      => $creds->model,
                'max_tokens' => $maxTokens,
                'system'     => $system,
                'messages'   => [
                    ['role' => 'user', 'content' => $this->userPayload($userMessage, $draft)],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic API error: HTTP '.$response->status());
        }

        $json = $response->json();
        $blocks = data_get($json, 'content');
        if (! is_array($blocks)) {
            throw new RuntimeException('Unexpected Anthropic response shape.');
        }

        $text = '';
        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $text .= (string) $block['text'];
            }
        }

        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('Empty response from language model.');
        }

        $in  = (int) data_get($json, 'usage.input_tokens', 0);
        $out = (int) data_get($json, 'usage.output_tokens', 0);
        $tokens = $in + $out > 0 ? $in + $out : 1;

        return [$text, $tokens];
    }
}
