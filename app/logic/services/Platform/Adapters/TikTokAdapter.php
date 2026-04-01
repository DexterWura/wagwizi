<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class TikTokAdapter extends AbstractPlatformAdapter
{
    protected function baseUrl(): string
    {
        return 'https://open.tiktokapis.com/v2';
    }

    public function platform(): Platform
    {
        return Platform::TikTok;
    }

    /**
     * TikTok Content Posting API uses a multi-step flow:
     * 1. Initialize upload (get publish_id + upload_url)
     * 2. Upload the video file
     * 3. Check publish status
     *
     * Note: Unaudited apps can only post private videos.
     */
    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) return $error;

        $text = $this->resolveContent($content, $platformContent);

        if ($error = $this->validateContentLength($text)) return $error;

        if (empty($mediaUrls)) {
            return PublishResult::fail('TikTok requires a video or photo to publish.');
        }

        if ($error = $this->validateMediaUrls($mediaUrls)) return $error;

        if (count($mediaUrls) > 35) {
            return PublishResult::fail('TikTok allows a maximum of 35 images per photo post.');
        }

        $isVideo = $this->isVideoUrl($mediaUrls[0]);

        if ($isVideo) {
            return $this->publishVideo($account, $text, $mediaUrls[0]);
        }

        return $this->publishPhoto($account, $text, $mediaUrls);
    }

    private function publishVideo(SocialAccount $account, string $text, string $videoUrl): PublishResult
    {
        $videoContent = $this->downloadSafeMedia($videoUrl);
        if ($videoContent === null) {
            return PublishResult::fail('Could not fetch video from URL.');
        }

        $videoSize = strlen($videoContent);

        $initResponse = $this->httpClient($account)
            ->post('/post/publish/video/init/', [
                'post_info' => [
                    'title'          => mb_substr($text, 0, 150),
                    'privacy_level'  => 'SELF_ONLY',
                    'disable_duet'   => false,
                    'disable_stitch' => false,
                    'disable_comment' => false,
                ],
                'source_info' => [
                    'source'             => 'FILE_UPLOAD',
                    'video_size'         => $videoSize,
                    'chunk_size'         => $videoSize,
                    'total_chunk_count'  => 1,
                ],
            ]);

        if (!$initResponse->successful()) {
            return $this->failFromResponse($initResponse);
        }

        $uploadUrl = $initResponse->json('data.upload_url');
        $publishId = $initResponse->json('data.publish_id');

        if (!$uploadUrl) {
            return PublishResult::fail('TikTok did not return an upload URL.');
        }

        $uploadResponse = Http::withToken($account->access_token)
            ->withHeaders([
                'Content-Type'  => 'video/mp4',
                'Content-Range' => "bytes 0-" . ($videoSize - 1) . "/{$videoSize}",
            ])
            ->withBody($videoContent, 'video/mp4')
            ->timeout(120)
            ->put($uploadUrl);

        if (!$uploadResponse->successful()) {
            return PublishResult::fail('Video upload to TikTok failed: ' . $uploadResponse->body());
        }

        $this->logPublishSuccess($publishId);

        return PublishResult::ok($publishId);
    }

    private function publishPhoto(SocialAccount $account, string $text, array $mediaUrls): PublishResult
    {
        $images = [];
        foreach ($mediaUrls as $url) {
            $images[] = ['image_url' => $url];
        }

        $response = $this->httpClient($account)
            ->post('/post/publish/content/init/', [
                'post_info' => [
                    'title'         => mb_substr($text, 0, 150),
                    'privacy_level' => 'SELF_ONLY',
                ],
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                ],
                'post_mode' => 'DIRECT_POST',
                'media_type' => 'PHOTO',
                'photo_images' => $images,
            ]);

        if (!$response->successful()) {
            return $this->failFromResponse($response);
        }

        $publishId = $response->json('data.publish_id');
        $this->logPublishSuccess($publishId);

        return PublishResult::ok($publishId);
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        return false;
    }

    public function refreshToken(string $refreshToken): TokenResult
    {
        $creds = $this->oauthClientCredentials();

        $response = Http::asForm()
            ->post('https://open.tiktokapis.com/v2/oauth/token/', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_key'    => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
            ]);

        if (!$response->successful()) {
            return TokenResult::fail('Failed to refresh TikTok token: ' . $response->body());
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

    private function isVideoUrl(string $url): bool
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['mp4', 'mov', 'avi', 'wmv', 'webm']);
    }
}
