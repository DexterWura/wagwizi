<?php

declare(strict_types=1);

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BlueskyAdapter extends AbstractPlatformAdapter
{
    protected function baseUrl(): string
    {
        return rtrim((string) ($this->platformConfig()['service_host'] ?? 'https://bsky.social'), '/');
    }

    private function xrpc(string $method): string
    {
        return $this->baseUrl() . '/xrpc/' . $method;
    }

    public function platform(): Platform
    {
        return Platform::Bluesky;
    }

    /**
     * @return array{
     *     valid: bool,
     *     error?: string,
     *     did?: string,
     *     handle?: string,
     *     accessJwt?: string,
     *     refreshJwt?: string,
     *     avatar?: ?string,
     *     expiresAt?: DateTimeInterface
     * }
     */
    public function validateCredentials(string $identifier, string $appPassword): array
    {
        $response = Http::acceptJson()
            ->timeout(25)
            ->post($this->xrpc('com.atproto.server.createSession'), [
                'identifier' => $identifier,
                'password'   => $appPassword,
            ]);

        if (!$response->successful()) {
            return [
                'valid' => false,
                'error' => $this->extractErrorMessage($response->json() ?? [], $response->status()),
            ];
        }

        $data = $response->json();
        $access = (string) ($data['accessJwt'] ?? '');
        $refresh = (string) ($data['refreshJwt'] ?? '');
        $did = (string) ($data['did'] ?? '');
        $handle = (string) ($data['handle'] ?? $identifier);

        if ($access === '' || $refresh === '' || $did === '') {
            return ['valid' => false, 'error' => 'Unexpected response from Bluesky. Please try again.'];
        }

        $avatar = $this->fetchAvatarUrl($access, $handle);

        return [
            'valid'      => true,
            'did'        => $did,
            'handle'     => $handle,
            'accessJwt'  => $access,
            'refreshJwt' => $refresh,
            'avatar'     => $avatar,
            'expiresAt'  => $this->jwtExpiry($access) ?? Carbon::now()->addMinutes(45),
        ];
    }

    private function fetchAvatarUrl(string $accessJwt, string $handle): ?string
    {
        $response = Http::acceptJson()
            ->timeout(15)
            ->withToken($accessJwt)
            ->get($this->xrpc('app.bsky.actor.getProfile'), ['actor' => $handle]);

        if (!$response->successful()) {
            return null;
        }

        $avatar = $response->json('avatar');

        return is_string($avatar) && $avatar !== '' ? $avatar : null;
    }

    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) {
            return $error;
        }

        $text = $this->resolveContent($content, $platformContent);
        $textTrim = trim($text);

        if ($textTrim === '' && $mediaUrls === []) {
            return PublishResult::fail('Bluesky posts need text and/or at least one image.');
        }

        if ($textTrim !== '' && ($error = $this->validateContentLength($text))) {
            return $error;
        }

        if ($mediaUrls !== []) {
            $mediaUrls = array_slice($mediaUrls, 0, 4);
            if ($error = $this->validateMediaUrls($mediaUrls)) {
                return $error;
            }
            if ($error = $this->validateVideoSupport($mediaUrls)) {
                return $error;
            }
        }

        $repo = $account->platform_user_id;

        $record = [
            '$type'     => 'app.bsky.feed.post',
            'createdAt' => $this->blueskyTimestamp(),
            'text'      => $text,
        ];

        if ($mediaUrls !== []) {
            $images = [];
            foreach ($mediaUrls as $url) {
                $blob = $this->uploadImageBlob($account, $url);
                if ($blob === null) {
                    return PublishResult::fail('Could not upload image to Bluesky from: ' . $url);
                }
                $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
                $alt = mb_substr(basename($path) ?: 'Image', 0, 1000);
                $images[] = [
                    'alt'   => $alt,
                    'image' => $blob,
                ];
            }
            $record['embed'] = [
                '$type'  => 'app.bsky.embed.images',
                'images' => $images,
            ];
        }

        $response = Http::acceptJson()
            ->timeout(90)
            ->retry(2, 500)
            ->withToken($account->access_token)
            ->post($this->xrpc('com.atproto.repo.createRecord'), [
                'repo'       => $repo,
                'collection' => 'app.bsky.feed.post',
                'record'     => $record,
            ]);

        if (!$response->successful()) {
            return $this->failFromBlueskyResponse($response);
        }

        $uri = (string) ($response->json('uri') ?? '');
        if ($uri === '') {
            return PublishResult::fail('Bluesky did not return a post URI.');
        }

        $this->logPublishSuccess($uri);

        return PublishResult::ok($uri, $this->uriToWebUrl($account, $uri));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function uploadImageBlob(SocialAccount $account, string $mediaUrl): ?array
    {
        $binary = $this->downloadSafeMedia($mediaUrl);
        if ($binary === null || $binary === '') {
            return null;
        }

        $path = (string) (parse_url($mediaUrl, PHP_URL_PATH) ?: '');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            default => 'image/jpeg',
        };

        $response = Http::withToken($account->access_token)
            ->timeout(90)
            ->withHeaders(['Content-Type' => $mime])
            ->withBody($binary, $mime)
            ->post($this->xrpc('com.atproto.repo.uploadBlob'));

        if (!$response->successful()) {
            Log::warning('Bluesky uploadBlob failed', [
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 400),
            ]);

            return null;
        }

        $blob = $response->json('blob');

        return is_array($blob) ? $blob : null;
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        if ($account->access_token === null || $account->access_token === '') {
            return false;
        }

        $parsed = $this->parseAtUri($platformPostId);
        if ($parsed === null) {
            return false;
        }

        $response = Http::acceptJson()
            ->timeout(25)
            ->withToken($account->access_token)
            ->post($this->xrpc('com.atproto.repo.deleteRecord'), [
                'repo'       => $parsed['repo'],
                'collection' => $parsed['collection'],
                'rkey'       => $parsed['rkey'],
            ]);

        return $response->successful();
    }

    public function refreshToken(string $refreshToken): TokenResult
    {
        if ($refreshToken === '') {
            return TokenResult::fail('Missing Bluesky refresh token.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(25)
            ->withHeaders(['Authorization' => 'Bearer ' . $refreshToken])
            ->post($this->xrpc('com.atproto.server.refreshSession'), []);

        if (!$response->successful()) {
            return TokenResult::fail($this->extractErrorMessage($response->json() ?? [], $response->status()));
        }

        $data = $response->json();
        $access = (string) ($data['accessJwt'] ?? '');
        $newRefresh = (string) ($data['refreshJwt'] ?? $refreshToken);

        if ($access === '') {
            return TokenResult::fail('Bluesky refresh returned no access token.');
        }

        return TokenResult::ok($access, $newRefresh, $this->jwtExpiry($access));
    }

    /**
     * @return array{repo: string, collection: string, rkey: string}|null
     */
    private function parseAtUri(string $uri): ?array
    {
        if (preg_match('#^at://([^/]+)/([^/]+)/([^/]+)$#', $uri, $m)) {
            return ['repo' => $m[1], 'collection' => $m[2], 'rkey' => $m[3]];
        }

        return null;
    }

    private function uriToWebUrl(SocialAccount $account, string $atUri): ?string
    {
        $parsed = $this->parseAtUri($atUri);
        if ($parsed === null) {
            return null;
        }

        $handle = $account->username ?? '';
        if ($handle === '') {
            return null;
        }

        return 'https://bsky.app/profile/' . rawurlencode($handle) . '/post/' . rawurlencode($parsed['rkey']);
    }

    private function blueskyTimestamp(): string
    {
        $c = Carbon::now('UTC');

        return $c->format('Y-m-d\TH:i:s') . substr($c->format('u'), 0, 3) . 'Z';
    }

    private function jwtExpiry(string $jwt): ?DateTimeInterface
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) {
            return null;
        }

        $data = json_decode($payload, true);
        if (!is_array($data) || !isset($data['exp'])) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $data['exp']);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractErrorMessage(array $body, int $status): string
    {
        $msg = $body['message'] ?? null;
        if (is_string($msg) && $msg !== '') {
            return $msg;
        }

        $err = $body['error'] ?? null;
        if (is_string($err) && $err !== '') {
            return $err;
        }

        return 'Bluesky returned HTTP ' . $status;
    }

    private function failFromBlueskyResponse(Response $response): PublishResult
    {
        $body = $response->json() ?? [];

        Log::warning('Bluesky publish failed', [
            'status' => $response->status(),
            'body'   => mb_substr($response->body(), 0, 500),
        ]);

        return PublishResult::fail(
            $this->extractErrorMessage(is_array($body) ? $body : [], $response->status()),
            $response->status()
        );
    }
}
