<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Carbon\Carbon;

class InstagramAdapter extends AbstractPlatformAdapter
{
    private const API_VERSION = 'v21.0';

    protected function baseUrl(): string
    {
        return 'https://graph.instagram.com/' . self::API_VERSION;
    }

    public function platform(): Platform
    {
        return Platform::Instagram;
    }

    /**
     * Instagram uses a two-step container publish flow:
     * 1. Create a media container
     * 2. Publish the container
     */
    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) return $error;

        $text   = $this->resolveContent($content, $platformContent);
        $userId = $account->platform_user_id;

        if ($error = $this->validateContentLength($text)) return $error;

        if (empty($mediaUrls)) {
            return PublishResult::fail('Instagram requires at least one image or video.');
        }

        if ($error = $this->validateMediaUrls($mediaUrls)) return $error;

        if (count($mediaUrls) > 10) {
            return PublishResult::fail('Instagram carousels allow a maximum of 10 items.');
        }

        if (count($mediaUrls) > 1) {
            return $this->publishCarousel($account, $userId, $text, $mediaUrls);
        }

        return $this->publishSingleMedia($account, $userId, $text, $mediaUrls[0]);
    }

    private function publishSingleMedia(SocialAccount $account, string $userId, string $text, string $mediaUrl): PublishResult
    {
        $isVideo = $this->isVideoUrl($mediaUrl);

        $containerPayload = [
            'caption'    => $text,
            'media_type' => $isVideo ? 'VIDEO' : 'IMAGE',
        ];

        if ($isVideo) {
            $containerPayload['video_url'] = $mediaUrl;
        } else {
            $containerPayload['image_url'] = $mediaUrl;
        }

        $containerResponse = $this->httpClient($account)
            ->post("/{$userId}/media", $containerPayload);

        if (!$containerResponse->successful()) {
            return $this->failFromResponse($containerResponse);
        }

        $containerId = $containerResponse->json('id');

        return $this->publishContainer($account, $userId, $containerId);
    }

    private function publishCarousel(SocialAccount $account, string $userId, string $text, array $mediaUrls): PublishResult
    {
        $childIds = [];

        foreach ($mediaUrls as $url) {
            $isVideo = $this->isVideoUrl($url);
            $payload = [
                'is_carousel_item' => true,
                'media_type'       => $isVideo ? 'VIDEO' : 'IMAGE',
            ];
            $payload[$isVideo ? 'video_url' : 'image_url'] = $url;

            $response = $this->httpClient($account)
                ->post("/{$userId}/media", $payload);

            if (!$response->successful()) {
                return $this->failFromResponse($response);
            }

            $childIds[] = $response->json('id');
        }

        $carouselResponse = $this->httpClient($account)
            ->post("/{$userId}/media", [
                'media_type' => 'CAROUSEL',
                'caption'    => $text,
                'children'   => implode(',', $childIds),
            ]);

        if (!$carouselResponse->successful()) {
            return $this->failFromResponse($carouselResponse);
        }

        return $this->publishContainer($account, $userId, $carouselResponse->json('id'));
    }

    private function publishContainer(SocialAccount $account, string $userId, string $containerId): PublishResult
    {
        $publishResponse = $this->httpClient($account)
            ->post("/{$userId}/media_publish", [
                'creation_id' => $containerId,
            ]);

        if (!$publishResponse->successful()) {
            return $this->failFromResponse($publishResponse);
        }

        $postId = $publishResponse->json('id');
        $this->logPublishSuccess($postId);

        return PublishResult::ok(
            $postId,
            "https://www.instagram.com/p/{$postId}/",
        );
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        return false;
    }

    public function refreshToken(string $refreshToken): TokenResult
    {
        $response = $this->httpClient()
            ->get('/refresh_access_token', [
                'grant_type'   => 'ig_refresh_token',
                'access_token' => $refreshToken,
            ]);

        if (!$response->successful()) {
            return TokenResult::fail('Failed to refresh Instagram token: ' . $response->body());
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

    private function isVideoUrl(string $url): bool
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['mp4', 'mov', 'avi', 'wmv', 'webm']);
    }
}
