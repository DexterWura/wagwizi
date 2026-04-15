<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Illuminate\Support\Facades\Http;

class DevToAdapter extends AbstractPlatformAdapter
{
    protected function baseUrl(): string
    {
        return 'https://dev.to/api';
    }

    public function platform(): Platform
    {
        return Platform::DevTo;
    }

    /**
     * @return array{valid: bool, id?: string, username?: ?string, name?: ?string, avatar?: ?string, error?: string}
     */
    public function validateCredentials(string $apiKey): array
    {
        $token = trim($apiKey);
        if ($token === '') {
            return ['valid' => false, 'error' => 'Dev.to API key cannot be empty.'];
        }

        $response = Http::acceptJson()
            ->withHeaders(['api-key' => $token])
            ->timeout(30)
            ->get($this->baseUrl() . '/users/me');

        if (!$response->successful()) {
            $msg = $response->json('error') ?? $response->json('message');
            return [
                'valid' => false,
                'error' => is_string($msg) && $msg !== ''
                    ? $msg
                    : 'Could not verify Dev.to API key.',
            ];
        }

        $data = $response->json();
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            return ['valid' => false, 'error' => 'Dev.to user ID was missing from API response.'];
        }

        return [
            'valid' => true,
            'id' => $id,
            'username' => isset($data['username']) ? (string) $data['username'] : null,
            'name' => isset($data['name']) ? (string) $data['name'] : null,
            'avatar' => isset($data['profile_image']) ? (string) $data['profile_image'] : null,
        ];
    }

    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
        ?string       $audience = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) {
            return $error;
        }

        $text = trim($this->resolveContent($content, $platformContent));
        if ($text === '') {
            return PublishResult::fail('Dev.to article content cannot be empty.');
        }

        if ($error = $this->validateContentLength($text)) {
            return $error;
        }

        if ($error = $this->validateMediaUrls($mediaUrls)) {
            return $error;
        }

        if ($error = $this->validateVideoSupport($mediaUrls)) {
            return $error;
        }

        [$title, $bodyMarkdown] = $this->splitTitleAndBody($text);

        $article = [
            'title' => $title,
            'published' => true,
            'body_markdown' => $bodyMarkdown,
        ];

        if ($mediaUrls !== []) {
            $article['main_image'] = $mediaUrls[0];
        }

        $response = Http::acceptJson()
            ->withHeaders(['api-key' => trim((string) $account->access_token)])
            ->asJson()
            ->timeout(60)
            ->retry(2, 500)
            ->post($this->baseUrl() . '/articles', ['article' => $article]);

        if (!$response->successful()) {
            return $this->failFromResponse($response);
        }

        $postId = trim((string) ($response->json('id') ?? ''));
        $postUrl = $response->json('url');
        if ($postId === '') {
            return PublishResult::fail('Dev.to did not return an article ID.');
        }

        $this->logPublishSuccess($postId);
        return PublishResult::ok($postId, is_string($postUrl) ? $postUrl : null);
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        return false;
    }

    public function refreshToken(string $refreshToken): TokenResult
    {
        return TokenResult::ok($refreshToken);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitTitleAndBody(string $markdown): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $title = '';
        $body = $markdown;

        foreach ($lines as $idx => $line) {
            $candidate = trim((string) $line);
            if ($candidate === '') {
                continue;
            }
            $title = $candidate;
            $remaining = array_slice($lines, $idx + 1);
            $body = trim(implode("\n", $remaining));
            break;
        }

        if ($title === '') {
            $title = mb_substr($markdown, 0, 120);
        }

        $title = ltrim($title, "# \t");
        $title = trim($title);
        if ($title === '') {
            $title = 'New article';
        }
        if (mb_strlen($title) > 120) {
            $title = rtrim(mb_substr($title, 0, 120));
        }

        if ($body === '') {
            $body = $markdown;
        }

        return [$title, $body];
    }
}
