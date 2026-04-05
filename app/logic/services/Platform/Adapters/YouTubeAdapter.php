<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class YouTubeAdapter extends AbstractPlatformAdapter
{
    protected function baseUrl(): string
    {
        return 'https://www.googleapis.com/youtube/v3';
    }

    public function platform(): Platform
    {
        return Platform::YouTube;
    }

    /**
     * YouTube publishing handles two scenarios:
     * - Text-only: create a community post (channel bulletin)
     * - With video: upload the video via resumable upload
     */
    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) return $error;

        $text = $this->resolveContent($content, $platformContent);

        if (trim($text) === '') {
            return PublishResult::fail('YouTube posts require a description.');
        }

        if ($error = $this->validateContentLength($text)) return $error;

        if (!empty($mediaUrls)) {
            if ($error = $this->validateMediaUrls($mediaUrls)) return $error;

            if ($this->isVideoUrl($mediaUrls[0])) {
                return $this->uploadVideo($account, $text, $mediaUrls[0]);
            }

            return PublishResult::fail('YouTube publishing currently supports video uploads only.');
        }

        return $this->postBulletin($account, $text);
    }

    private function postBulletin(SocialAccount $account, string $text): PublishResult
    {
        $response = $this->httpClient($account)
            ->post('/activities?part=snippet,contentDetails', [
                'snippet' => [
                    'description' => $text,
                ],
                'contentDetails' => [
                    'bulletin' => [
                        'resourceId' => [],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            return $this->failFromResponse($response);
        }

        $id = $response->json('id');
        $this->logPublishSuccess($id);

        return PublishResult::ok($id, "https://www.youtube.com/post/{$id}");
    }

    private function uploadVideo(SocialAccount $account, string $description, string $videoUrl): PublishResult
    {
        $initResponse = Http::withToken($account->access_token)
            ->withHeaders([
                'Content-Type'           => 'application/json',
                'X-Upload-Content-Type'  => 'video/*',
            ])
            ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', [
                'snippet' => [
                    'title'       => mb_substr($description, 0, 100),
                    'description' => $description,
                ],
                'status' => [
                    'privacyStatus' => 'public',
                ],
            ]);

        if (!$initResponse->successful()) {
            return $this->failFromResponse($initResponse);
        }

        $uploadUrl = $initResponse->header('Location');
        if (!$uploadUrl) {
            return PublishResult::fail('YouTube did not return a resumable upload URL.');
        }

        $videoContent = $this->downloadSafeMedia($videoUrl);
        if ($videoContent === null) {
            return PublishResult::fail('Could not fetch video from URL.');
        }

        $uploadResponse = Http::withToken($account->access_token)
            ->withBody($videoContent, 'video/*')
            ->timeout(120)
            ->put($uploadUrl);

        if (!$uploadResponse->successful()) {
            return PublishResult::fail('Video upload to YouTube failed: ' . $uploadResponse->body());
        }

        $videoId = $uploadResponse->json('id');
        $this->logPublishSuccess($videoId);

        return PublishResult::ok($videoId, "https://www.youtube.com/watch?v={$videoId}");
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        $response = $this->httpClient($account)
            ->delete("/videos?id={$platformPostId}");

        return $response->successful();
    }

    public function refreshToken(string $refreshToken): TokenResult
    {
        $creds = $this->oauthClientCredentials();

        $response = Http::asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id'     => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
            ]);

        if (!$response->successful()) {
            return TokenResult::fail('Failed to refresh YouTube/Google token: ' . $response->body());
        }

        $data = $response->json();

        return TokenResult::ok(
            accessToken:  $data['access_token'],
            refreshToken: $refreshToken,
            expiresAt:    isset($data['expires_in'])
                ? Carbon::now()->addSeconds($data['expires_in'])
                : null,
        );
    }

    private function isVideoUrl(string $url): bool
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['mp4', 'mov', 'avi', 'wmv', 'webm', 'mkv']);
    }
}
