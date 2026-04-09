<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;

class TelegramAdapter extends AbstractPlatformAdapter
{
    protected function baseUrl(): string
    {
        return 'https://api.telegram.org';
    }

    public function platform(): Platform
    {
        return Platform::Telegram;
    }

    /**
     * Telegram uses bot tokens stored in `access_token` and channel IDs in `platform_user_id`.
     * No OAuth -- the user provides their bot token and target chat ID directly.
     */
    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
        ?string       $audience = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) return $error;

        $text     = $this->resolveContent($content, $platformContent);
        $botToken = $account->access_token;
        $chatId   = $account->platform_user_id;

        if (trim($text) === '' && empty($mediaUrls)) {
            return PublishResult::fail('Telegram message cannot be empty.');
        }

        if ($error = $this->validateContentLength($text)) return $error;

        if (!empty($mediaUrls)) {
            if ($error = $this->validateMediaUrls($mediaUrls)) return $error;

            $captionLimit = 1024;
            if (mb_strlen($text) > $captionLimit) {
                return PublishResult::fail(
                    "Telegram media captions are limited to {$captionLimit} characters (got " . mb_strlen($text) . ').'
                );
            }

            return $this->sendWithMedia($botToken, $chatId, $text, $mediaUrls[0]);
        }

        return $this->sendMessage($botToken, $chatId, $text);
    }

    private function sendMessage(string $botToken, string $chatId, string $text): PublishResult
    {
        $response = $this->botClient($botToken)
            ->post('/sendMessage', [
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ]);

        if (!$response->successful()) {
            return $this->failFromResponse($response);
        }

        $messageId = $response->json('result.message_id');
        $this->logPublishSuccess((string) $messageId);

        return PublishResult::ok((string) $messageId);
    }

    private function sendWithMedia(string $botToken, string $chatId, string $text, string $mediaUrl): PublishResult
    {
        $isVideo = $this->isVideoUrl($mediaUrl);
        $endpoint = $isVideo ? '/sendVideo' : '/sendPhoto';
        $mediaKey = $isVideo ? 'video' : 'photo';

        $response = $this->botClient($botToken)
            ->post($endpoint, [
                'chat_id'    => $chatId,
                $mediaKey    => $mediaUrl,
                'caption'    => $text,
                'parse_mode' => 'HTML',
            ]);

        if (!$response->successful()) {
            return $this->failFromResponse($response);
        }

        $messageId = $response->json('result.message_id');
        $this->logPublishSuccess((string) $messageId);

        return PublishResult::ok((string) $messageId);
    }

    private function botClient(string $botToken): \Illuminate\Http\Client\PendingRequest
    {
        return \Illuminate\Support\Facades\Http::baseUrl("{$this->baseUrl()}/bot{$botToken}")
            ->timeout(30)
            ->retry(2, 500)
            ->acceptJson();
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        $response = $this->botClient($account->access_token)
            ->post('/deleteMessage', [
                'chat_id'    => $account->platform_user_id,
                'message_id' => (int) $platformPostId,
            ]);

        return $response->successful() && $response->json('ok') === true;
    }

    /**
     * Telegram bots use permanent tokens; no refresh needed.
     */
    public function refreshToken(string $refreshToken): TokenResult
    {
        return TokenResult::ok($refreshToken);
    }

    private function isVideoUrl(string $url): bool
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['mp4', 'mov', 'avi', 'wmv', 'webm']);
    }
}
