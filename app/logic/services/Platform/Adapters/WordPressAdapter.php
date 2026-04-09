<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressAdapter extends AbstractPlatformAdapter
{
    protected function baseUrl(): string
    {
        return '';
    }

    public function platform(): Platform
    {
        return Platform::WordPress;
    }

    /**
     * WordPress uses per-site base URLs stored in the account metadata,
     * authenticated via Basic Auth with an Application Password.
     */
    private function wpClient(SocialAccount $account): PendingRequest
    {
        $siteUrl = rtrim($account->metadata['site_url'] ?? '', '/');
        $username = $account->metadata['wp_username'] ?? '';

        return Http::baseUrl($siteUrl . '/wp-json/wp/v2')
            ->withBasicAuth($username, $account->access_token)
            ->timeout(30)
            ->retry(2, 500)
            ->acceptJson();
    }

    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
        ?string       $audience = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) return $error;

        $siteUrl = $account->metadata['site_url'] ?? '';
        if (empty($siteUrl)) {
            return PublishResult::fail('No WordPress site URL configured. Please reconnect.');
        }

        $text = $this->resolveContent($content, $platformContent);

        if (trim($text) === '') {
            return PublishResult::fail('Post content cannot be empty.');
        }

        if ($error = $this->validateContentLength($text)) return $error;

        if (!empty($mediaUrls)) {
            if ($error = $this->validateMediaUrls($mediaUrls)) return $error;
        }

        $title = $this->extractTitle($text);
        $body  = $this->formatBody($text, $title);

        $payload = [
            'title'   => $title,
            'content' => $body,
            'status'  => 'publish',
            'format'  => 'standard',
        ];

        $featuredMediaId = null;
        if (!empty($mediaUrls)) {
            $featuredMediaId = $this->uploadFeaturedImage($account, $mediaUrls[0]);
            if ($featuredMediaId === null) {
                return PublishResult::fail('WordPress media upload failed. Post was not published without the selected media.');
            }
            $payload['featured_media'] = $featuredMediaId;
        }

        $response = $this->wpClient($account)->post('/posts', $payload);

        if (!$response->successful()) {
            return $this->failFromWpResponse($response);
        }

        $postData = $response->json();
        $postId   = (string) ($postData['id'] ?? '');
        $postLink = $postData['link'] ?? null;

        $this->logPublishSuccess($postId);

        return PublishResult::ok($postId, $postLink);
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        $response = $this->wpClient($account)
            ->delete('/posts/' . $platformPostId, [
                'force' => true,
            ]);

        return $response->successful();
    }

    /**
     * Application Passwords don't expire — return the existing token as-is.
     */
    public function refreshToken(string $refreshToken): TokenResult
    {
        return TokenResult::ok($refreshToken);
    }

    /**
     * Validate that the WordPress credentials work by hitting the
     * /wp-json/wp/v2/users/me endpoint.
     */
    public function validateCredentials(string $siteUrl, string $username, string $appPassword): array
    {
        $siteUrl = rtrim($siteUrl, '/');

        $response = Http::withBasicAuth($username, $appPassword)
            ->timeout(15)
            ->acceptJson()
            ->get($siteUrl . '/wp-json/wp/v2/users/me', [
                'context' => 'edit',
            ]);

        if (!$response->successful()) {
            $status = $response->status();
            if ($status === 401 || $status === 403) {
                return ['valid' => false, 'error' => 'Invalid username or application password.'];
            }
            return ['valid' => false, 'error' => 'Could not connect to ' . $siteUrl . ' (HTTP ' . $status . ').'];
        }

        $data = $response->json();

        return [
            'valid'    => true,
            'id'       => (string) ($data['id'] ?? ''),
            'username' => $data['slug'] ?? $data['username'] ?? $username,
            'name'     => $data['name'] ?? $username,
            'avatar'   => $data['avatar_urls']['96'] ?? $data['avatar_urls']['48'] ?? null,
        ];
    }

    private function extractTitle(string $text): string
    {
        $firstLine = strtok($text, "\n");

        if ($firstLine !== false && mb_strlen(trim($firstLine)) > 0) {
            return mb_substr(trim($firstLine), 0, 200);
        }

        return mb_substr($text, 0, 80);
    }

    private function formatBody(string $text, string $title): string
    {
        $body = $text;

        if (str_starts_with($body, $title)) {
            $body = mb_substr($body, mb_strlen($title));
            $body = ltrim($body, "\n\r ");
        }

        $paragraphs = preg_split('/\n{2,}/', $body);
        if ($paragraphs === false) {
            return '<p>' . nl2br(e($body)) . '</p>';
        }

        $html = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p !== '') {
                $html .= '<p>' . nl2br(e($p)) . '</p>';
            }
        }

        return $html ?: '<p>' . nl2br(e($body)) . '</p>';
    }

    private function uploadFeaturedImage(SocialAccount $account, string $imageUrl): ?int
    {
        try {
            $imageResponse = Http::timeout(30)->get($imageUrl);
            if (!$imageResponse->successful()) return null;

            $contentType = $imageResponse->header('Content-Type') ?? 'image/jpeg';
            $extension = match (true) {
                str_contains($contentType, 'png')  => 'png',
                str_contains($contentType, 'gif')  => 'gif',
                str_contains($contentType, 'webp') => 'webp',
                default                            => 'jpg',
            };
            $filename = 'postai-' . time() . '.' . $extension;

            $siteUrl  = rtrim($account->metadata['site_url'] ?? '', '/');
            $username = $account->metadata['wp_username'] ?? '';

            $uploadResponse = Http::withBasicAuth($username, $account->access_token)
                ->timeout(30)
                ->withHeaders([
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Content-Type'        => $contentType,
                ])
                ->withBody($imageResponse->body(), $contentType)
                ->post($siteUrl . '/wp-json/wp/v2/media');

            if ($uploadResponse->successful()) {
                return $uploadResponse->json('id');
            }
        } catch (\Throwable $e) {
            Log::warning('WordPress featured image upload failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function failFromWpResponse(\Illuminate\Http\Client\Response $response): PublishResult
    {
        $body = $response->json() ?? [];
        $message = $body['message']
            ?? $body['error_description']
            ?? 'WordPress returned HTTP ' . $response->status();

        $code = $body['code'] ?? null;
        if ($code === 'rest_cannot_create') {
            $message = 'Your Application Password does not have permission to create posts.';
        }

        Log::warning('WordPress publish failed', [
            'status' => $response->status(),
            'body'   => mb_substr($response->body(), 0, 500),
        ]);

        return PublishResult::fail($message, $response->status());
    }
}
