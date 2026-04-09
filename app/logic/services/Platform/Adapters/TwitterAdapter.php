<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwitterAdapter extends AbstractPlatformAdapter
{
    protected function baseUrl(): string
    {
        return 'https://api.x.com/2';
    }

    public function platform(): Platform
    {
        return Platform::Twitter;
    }

    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
        ?string       $audience = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) return $error;

        $text = $this->resolveContent($content, $platformContent);

        if (trim($text) === '' && empty($mediaUrls)) {
            return PublishResult::fail('Tweet cannot be empty.');
        }

        if ($error = $this->validateContentLength($text)) return $error;

        if (!empty($mediaUrls)) {
            if (count($mediaUrls) > 4) {
                return PublishResult::fail('Twitter allows a maximum of 4 media attachments per tweet.');
            }
            if ($error = $this->validateMediaUrls($mediaUrls)) return $error;
        }

        $payload = ['text' => $text];

        try {
            if (!empty($mediaUrls)) {
                $mediaIds = $this->uploadMedia($account, $mediaUrls);
                if (count($mediaIds) !== count($mediaUrls)) {
                    return PublishResult::fail('Twitter media upload failed for one or more files. Tweet was not published as text-only.');
                }
                $payload['media'] = ['media_ids' => $mediaIds];
            }

            $response = $this->httpClient($account)
                ->post('/tweets', $payload);
        } catch (RequestException $e) {
            $response = $e->response;

            if ($response !== null) {
                return $this->failFromResponse($response);
            }

            Log::warning('Twitter publish request exception', [
                'error' => $e->getMessage(),
            ]);

            return PublishResult::fail('Twitter publish request failed: ' . $e->getMessage());
        } catch (\Throwable $e) {
            Log::warning('Twitter publish unexpected exception', [
                'error' => $e->getMessage(),
            ]);

            return PublishResult::fail('Twitter publish failed: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            return $this->failFromResponse($response);
        }

        $tweetId = $response->json('data.id');
        $this->logPublishSuccess($tweetId);

        return PublishResult::ok(
            $tweetId,
            "https://x.com/i/status/{$tweetId}",
        );
    }

    /**
     * Upload media via the v1.1 media upload endpoint (still required by X API v2 for tweets).
     *
     * @return string[] Array of media_id strings
     */
    private function uploadMedia(SocialAccount $account, array $mediaUrls): array
    {
        $mediaIds = [];

        foreach ($mediaUrls as $url) {
            $mediaContent = $this->downloadSafeMedia($url);
            if ($mediaContent === null) {
                continue;
            }

            try {
                $response = Http::baseUrl('https://upload.twitter.com/1.1')
                    ->withToken($account->access_token)
                    ->timeout(60)
                    ->asMultipart()
                    ->post('/media/upload.json', [
                        ['name' => 'media_data', 'contents' => base64_encode($mediaContent)],
                    ]);

                if ($response->successful() && $response->json('media_id_string')) {
                    $mediaIds[] = $response->json('media_id_string');
                    continue;
                }

                if ($response->status() === 402) {
                    Log::warning('Twitter media upload blocked by account credits', [
                        'status' => $response->status(),
                        'body'   => mb_substr($response->body(), 0, 500),
                    ]);
                }
            } catch (RequestException $e) {
                $resp = $e->response;
                Log::warning('Twitter media upload request exception', [
                    'status' => $resp?->status(),
                    'error'  => $e->getMessage(),
                    'body'   => $resp ? mb_substr($resp->body(), 0, 500) : null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Twitter media upload unexpected exception', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $mediaIds;
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        $response = $this->httpClient($account)
            ->delete("/tweets/{$platformPostId}");

        return $response->successful();
    }

    public function refreshToken(string $refreshToken): TokenResult
    {
        $creds = $this->oauthClientCredentials();
        $clientId = trim((string) ($creds['client_id'] ?? ''));
        $clientSecret = trim((string) ($creds['client_secret'] ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            return TokenResult::fail('Twitter OAuth client credentials are missing.');
        }

        $basic = base64_encode($clientId . ':' . $clientSecret);

        $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $basic,
            ])
            ->acceptJson()
            ->asForm()
            ->post('https://api.x.com/2/oauth2/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id'     => $clientId,
            ]);

        if (!$response->successful()) {
            return TokenResult::fail('Failed to refresh Twitter token: ' . $response->body());
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
        $text = trim($comment);
        $targetTweetId = $this->normalizeTweetId($platformPostId);
        if ($text === '' || $targetTweetId === null) {
            return false;
        }

        $response = $this->httpClient($account)->post('/tweets', [
            'text' => $text,
            'reply' => [
                'in_reply_to_tweet_id' => $targetTweetId,
            ],
        ]);

        return $response->successful();
    }

    private function normalizeTweetId(string $tweetId): ?string
    {
        $value = trim($tweetId);
        if ($value === '') {
            return null;
        }

        return preg_match('/^\d+$/', $value) === 1 ? $value : null;
    }
}
