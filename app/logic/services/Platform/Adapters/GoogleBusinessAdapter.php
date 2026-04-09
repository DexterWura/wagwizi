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

class GoogleBusinessAdapter extends AbstractPlatformAdapter
{
    private const API_BASE = 'https://mybusiness.googleapis.com/v4';
    private const ACCOUNTS_API = 'https://mybusinessaccountmanagement.googleapis.com/v1';

    protected function baseUrl(): string
    {
        return self::API_BASE;
    }

    public function platform(): Platform
    {
        return Platform::GoogleBusiness;
    }

    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
        ?string       $audience = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) return $error;

        $locationName = $account->metadata['location_name'] ?? '';
        if (empty($locationName)) {
            return PublishResult::fail('No Google Business location configured. Please reconnect.');
        }

        $text = $this->resolveContent($content, $platformContent);

        if (trim($text) === '' && empty($mediaUrls)) {
            return PublishResult::fail('Google Business post cannot be empty.');
        }

        if ($error = $this->validateContentLength($text)) return $error;

        if (!empty($mediaUrls)) {
            if ($error = $this->validateMediaUrls($mediaUrls)) return $error;
        }

        $payload = [
            'topicType' => 'STANDARD',
            'summary'   => $text,
        ];

        if (!empty($mediaUrls)) {
            $payload['media'] = array_map(fn (string $url) => [
                'mediaFormat' => $this->resolveMediaFormat($url),
                'sourceUrl'   => $url,
            ], $mediaUrls);
        }

        $response = $this->httpClient($account)
            ->post(self::API_BASE . "/{$locationName}/localPosts", $payload);

        if (!$response->successful()) {
            return $this->failFromGbpResponse($response);
        }

        $data     = $response->json();
        $postName = $data['name'] ?? '';
        $postUrl  = $data['searchUrl'] ?? null;

        $this->logPublishSuccess($postName);

        return PublishResult::ok($postName, $postUrl);
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        $response = $this->httpClient($account)
            ->delete(self::API_BASE . "/{$platformPostId}");

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
            return TokenResult::fail('Failed to refresh Google Business token: ' . $response->body());
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

    /**
     * Fetch the first account and its first location for the authenticated user.
     * Returns account/location metadata to store alongside the social account.
     */
    public function fetchAccountAndLocation(string $accessToken): array
    {
        $accountsResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->get(self::ACCOUNTS_API . '/accounts');

        if (!$accountsResponse->successful()) {
            return ['valid' => false, 'error' => 'Could not retrieve Google Business accounts (HTTP ' . $accountsResponse->status() . ').'];
        }

        $accounts = $accountsResponse->json('accounts') ?? [];

        if (empty($accounts)) {
            return ['valid' => false, 'error' => 'No Google Business accounts found for this Google account.'];
        }

        $account     = $accounts[0];
        $accountName = $account['name'] ?? '';

        $locationsResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->get(self::API_BASE . "/{$accountName}/locations");

        if (!$locationsResponse->successful()) {
            return ['valid' => false, 'error' => 'Could not retrieve locations for your Google Business account.'];
        }

        $locations = $locationsResponse->json('locations') ?? [];

        if (empty($locations)) {
            return ['valid' => false, 'error' => 'No locations found in your Google Business account. Please add a business location first.'];
        }

        $location     = $locations[0];
        $locationName = $location['name'] ?? '';
        $locationTitle = $location['title']
            ?? $location['locationName']
            ?? $location['storefrontAddress']['locality']
            ?? 'Business';

        return [
            'valid'          => true,
            'account_name'   => $accountName,
            'location_name'  => $locationName,
            'location_title' => $locationTitle,
        ];
    }

    private function resolveMediaFormat(string $url): string
    {
        if ($this->looksLikeVideo($url)) {
            return 'VIDEO';
        }

        return 'PHOTO';
    }

    private function failFromGbpResponse(\Illuminate\Http\Client\Response $response): PublishResult
    {
        $body    = $response->json() ?? [];
        $message = $body['error']['message']
            ?? $body['error_description']
            ?? $body['message']
            ?? 'Google Business returned HTTP ' . $response->status();

        $status = $body['error']['code'] ?? $response->status();

        if ($status === 403) {
            $message = 'Insufficient permissions. Ensure your Google account has owner/manager access to this business location.';
        }

        Log::warning('Google Business publish failed', [
            'status' => $response->status(),
            'body'   => mb_substr($response->body(), 0, 500),
        ]);

        return PublishResult::fail($message, $response->status());
    }
}
