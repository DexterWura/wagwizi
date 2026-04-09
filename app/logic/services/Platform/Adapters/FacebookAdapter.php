<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Carbon\Carbon;

class FacebookAdapter extends AbstractPlatformAdapter
{
    private const API_VERSION = 'v21.0';

    protected function baseUrl(): string
    {
        return 'https://graph.facebook.com/' . self::API_VERSION;
    }

    public function platform(): Platform
    {
        return Platform::Facebook;
    }

    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
        ?string       $audience = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) return $error;

        $text   = $this->resolveContent($content, $platformContent);
        $pageId = $account->platform_user_id;

        if (trim($text) === '' && empty($mediaUrls)) {
            return PublishResult::fail('Facebook post cannot be empty.');
        }

        if ($error = $this->validateContentLength($text)) return $error;

        if (!empty($mediaUrls)) {
            if ($error = $this->validateMediaUrls($mediaUrls)) return $error;
        }

        if (!empty($mediaUrls)) {
            $firstMedia = $mediaUrls[0];
            if ($this->isVideoUrl($firstMedia)) {
                return $this->publishWithVideo($account, $pageId, $text, $firstMedia);
            }

            return $this->publishWithPhoto($account, $pageId, $text, $firstMedia);
        }

        $response = $this->httpClient($account)
            ->post("/{$pageId}/feed", [
                'message' => $text,
            ]);

        if (!$response->successful()) {
            return $this->failFromResponse($response);
        }

        $postId = $response->json('id');
        $this->logPublishSuccess($postId);

        return PublishResult::ok(
            $postId,
            "https://facebook.com/{$postId}",
        );
    }

    private function publishWithPhoto(SocialAccount $account, string $pageId, string $text, string $imageUrl): PublishResult
    {
        $response = $this->httpClient($account)
            ->post("/{$pageId}/photos", [
                'url'     => $imageUrl,
                'message' => $text,
            ]);

        if (!$response->successful()) {
            return $this->failFromResponse($response);
        }

        $postId = $response->json('post_id');
        if ($postId === null) {
            $photoId = $response->json('id');
            if (is_string($photoId) && $photoId !== '') {
                $postId = $this->resolvePhotoPostId($account, $photoId);
            }
        }

        if (!is_string($postId) || $postId === '') {
            return PublishResult::fail('Facebook returned a photo id without a resolvable post id.');
        }

        $this->logPublishSuccess($postId);

        return PublishResult::ok(
            $postId,
            "https://facebook.com/{$postId}",
        );
    }

    private function publishWithVideo(SocialAccount $account, string $pageId, string $text, string $videoUrl): PublishResult
    {
        $response = $this->httpClient($account)
            ->post("/{$pageId}/videos", [
                'file_url'    => $videoUrl,
                'description' => $text,
            ]);

        if (!$response->successful()) {
            return $this->failFromResponse($response);
        }

        $videoId = $response->json('id');
        if (!is_string($videoId) || $videoId === '') {
            return PublishResult::fail('Facebook returned no video id for the uploaded media.');
        }

        $this->logPublishSuccess($videoId);

        return PublishResult::ok(
            $videoId,
            "https://facebook.com/{$videoId}",
        );
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        $response = $this->httpClient($account)
            ->delete("/{$platformPostId}");

        return $response->successful();
    }

    public function refreshToken(string $refreshToken): TokenResult
    {
        $creds = $this->oauthClientCredentials();

        $response = $this->httpClient()
            ->get('/oauth/access_token', [
                'grant_type'    => 'fb_exchange_token',
                'client_id'     => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'fb_exchange_token' => $refreshToken,
            ]);

        if (!$response->successful()) {
            return TokenResult::fail('Failed to refresh Facebook token: ' . $response->body());
        }

        $data = $response->json();

        return TokenResult::ok(
            accessToken:  $data['access_token'],
            refreshToken: $data['access_token'],
            expiresAt:    isset($data['expires_in'])
                ? Carbon::now()->addSeconds($data['expires_in'])
                : null,
        );
    }

    public function publishComment(SocialAccount $account, string $platformPostId, string $comment): bool
    {
        $message = trim($comment);
        $targetPostId = $this->normalizePostTarget($account, $platformPostId);
        if ($message === '' || $targetPostId === null) {
            return false;
        }

        $response = $this->httpClient($account)->post("/{$targetPostId}/comments", [
            'message' => $message,
        ]);

        return $response->successful();
    }

    private function resolvePhotoPostId(SocialAccount $account, string $photoId): ?string
    {
        $response = $this->httpClient($account)->get("/{$photoId}", [
            'fields' => 'post_id',
        ]);

        if (!$response->successful()) {
            return null;
        }

        $postId = $response->json('post_id');
        return is_string($postId) && $postId !== '' ? $postId : null;
    }

    private function normalizePostTarget(SocialAccount $account, string $platformPostId): ?string
    {
        $value = trim($platformPostId);
        if ($value === '') {
            return null;
        }

        // Expected format is "{page_id}_{post_id}" for page feed posts.
        if (preg_match('/^\d+_\d+$/', $value) !== 1) {
            return null;
        }

        $pageId = trim((string) $account->platform_user_id);
        if ($pageId !== '' && !str_starts_with($value, $pageId . '_')) {
            return null;
        }

        return $value;
    }

    private function isVideoUrl(string $url): bool
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['mp4', 'mov', 'avi', 'wmv', 'webm', 'mkv'], true);
    }
}
