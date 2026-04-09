<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Carbon\Carbon;

class ThreadsAdapter extends AbstractPlatformAdapter
{
    protected function baseUrl(): string
    {
        return 'https://graph.threads.net/v1.0';
    }

    public function platform(): Platform
    {
        return Platform::Threads;
    }

    /**
     * Threads uses a two-step container model similar to Instagram.
     * Text-only posts are also supported (unlike IG).
     */
    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
        ?string       $audience = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) return $error;

        $text   = $this->resolveContent($content, $platformContent);
        $userId = $account->platform_user_id;

        if (trim($text) === '' && empty($mediaUrls)) {
            return PublishResult::fail('Threads post cannot be empty.');
        }

        if ($error = $this->validateContentLength($text)) return $error;

        if (!empty($mediaUrls)) {
            if ($error = $this->validateMediaUrls($mediaUrls)) return $error;

            if (count($mediaUrls) > 20) {
                return PublishResult::fail('Threads carousels allow a maximum of 20 items.');
            }
        }

        $containerPayload = ['text' => $text];

        if (!empty($mediaUrls)) {
            if (count($mediaUrls) > 1) {
                return $this->publishCarousel($account, $userId, $text, $mediaUrls);
            }

            $mediaUrl = $mediaUrls[0];
            $isVideo  = $this->isVideoUrl($mediaUrl);
            $containerPayload['media_type'] = $isVideo ? 'VIDEO' : 'IMAGE';
            $containerPayload[$isVideo ? 'video_url' : 'image_url'] = $mediaUrl;
        } else {
            $containerPayload['media_type'] = 'TEXT';
        }

        $containerResponse = $this->httpClient($account)
            ->post("/{$userId}/threads", $containerPayload);

        if (!$containerResponse->successful()) {
            return $this->failFromResponse($containerResponse);
        }

        return $this->publishContainer($account, $userId, $containerResponse->json('id'));
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
                ->post("/{$userId}/threads", $payload);

            if (!$response->successful()) {
                return $this->failFromResponse($response);
            }

            $childIds[] = $response->json('id');
        }

        $carouselResponse = $this->httpClient($account)
            ->post("/{$userId}/threads", [
                'media_type' => 'CAROUSEL',
                'text'       => $text,
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
            ->post("/{$userId}/threads_publish", [
                'creation_id' => $containerId,
            ]);

        if (!$publishResponse->successful()) {
            return $this->failFromResponse($publishResponse);
        }

        $postId = $publishResponse->json('id');
        $this->logPublishSuccess($postId);

        return PublishResult::ok(
            $postId,
            "https://www.threads.net/post/{$postId}",
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
        $response = $this->httpClient()
            ->get('/refresh_access_token', [
                'grant_type'   => 'th_refresh_token',
                'access_token' => $refreshToken,
            ]);

        if (!$response->successful()) {
            return TokenResult::fail('Failed to refresh Threads token: ' . $response->body());
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
