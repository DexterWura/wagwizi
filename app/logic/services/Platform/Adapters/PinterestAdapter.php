<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class PinterestAdapter extends AbstractPlatformAdapter
{
    protected function baseUrl(): string
    {
        return 'https://api.pinterest.com/v5';
    }

    public function platform(): Platform
    {
        return Platform::Pinterest;
    }

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
            return PublishResult::fail('Pinterest requires an image to create a pin.');
        }

        if ($error = $this->validateMediaUrls($mediaUrls)) return $error;
        if ($error = $this->validateVideoSupport($mediaUrls)) return $error;

        $boardId = $account->metadata['default_board_id'] ?? '';
        if (empty($boardId)) {
            return PublishResult::fail('No default board configured for this Pinterest account. Please set a board first.');
        }

        $payload = [
            'title'       => mb_substr($text, 0, 100),
            'description' => $text,
            'board_id'    => $boardId,
            'media_source' => [
                'source_type' => 'image_url',
                'url'         => $mediaUrls[0],
            ],
        ];

        $response = $this->httpClient($account)
            ->post('/pins', $payload);

        if (!$response->successful()) {
            return $this->failFromResponse($response);
        }

        $pinId = $response->json('id');
        $this->logPublishSuccess($pinId);

        return PublishResult::ok(
            $pinId,
            "https://www.pinterest.com/pin/{$pinId}/",
        );
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        $response = $this->httpClient($account)
            ->delete("/pins/{$platformPostId}");

        return $response->successful();
    }

    public function refreshToken(string $refreshToken): TokenResult
    {
        $creds = $this->oauthClientCredentials();

        $response = Http::asForm()
            ->withBasicAuth($creds['client_id'], $creds['client_secret'])
            ->post('https://api.pinterest.com/v5/oauth/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if (!$response->successful()) {
            return TokenResult::fail('Failed to refresh Pinterest token: ' . $response->body());
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
}
