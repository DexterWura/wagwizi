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
        ?string       $audience = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) return $error;

        $text     = $this->resolveContent($content, $platformContent);
        $authorUrn = $this->linkedInAuthorUrn($account);
        if ($authorUrn === null) {
            return PublishResult::fail('LinkedIn destination is misconfigured. Reconnect this account.');
        }
        $ownerUrn = $this->linkedInOwnerUrn($account, $authorUrn);

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

        $payload = $this->buildUgcPostPayload($authorUrn, $ownerUrn, $text, $mediaUrls, $account, $audience);

        $response = $this->httpClient($account)
            ->withHeaders($this->linkedInHeaders())
            ->post('/v2/ugcPosts', $payload);

        if ($response->status() === 201) {
            $postUrn = $this->normalizePostedUrn(
                $response->header('x-restli-id') ?? $response->header('X-RestLi-Id') ?? ''
            );

            $this->logPublishSuccess($postUrn);

            return PublishResult::ok(
                $postUrn,
                $postUrn !== '' ? "https://www.linkedin.com/feed/update/{$postUrn}/" : null,
            );
        }

        return $this->failFromResponse($response);
    }

    private function buildUgcPostPayload(string $authorUrn, string $ownerUrn, string $text, array $mediaUrls, SocialAccount $account, ?string $audience): array
    {
        $shareMediaCategory = 'NONE';
        $mediaPayload = [];

        if (!empty($mediaUrls)) {
            $shareMediaCategory = 'IMAGE';

            foreach ($mediaUrls as $url) {
                $asset = $this->registerAndUploadImage($account, $ownerUrn, $url);
                if ($asset !== null) {
                    $mediaPayload[] = [
                        'status' => 'READY',
                        'media'  => $asset,
                    ];
                }
            }

            if (count($mediaPayload) !== count($mediaUrls)) {
                throw new \RuntimeException(
                    'LinkedIn media upload failed for one or more images. The post was not published as text-only.'
                );
            }
        }

        $payload = [
            'author'         => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'   => ['text' => $text],
                    'shareMediaCategory' => $shareMediaCategory,
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $this->linkedInVisibilityFromAudience($audience),
            ],
        ];

        if ($shareMediaCategory === 'IMAGE') {
            $payload['specificContent']['com.linkedin.ugc.ShareContent']['media'] = $mediaPayload;
        }

        return $payload;
    }

    private function linkedInVisibilityFromAudience(?string $audience): string
    {
        return match (strtolower((string) $audience)) {
            'connections' => 'CONNECTIONS',
            default => 'PUBLIC',
        };
    }

    private function registerAndUploadImage(SocialAccount $account, string $ownerUrn, string $imageUrl): ?string
    {
        $initResponse = $this->httpClient($account)
            ->withHeaders($this->linkedInHeaders())
            ->post('/v2/assets?action=registerUpload', [
                'registerUploadRequest' => [
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner' => $ownerUrn,
                    'serviceRelationships' => [[
                        'relationshipType' => 'OWNER',
                        'identifier' => 'urn:li:userGeneratedContent',
                    ]],
                ],
            ]);

        if (!$initResponse->successful()) {
            Log::warning('LinkedIn initializeUpload failed', [
                'status' => $initResponse->status(),
                'body'   => mb_substr($initResponse->body(), 0, 500),
            ]);
            return null;
        }

        $initData = $initResponse->json();
        $value = is_array($initData['value'] ?? null) ? $initData['value'] : [];
        $uploadMechanism = is_array($value['uploadMechanism'] ?? null) ? $value['uploadMechanism'] : [];
        $httpUpload = is_array($uploadMechanism['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest'] ?? null)
            ? $uploadMechanism['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']
            : [];

        $uploadUrl = $httpUpload['uploadUrl'] ?? null;
        $imageUrn  = $value['asset'] ?? null;

        if (!is_string($uploadUrl) || trim($uploadUrl) === '' || !is_string($imageUrn) || trim($imageUrn) === '') {
            Log::warning('LinkedIn initializeUpload missing upload metadata', [
                'status' => $initResponse->status(),
                'body'   => mb_substr($initResponse->body(), 0, 800),
                'has_value' => is_array($value),
                'upload_mechanism_keys' => array_keys($uploadMechanism),
            ]);
            return null;
        }

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

    private function normalizePostedUrn(string $raw): string
    {
        $value = trim(rawurldecode($raw));
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'urn:li:')) {
            return $value;
        }

        if (str_starts_with($value, 'ugcPost:') || str_starts_with($value, 'share:')) {
            return 'urn:li:' . $value;
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return 'urn:li:ugcPost:' . $value;
        }

        return $value;
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

        $actorUrn = $this->linkedInAuthorUrn($account);
        if ($actorUrn === null) {
            return false;
        }
        $encodedTarget = rawurlencode($target);

        // LinkedIn comment APIs can vary by app/version; try known-compatible shapes.
        $attempts = [
            [
                'endpoint' => '/v2/socialActions/' . $encodedTarget . '/comments',
                'payload' => [
                    'actor' => $actorUrn,
                    'message' => ['text' => $message],
                ],
                'headers' => [],
            ],
            [
                'endpoint' => '/rest/socialActions/' . $encodedTarget . '/comments',
                'payload' => [
                    'actor' => $actorUrn,
                    'message' => ['text' => $message],
                ],
                'headers' => $this->linkedInHeaders(),
            ],
            [
                'endpoint' => '/rest/socialActions/' . $encodedTarget . '/comments',
                'payload' => [
                    'actor' => $actorUrn,
                    'commentary' => $message,
                ],
                'headers' => $this->linkedInHeaders(),
            ],
        ];

        foreach ($attempts as $attempt) {
            $req = $this->httpClient($account);
            $headers = $attempt['headers'] ?? [];
            if (is_array($headers) && $headers !== []) {
                $req = $req->withHeaders($headers);
            }

            $response = $req->post($attempt['endpoint'], $attempt['payload']);
            if ($response->successful()) {
                return true;
            }

            $status = $response->status();
            $body = mb_substr($response->body(), 0, 500);
            $bodyLower = strtolower($response->body());

            if (
                $status === 403
                && (
                    str_contains($bodyLower, 'access_denied')
                    || str_contains($bodyLower, 'not enough permissions')
                    || str_contains($bodyLower, 'partnerapisocia')
                )
            ) {
                throw new \RuntimeException(
                    'LinkedIn denied first-comment publishing due to missing permissions. Reconnect this LinkedIn account to grant comment permissions, or remove first comment for LinkedIn.'
                );
            }

            Log::warning('LinkedIn first-comment attempt failed', [
                'endpoint' => $attempt['endpoint'],
                'status' => $status,
                'body' => $body,
            ]);
        }

        return false;
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

        $version = trim((string) config("platforms.{$this->platform()->value}.api_version", ''));
        if ($version !== '') {
            $headers['LinkedIn-Version'] = $version;
        }

        return $headers;
    }

    private function linkedInAuthorUrn(SocialAccount $account): ?string
    {
        $metadata = is_array($account->metadata) ? $account->metadata : [];
        $stored = trim((string) ($metadata['author_urn'] ?? ''));
        if ($stored !== '' && str_starts_with($stored, 'urn:li:')) {
            return $stored;
        }

        $id = trim((string) $account->platform_user_id);
        if ($id === '') {
            return null;
        }

        $accountType = strtolower(trim((string) ($metadata['account_type'] ?? 'person')));
        if ($accountType === 'organization') {
            return "urn:li:organization:{$id}";
        }

        return "urn:li:person:{$id}";
    }

    private function linkedInOwnerUrn(SocialAccount $account, string $fallback): string
    {
        $metadata = is_array($account->metadata) ? $account->metadata : [];
        $stored = trim((string) ($metadata['owner_urn'] ?? ''));
        if ($stored !== '' && str_starts_with($stored, 'urn:li:')) {
            return $stored;
        }

        return $fallback;
    }
}
