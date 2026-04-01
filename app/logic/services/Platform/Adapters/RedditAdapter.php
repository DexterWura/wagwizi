<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class RedditAdapter extends AbstractPlatformAdapter
{
    protected function baseUrl(): string
    {
        return 'https://oauth.reddit.com';
    }

    public function platform(): Platform
    {
        return Platform::Reddit;
    }

    /**
     * Reddit requires a target subreddit, stored in the SocialAccount metadata.
     * Supports self (text), link, and image posts.
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
            return PublishResult::fail('Reddit posts require at least a title.');
        }

        if ($error = $this->validateContentLength($text)) return $error;
        if ($error = $this->validateVideoSupport($mediaUrls)) return $error;

        if (!empty($mediaUrls)) {
            if ($error = $this->validateMediaUrls($mediaUrls)) return $error;
        }

        $subreddit = $account->metadata['subreddit'] ?? '';

        if (empty($subreddit)) {
            return PublishResult::fail('No target subreddit configured for this Reddit account.');
        }

        $title = mb_substr($text, 0, 300);

        $payload = [
            'sr'    => $subreddit,
            'title' => $title,
            'kind'  => 'self',
            'text'  => $text,
            'api_type' => 'json',
        ];

        if (!empty($mediaUrls)) {
            $payload['kind'] = 'link';
            $payload['url']  = $mediaUrls[0];
            unset($payload['text']);
        }

        $response = $this->httpClient($account)
            ->withHeaders(['User-Agent' => config('app.name') . '/1.0'])
            ->asForm()
            ->post('/api/submit', $payload);

        if (!$response->successful()) {
            return $this->failFromResponse($response);
        }

        $json   = $response->json('json') ?? [];
        $errors = $json['errors'] ?? [];

        if (!empty($errors)) {
            $errorMsg = is_array($errors[0]) ? implode(': ', $errors[0]) : (string) $errors[0];
            return PublishResult::fail($errorMsg);
        }

        $postUrl = $json['data']['url'] ?? null;
        $postId  = $json['data']['id'] ?? $json['data']['name'] ?? '';
        $this->logPublishSuccess($postId);

        return PublishResult::ok($postId, $postUrl);
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        $response = $this->httpClient($account)
            ->withHeaders(['User-Agent' => config('app.name') . '/1.0'])
            ->asForm()
            ->post('/api/del', [
                'id' => $platformPostId,
            ]);

        return $response->successful();
    }

    public function refreshToken(string $refreshToken): TokenResult
    {
        $creds = $this->oauthClientCredentials();

        $response = Http::asForm()
            ->withBasicAuth($creds['client_id'], $creds['client_secret'])
            ->withHeaders(['User-Agent' => config('app.name') . '/1.0'])
            ->post('https://www.reddit.com/api/v1/access_token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if (!$response->successful()) {
            return TokenResult::fail('Failed to refresh Reddit token: ' . $response->body());
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
