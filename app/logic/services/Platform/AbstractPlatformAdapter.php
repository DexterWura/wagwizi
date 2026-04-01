<?php

namespace App\Services\Platform;

use App\Models\SocialAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractPlatformAdapter implements PlatformAdapterInterface
{
    abstract protected function baseUrl(): string;

    protected function httpClient(?SocialAccount $account = null): PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl())
            ->timeout(30)
            ->retry(2, 500)
            ->acceptJson();

        if ($account !== null) {
            $client = $client->withToken($account->access_token);
        }

        return $client;
    }

    protected function resolveContent(string $masterContent, ?string $platformContent): string
    {
        return $platformContent ?? $masterContent;
    }

    protected function platformConfig(): array
    {
        return config("platforms.{$this->platform()->value}", []);
    }

    protected function validateAccount(SocialAccount $account): ?PublishResult
    {
        if ($account->status !== 'active') {
            return PublishResult::fail("Account is not active (status: {$account->status}).");
        }

        if (empty($account->access_token)) {
            return PublishResult::fail('Account has no access token. Please reconnect.');
        }

        if (empty($account->platform_user_id)) {
            return PublishResult::fail('Account is missing a platform user ID. Please reconnect.');
        }

        return null;
    }

    protected function validateContentLength(string $text): ?PublishResult
    {
        $maxLength = $this->platformConfig()['max_content_length'] ?? null;

        if ($maxLength !== null && mb_strlen($text) > $maxLength) {
            $label = $this->platform()->label();
            return PublishResult::fail(
                "{$label} content exceeds the {$maxLength}-character limit (got " . mb_strlen($text) . ').'
            );
        }

        return null;
    }

    protected function validateMediaUrls(array $mediaUrls): ?PublishResult
    {
        foreach ($mediaUrls as $url) {
            if (!is_string($url) || trim($url) === '') {
                return PublishResult::fail('One or more media URLs are empty or invalid.');
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return PublishResult::fail("Invalid media URL: {$url}");
            }
        }

        return null;
    }

    protected function validateVideoSupport(array $mediaUrls): ?PublishResult
    {
        $supportsVideo = $this->platformConfig()['supports_video'] ?? false;

        if (!$supportsVideo) {
            foreach ($mediaUrls as $url) {
                if ($this->looksLikeVideo($url)) {
                    return PublishResult::fail($this->platform()->label() . ' does not support video uploads.');
                }
            }
        }

        return null;
    }

    protected function looksLikeVideo(string $url): bool
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['mp4', 'mov', 'avi', 'wmv', 'webm', 'mkv']);
    }

    protected function failFromResponse(Response $response): PublishResult
    {
        $body    = $response->json() ?? [];
        $message = $body['error']['message']
            ?? $body['error_description']
            ?? $body['detail']
            ?? $body['message']
            ?? 'Platform returned HTTP ' . $response->status();

        Log::warning('Platform publish failed', [
            'platform' => $this->platform()->value,
            'status'   => $response->status(),
            'body'     => mb_substr($response->body(), 0, 500),
        ]);

        return PublishResult::fail($message, $response->status());
    }

    protected function logPublishSuccess(string $platformPostId): void
    {
        Log::info('Published to platform', [
            'platform' => $this->platform()->value,
            'post_id'  => $platformPostId,
        ]);
    }

    protected function oauthTokenUrl(): string
    {
        return '';
    }

    protected function oauthClientCredentials(): array
    {
        $config = $this->platformConfig();

        return [
            'client_id'     => $config['client_id'] ?? '',
            'client_secret' => $config['client_secret'] ?? '',
        ];
    }

    /**
     * Download media only from safe, public, allowlisted hosts.
     */
    protected function downloadSafeMedia(string $url): ?string
    {
        if (!$this->isSafePublicUrl($url)) {
            Log::warning('Blocked unsafe media URL', [
                'platform' => $this->platform()->value,
                'url' => $url,
            ]);
            return null;
        }

        $response = Http::timeout(60)->retry(2, 500)->get($url);
        if (!$response->successful()) {
            return null;
        }

        return $response->body();
    }

    protected function isSafePublicUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($scheme, ['https', 'http'], true) || $host === '') {
            return false;
        }

        $allowedHosts = $this->allowedMediaHosts();
        if (!in_array($host, $allowedHosts, true)) {
            return false;
        }

        $ips = gethostbynamel($host) ?: [];
        foreach ($ips as $ip) {
            $isPublic = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($isPublic === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    protected function allowedMediaHosts(): array
    {
        $hosts = [];
        $urls = [
            config('app.url'),
            config('app.asset_url'),
            config('filesystems.disks.public.url'),
        ];

        foreach ($urls as $url) {
            if (!is_string($url) || trim($url) === '') {
                continue;
            }
            $host = parse_url($url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $hosts[] = strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }

    public function publishComment(SocialAccount $account, string $platformPostId, string $comment): bool
    {
        return false;
    }
}
