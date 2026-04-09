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
                $upload = $this->uploadMedia($account, $mediaUrls);
                $mediaIds = $upload['ids'];
                if (count($mediaIds) !== count($mediaUrls)) {
                    $details = $upload['errors'] !== [] ? (' Details: ' . implode(' | ', array_slice($upload['errors'], 0, 3))) : '';
                    $hint = !empty($upload['forbidden'])
                        ? ' Reconnect your Twitter account to grant media upload permissions (include media.write).'
                        : '';
                    return PublishResult::fail('Twitter media upload failed for one or more files. Tweet was not published as text-only.' . $hint . $details);
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
     * @return array{ids: array<int,string>, errors: array<int,string>, forbidden: bool}
     */
    private function uploadMedia(SocialAccount $account, array $mediaUrls): array
    {
        $mediaIds = [];
        $errors = [];
        $forbidden = false;

        foreach ($mediaUrls as $url) {
            $mediaContent = $this->downloadSafeMedia($url);
            if ($mediaContent === null) {
                $errors[] = 'Could not fetch media from: ' . $url;
                continue;
            }

            $uploadedId = null;
            $attemptErrors = [];
            $uploadEndpoints = [
                'https://api.x.com/2/media/upload',
                'https://api.twitter.com/2/media/upload',
                'https://upload.twitter.com/1.1/media/upload.json',
                'https://upload.x.com/1.1/media/upload.json',
            ];
            $filename = $this->filenameFromMediaUrl($url);
            $mimeType = $this->mimeTypeFromMediaUrl($url);

            foreach ($uploadEndpoints as $endpoint) {
                try {
                    // Preferred upload shape for X media endpoint: multipart file using "media".
                    $response = Http::withToken($account->access_token)
                        ->timeout(60)
                        ->acceptJson()
                        ->attach('media', $mediaContent, $filename, ['Content-Type' => $mimeType])
                        ->post($endpoint);

                    $mediaId = $this->extractMediaId($response->json());
                    if ($response->successful() && $mediaId !== null) {
                        $uploadedId = $mediaId;
                        break;
                    }

                    // Fallback shape: base64 payload using "media_data" with form encoding.
                    if ($uploadedId === null) {
                        $fallback = Http::withToken($account->access_token)
                            ->timeout(60)
                            ->acceptJson()
                            ->asForm()
                            ->post($endpoint, [
                                'media_data' => base64_encode($mediaContent),
                            ]);

                        $fallbackMediaId = $this->extractMediaId($fallback->json());
                        if ($fallback->successful() && $fallbackMediaId !== null) {
                            $uploadedId = $fallbackMediaId;
                            break;
                        }

                        if ($fallback->status() === 403) {
                            $forbidden = true;
                        }
                        $attemptErrors[] = $endpoint . ' status=' . $fallback->status() . ' body=' . $this->compactResponseBody($fallback->body());
                    }

                    if ($response->status() === 403) {
                        $forbidden = true;
                    }
                    $attemptErrors[] = $endpoint . ' status=' . $response->status() . ' body=' . $this->compactResponseBody($response->body());
                } catch (RequestException $e) {
                    $resp = $e->response;
                    $attemptErrors[] = $endpoint . ' request-exception status=' . ($resp?->status() ?? 'n/a') . ' error=' . $e->getMessage();
                    if (($resp?->status() ?? null) === 403) {
                        $forbidden = true;
                    }
                    Log::warning('Twitter media upload request exception', [
                        'status' => $resp?->status(),
                        'error'  => $e->getMessage(),
                        'body'   => $resp ? $this->compactResponseBody($resp->body(), 1200) : null,
                    ]);
                } catch (\Throwable $e) {
                    $attemptErrors[] = $endpoint . ' exception=' . $e->getMessage();
                    Log::warning('Twitter media upload unexpected exception', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($uploadedId !== null) {
                $mediaIds[] = $uploadedId;
            } else {
                $errors[] = 'Upload failed for ' . $url . (count($attemptErrors) ? (' (' . implode(' || ', array_slice($attemptErrors, 0, 2)) . ')') : '');
                Log::warning('Twitter media upload failed for URL', [
                    'media_url' => $url,
                    'attempt_errors' => array_slice($attemptErrors, 0, 4),
                    'has_media_write_scope' => $this->accountHasScope($account, 'media.write'),
                    'token_scopes' => $this->normalizeScopes($account->scopes),
                ]);
            }
        }

        return [
            'ids' => $mediaIds,
            'errors' => $errors,
            'forbidden' => $forbidden,
        ];
    }

    private function filenameFromMediaUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $name = trim((string) basename($path));

        return $name !== '' ? $name : 'upload.bin';
    }

    private function mimeTypeFromMediaUrl(string $url): string
    {
        $ext = strtolower((string) pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            default => 'application/octet-stream',
        };
    }

    private function extractMediaId(mixed $payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        $id = $payload['media_id_string']
            ?? $payload['media_id']
            ?? $payload['data']['media_id_string']
            ?? $payload['data']['id']
            ?? null;

        if (is_int($id)) {
            return (string) $id;
        }

        if (is_string($id) && trim($id) !== '') {
            return trim($id);
        }

        return null;
    }

    private function compactResponseBody(string $body, int $limit = 220): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '[empty]';
        }

        return mb_substr(preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed, 0, $limit);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeScopes(mixed $scopes): array
    {
        if (is_array($scopes)) {
            return array_values(array_filter(array_map(static fn ($s): string => strtolower(trim((string) $s)), $scopes)));
        }

        if (is_string($scopes)) {
            return array_values(array_filter(array_map(static fn ($s): string => strtolower(trim($s)), preg_split('/[\s,]+/', $scopes) ?: [])));
        }

        return [];
    }

    private function accountHasScope(SocialAccount $account, string $scope): bool
    {
        $needle = strtolower(trim($scope));
        return in_array($needle, $this->normalizeScopes($account->scopes), true);
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
