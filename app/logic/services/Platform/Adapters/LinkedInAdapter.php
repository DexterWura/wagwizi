<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInAdapter extends AbstractPlatformAdapter
{
    protected function baseUrl(): string
    {
        return 'https://api.linkedin.com';
    }

    public function platform(): Platform
    {
        return Platform::LinkedIn;
    }

    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) return $error;

        $text     = $this->resolveContent($content, $platformContent);
        $authorId = $account->platform_user_id;

        if (trim($text) === '' && empty($mediaUrls)) {
            return PublishResult::fail('LinkedIn post cannot be empty.');
        }

        if ($error = $this->validateContentLength($text)) return $error;

        if (!empty($mediaUrls)) {
            if ($error = $this->validateMediaUrls($mediaUrls)) return $error;
            if (count($mediaUrls) > 9) {
                return PublishResult::fail('LinkedIn allows a maximum of 9 images per post.');
            }
        }

        $payload = $this->buildRestPostPayload($authorId, $text, $mediaUrls, $account);

        $response = $this->httpClient($account)
            ->withHeaders($this->linkedInHeaders())
            ->post('/rest/posts', $payload);

        if ($response->status() === 201) {
            $postUrn = $response->header('x-restli-id') ?? $response->header('X-LinkedIn-Id') ?? '';
            $this->logPublishSuccess($postUrn);

            return PublishResult::ok(
                $postUrn,
                "https://www.linkedin.com/feed/update/{$postUrn}/",
            );
        }

        return $this->failFromResponse($response);
    }

    private function buildRestPostPayload(string $authorId, string $text, array $mediaUrls, SocialAccount $account): array
    {
        $payload = [
            'author'           => "urn:li:person:{$authorId}",
            'commentary'       => $text,
            'visibility'       => 'PUBLIC',
            'distribution'     => [
                'feedDistribution'               => 'MAIN_FEED',
                'targetEntities'                 => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState'   => 'PUBLISHED',
        ];

        if (!empty($mediaUrls)) {
            $imageAssets = [];
            foreach ($mediaUrls as $url) {
                $asset = $this->registerAndUploadImage($account, $authorId, $url);
                if ($asset !== null) {
                    $imageAssets[] = ['id' => $asset];
                }
            }

            if (count($imageAssets) !== count($mediaUrls)) {
                throw new \RuntimeException(
                    'LinkedIn media upload failed for one or more images. The post was not published as text-only.'
                );
            }

            $payload['content'] = [
                'multiImage' => [
                    'images' => $imageAssets,
                ],
            ];
        }

        return $payload;
    }

    private function registerAndUploadImage(SocialAccount $account, string $authorId, string $imageUrl): ?string
    {
        $initResponse = $this->httpClient($account)
            ->withHeaders($this->linkedInHeaders())
            ->post('/rest/images?action=initializeUpload', [
                'initializeUploadRequest' => [
                    'owner' => "urn:li:person:{$authorId}",
                ],
            ]);

        if (!$initResponse->successful()) {
            Log::warning('LinkedIn initializeUpload failed', [
                'status' => $initResponse->status(),
                'body'   => mb_substr($initResponse->body(), 0, 500),
            ]);
            return null;
        }

        $uploadUrl = $initResponse->json('value.uploadUrl');
        $imageUrn  = $initResponse->json('value.image');

        $imageContent = $this->downloadSafeMedia($imageUrl);
        if ($imageContent === null) {
            Log::warning('LinkedIn media download blocked/failed', [
                'image_url' => $imageUrl,
            ]);
            return null;
        }

        $uploadResponse = Http::withToken($account->access_token)
            ->withBody($imageContent, 'application/octet-stream')
            ->put($uploadUrl);

        if (!$uploadResponse->successful()) {
            Log::warning('LinkedIn image upload failed', [
                'status' => $uploadResponse->status(),
                'body'   => mb_substr($uploadResponse->body(), 0, 500),
            ]);
        }

        return $uploadResponse->successful() ? $imageUrn : null;
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        $response = $this->httpClient($account)
            ->withHeaders($this->linkedInHeaders())
            ->delete("/rest/posts/{$platformPostId}");

        return $response->successful();
    }

    public function refreshToken(string $refreshToken): TokenResult
    {
        $creds = $this->oauthClientCredentials();

        $response = Http::asForm()
            ->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id'     => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
            ]);

        if (!$response->successful()) {
            return TokenResult::fail('Failed to refresh LinkedIn token: ' . $response->body());
        }

        $data = $response->json();

        return TokenResult::ok(
            accessToken:  $data['access_token'],
            refreshToken: $data['refresh_token'] ?? $refreshToken,
            expiresAt:    isset($data['expires_in'])
                ? Carbon::now()->addSeconds($data['expires_in'])
                : null,
        );
    }

    public function publishComment(SocialAccount $account, string $platformPostId, string $comment): bool
    {
        $message = trim($comment);
        $target = $this->normalizeLinkedInPostUrn($platformPostId);

        if ($message === '' || $target === null) {
            return false;
        }

        $actorId = trim((string) $account->platform_user_id);
        if ($actorId === '') {
            return false;
        }

        $response = $this->httpClient($account)
            ->withHeaders($this->linkedInHeaders())
            ->post('/rest/socialActions/' . rawurlencode($target) . '/comments', [
                'actor'   => "urn:li:person:{$actorId}",
                'message' => [
                    'text' => $message,
                ],
            ]);

        return $response->successful();
    }

    private function normalizeLinkedInPostUrn(string $platformPostId): ?string
    {
        $value = trim($platformPostId);
        if ($value === '') {
            return null;
        }

        $decoded = rawurldecode($value);

        if (str_starts_with($decoded, 'urn:li:ugcPost:') || str_starts_with($decoded, 'urn:li:share:')) {
            return $decoded;
        }

        if (str_starts_with($decoded, 'ugcPost:')) {
            return 'urn:li:' . $decoded;
        }

        if (preg_match('/^\d+$/', $decoded) === 1) {
            return 'urn:li:ugcPost:' . $decoded;
        }

        return null;
    }

    /**
     * LinkedIn version header changes over time; keep it configurable.
     *
     * @return array<string, string>
     */
    private function linkedInHeaders(): array
    {
        $headers = [
            'X-Restli-Protocol-Version' => '2.0.0',
        ];

        $version = trim((string) config('platforms.linkedin.api_version', ''));
        if ($version !== '') {
            $headers['LinkedIn-Version'] = $version;
        }

        return $headers;
    }
}
