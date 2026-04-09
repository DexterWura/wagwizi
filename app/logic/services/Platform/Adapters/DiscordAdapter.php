<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordAdapter extends AbstractPlatformAdapter
{
    protected function baseUrl(): string
    {
        return 'https://discord.com/api/v10';
    }

    public function platform(): Platform
    {
        return Platform::Discord;
    }

    /**
     * Discord uses webhook URLs stored in metadata. The access_token holds the
     * full webhook URL so the adapter is self-contained.
     */
    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
        ?string       $audience = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) return $error;

        $webhookUrl = $account->metadata['webhook_url'] ?? '';
        if (empty($webhookUrl)) {
            return PublishResult::fail('No Discord webhook URL configured. Please reconnect.');
        }

        $text = $this->resolveContent($content, $platformContent);

        if (trim($text) === '' && empty($mediaUrls)) {
            return PublishResult::fail('Discord message cannot be empty.');
        }

        if ($error = $this->validateContentLength($text)) return $error;

        if (!empty($mediaUrls)) {
            if ($error = $this->validateMediaUrls($mediaUrls)) return $error;
        }

        $payload = [];

        if (trim($text) !== '') {
            $payload['content'] = $text;
        }

        if (!empty($mediaUrls)) {
            $payload['embeds'] = array_map(fn (string $url) => [
                'image' => ['url' => $url],
            ], $mediaUrls);
        }

        $response = Http::acceptJson()
            ->timeout(30)
            ->retry(2, 500)
            ->post($webhookUrl . '?wait=true', $payload);

        if (!$response->successful()) {
            return $this->failFromDiscordResponse($response);
        }

        $data      = $response->json();
        $messageId = (string) ($data['id'] ?? '');

        $this->logPublishSuccess($messageId);

        return PublishResult::ok($messageId);
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        $webhookUrl = $account->metadata['webhook_url'] ?? '';
        if (empty($webhookUrl)) {
            return false;
        }

        $response = Http::timeout(15)
            ->delete($webhookUrl . '/messages/' . $platformPostId);

        return $response->successful();
    }

    /**
     * Discord webhooks are permanent — no token refresh needed.
     */
    public function refreshToken(string $refreshToken): TokenResult
    {
        return TokenResult::ok($refreshToken);
    }

    /**
     * Validate a webhook URL by sending a GET request.
     * Discord returns webhook metadata (id, name, channel_id, guild_id, etc.).
     */
    public function validateWebhook(string $webhookUrl): array
    {
        $response = Http::acceptJson()
            ->timeout(15)
            ->get($webhookUrl);

        if (!$response->successful()) {
            $status = $response->status();
            if ($status === 401 || $status === 404) {
                return ['valid' => false, 'error' => 'Invalid webhook URL. Please check and try again.'];
            }
            return ['valid' => false, 'error' => 'Could not reach Discord (HTTP ' . $status . ').'];
        }

        $data = $response->json();

        if (empty($data['id'])) {
            return ['valid' => false, 'error' => 'Unexpected response from Discord. This may not be a valid webhook URL.'];
        }

        return [
            'valid'        => true,
            'webhook_id'   => (string) $data['id'],
            'name'         => $data['name'] ?? 'Discord Webhook',
            'channel_id'   => (string) ($data['channel_id'] ?? ''),
            'guild_id'     => (string) ($data['guild_id'] ?? ''),
            'avatar'       => isset($data['avatar'])
                ? "https://cdn.discordapp.com/avatars/{$data['id']}/{$data['avatar']}.png"
                : null,
        ];
    }

    private function failFromDiscordResponse(\Illuminate\Http\Client\Response $response): PublishResult
    {
        $body    = $response->json() ?? [];
        $message = $body['message']
            ?? $body['error']
            ?? 'Discord returned HTTP ' . $response->status();

        $code = $body['code'] ?? null;
        if ($code === 10015) {
            $message = 'This webhook no longer exists. Please reconnect with a new webhook URL.';
        }

        Log::warning('Discord publish failed', [
            'status' => $response->status(),
            'body'   => mb_substr($response->body(), 0, 500),
        ]);

        return PublishResult::fail($message, $response->status());
    }
}
