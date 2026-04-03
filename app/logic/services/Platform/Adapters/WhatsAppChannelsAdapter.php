<?php

namespace App\Services\Platform\Adapters;

use App\Models\SocialAccount;
use App\Services\Platform\AbstractPlatformAdapter;
use App\Services\Platform\Platform;
use App\Services\Platform\PublishResult;
use App\Services\Platform\TokenResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Publishes via WhatsApp Cloud API POST /{phone-number-id}/messages.
 * The "channel" or broadcast recipient is the Cloud API {@see $account->platform_user_id} value passed as {@code to}
 * (e.g. E.164 phone, group id, or a channel/newsletter id when supported for your Meta app).
 */
final class WhatsAppChannelsAdapter extends AbstractPlatformAdapter
{
    public function platform(): Platform
    {
        return Platform::WhatsappChannels;
    }

    protected function baseUrl(): string
    {
        return 'https://graph.facebook.com/' . $this->graphApiVersion();
    }

    private function graphApiVersion(): string
    {
        $v = trim((string) ($this->platformConfig()['graph_api_version'] ?? 'v21.0'), '/');

        return $v !== '' ? $v : 'v21.0';
    }

    /**
     * @return array{valid: bool, error?: string}
     */
    public function validateCredentials(string $accessToken, string $phoneNumberId): array
    {
        $phoneNumberId = trim($phoneNumberId);
        if ($phoneNumberId === '' || !ctype_digit($phoneNumberId)) {
            return ['valid' => false, 'error' => 'Phone number ID must be the numeric ID from Meta (WhatsApp → API Setup).'];
        }

        $token = trim($accessToken);
        if ($token === '') {
            return ['valid' => false, 'error' => 'Access token cannot be empty.'];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get("{$this->baseUrl()}/{$phoneNumberId}", [
                'fields' => 'id,display_phone_number,verified_name',
            ]);

        if (!$response->successful()) {
            $msg = $response->json('error.message');

            return [
                'valid' => false,
                'error' => is_string($msg) && $msg !== ''
                    ? $msg
                    : 'Could not verify the token against this phone number ID.',
            ];
        }

        return ['valid' => true];
    }

    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
    ): PublishResult {
        if ($error = $this->validateAccount($account)) {
            return $error;
        }

        $phoneId = isset($account->metadata['phone_number_id'])
            ? trim((string) $account->metadata['phone_number_id'])
            : '';
        if ($phoneId === '' || !ctype_digit($phoneId)) {
            return PublishResult::fail('WhatsApp connection is missing a valid phone number ID. Reconnect from the accounts page.');
        }

        $recipientType = $account->metadata['recipient_type'] ?? 'individual';
        if (!is_string($recipientType) || !in_array($recipientType, ['individual', 'group'], true)) {
            $recipientType = 'individual';
        }

        $to = trim($account->platform_user_id);
        if ($to === '') {
            return PublishResult::fail('Missing recipient ID. Reconnect and set the Channel / group / phone value used as "to" in the Cloud API.');
        }

        $text = $this->resolveContent($content, $platformContent);

        if (trim($text) === '' && $mediaUrls === []) {
            return PublishResult::fail('WhatsApp message cannot be empty.');
        }

        if ($error = $this->validateContentLength($text)) {
            return $error;
        }

        $token = trim((string) $account->access_token);
        $url   = "{$this->baseUrl()}/{$phoneId}/messages";

        if ($mediaUrls !== []) {
            if ($error = $this->validateMediaUrls($mediaUrls)) {
                return $error;
            }
            if ($error = $this->validateVideoSupport($mediaUrls)) {
                return $error;
            }

            $captionLimit = (int) ($this->platformConfig()['max_caption_length'] ?? 1024);
            if (mb_strlen($text) > $captionLimit) {
                return PublishResult::fail(
                    "WhatsApp media captions are limited to {$captionLimit} characters (got " . mb_strlen($text) . ').'
                );
            }

            $first = $mediaUrls[0];

            return $this->looksLikeVideo($first)
                ? $this->sendVideo($url, $token, $to, $recipientType, $text, $first)
                : $this->sendImage($url, $token, $to, $recipientType, $text, $first);
        }

        return $this->sendText($url, $token, $to, $recipientType, $text);
    }

    private function sendText(
        string $url,
        string $token,
        string $to,
        string $recipientType,
        string $text,
    ): PublishResult {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => $recipientType,
            'to'                => $to,
            'type'              => 'text',
            'text'              => [
                'preview_url' => true,
                'body'        => $text,
            ],
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(90)
            ->retry(2, 500)
            ->post($url, $payload);

        return $this->finishMessageResponse($response);
    }

    private function sendImage(
        string $url,
        string $token,
        string $to,
        string $recipientType,
        string $caption,
        string $mediaUrl,
    ): PublishResult {
        $image = ['link' => $mediaUrl];
        if (trim($caption) !== '') {
            $image['caption'] = $caption;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => $recipientType,
            'to'                => $to,
            'type'              => 'image',
            'image'             => $image,
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(90)
            ->retry(2, 500)
            ->post($url, $payload);

        return $this->finishMessageResponse($response);
    }

    private function sendVideo(
        string $url,
        string $token,
        string $to,
        string $recipientType,
        string $caption,
        string $mediaUrl,
    ): PublishResult {
        $video = ['link' => $mediaUrl];
        if (trim($caption) !== '') {
            $video['caption'] = $caption;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => $recipientType,
            'to'                => $to,
            'type'              => 'video',
            'video'             => $video,
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(90)
            ->retry(2, 500)
            ->post($url, $payload);

        return $this->finishMessageResponse($response);
    }

    private function finishMessageResponse(Response $response): PublishResult
    {
        if (!$response->successful()) {
            return $this->failFromResponse($response);
        }

        $wamid = $response->json('messages.0.id');
        if (!is_string($wamid) || $wamid === '') {
            return PublishResult::fail('WhatsApp did not return a message id.');
        }

        $this->logPublishSuccess($wamid);

        return PublishResult::ok($wamid);
    }

    public function deletePost(SocialAccount $account, string $platformPostId): bool
    {
        return false;
    }

    public function refreshToken(string $refreshToken): TokenResult
    {
        return TokenResult::ok($refreshToken);
    }
}
