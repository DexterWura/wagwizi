<?php

declare(strict_types=1);

namespace App\Services\Webhook;

use App\Models\Post;
use App\Models\User;
use App\Services\Post\PostPublishingService;
use App\Services\Post\PostSchedulingService;
use App\Services\Subscription\PlanWebhookFeatureService;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class UserWebhookService
{
    public function __construct(
        private readonly PlanWebhookFeatureService $planWebhookFeature,
        private readonly PostSchedulingService $postScheduling,
        private readonly PostPublishingService $postPublishing,
    ) {}

    /**
     * @return array{webhook_key_id: string, webhook_secret: string}
     */
    public function ensureCredentials(User $user): array
    {
        $needsKey = !is_string($user->webhook_key_id) || trim($user->webhook_key_id) === '';
        $needsSecret = !is_string($user->webhook_secret) || trim($user->webhook_secret) === '';

        if (!$needsKey && !$needsSecret) {
            return [
                'webhook_key_id' => (string) $user->webhook_key_id,
                'webhook_secret' => (string) $user->webhook_secret,
            ];
        }

        $attrs = [];
        if ($needsKey) {
            $attrs['webhook_key_id'] = $this->generateWebhookKeyId();
        }
        if ($needsSecret) {
            $attrs['webhook_secret'] = $this->generateWebhookSecret();
        }

        $user->update($attrs);
        $user->refresh();

        return [
            'webhook_key_id' => (string) $user->webhook_key_id,
            'webhook_secret' => (string) $user->webhook_secret,
        ];
    }

    /**
     * @return array{webhook_key_id: string, webhook_secret: string}
     */
    public function regenerateSecret(User $user): array
    {
        if (!is_string($user->webhook_key_id) || trim($user->webhook_key_id) === '') {
            $user->update(['webhook_key_id' => $this->generateWebhookKeyId()]);
        }

        $user->update(['webhook_secret' => $this->generateWebhookSecret()]);
        $user->refresh();

        return [
            'webhook_key_id' => (string) $user->webhook_key_id,
            'webhook_secret' => (string) $user->webhook_secret,
        ];
    }

    public function userMayUseWebhooks(User $user): bool
    {
        return $this->planWebhookFeature->userMayUseWebhooks((int) $user->id);
    }

    /**
     * @return array{ok: bool, user?: User, error?: string}
     */
    public function authenticateInbound(string $webhookKeyId, ?string $providedSecret): array
    {
        $id = trim($webhookKeyId);
        if ($id === '') {
            return ['ok' => false, 'error' => 'Webhook key id is required.'];
        }

        $secret = trim((string) $providedSecret);
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Missing webhook secret. Provide it in X-Webhook-Secret header.'];
        }

        $user = User::query()->where('webhook_key_id', $id)->first();
        if ($user === null) {
            return ['ok' => false, 'error' => 'Unknown webhook key.'];
        }
        if ((string) ($user->status ?? 'active') !== 'active') {
            return ['ok' => false, 'error' => 'Webhook access is disabled for this account.'];
        }

        if (!$this->userMayUseWebhooks($user)) {
            return ['ok' => false, 'error' => 'Webhooks are not enabled for this account plan.'];
        }

        $storedSecret = trim((string) ($user->webhook_secret ?? ''));
        if ($storedSecret === '' || !hash_equals($storedSecret, $secret)) {
            return ['ok' => false, 'error' => 'Invalid webhook secret.'];
        }

        return ['ok' => true, 'user' => $user];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{post_id: int, status: string}
     */
    public function executeInboundAction(User $user, array $payload): array
    {
        $action = trim((string) ($payload['action'] ?? 'draft'));
        if ($action === '') {
            $action = 'draft';
        }

        $normalized = $this->normalizeInboundPayload($payload);

        return match ($action) {
            'draft' => $this->createDraft($user, $normalized),
            'schedule' => $this->schedulePost($user, $normalized),
            'publish_now' => $this->publishNow($user, $normalized),
            default => throw new InvalidArgumentException('Unsupported action. Use draft, schedule, or publish_now.'),
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeInboundPayload(array $payload): array
    {
        $content = trim((string) ($payload['content'] ?? ''));
        if ($content === '') {
            throw new InvalidArgumentException('Webhook payload must include non-empty content.');
        }

        $out = ['content' => $content];

        if (isset($payload['platform_accounts'])) {
            $accountIds = $payload['platform_accounts'];
            if (!is_array($accountIds)) {
                throw new InvalidArgumentException('platform_accounts must be an array of social account IDs.');
            }

            $ids = [];
            foreach ($accountIds as $raw) {
                $id = (int) $raw;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            $ids = array_values(array_unique($ids));
            if ($ids === []) {
                throw new InvalidArgumentException('No valid platform account IDs were provided.');
            }

            $out['platform_accounts'] = $ids;
        }

        if (isset($payload['platform_content']) && is_array($payload['platform_content'])) {
            $out['platform_content'] = $payload['platform_content'];
        }
        if (isset($payload['media_file_ids']) && is_array($payload['media_file_ids'])) {
            $out['media_file_ids'] = $payload['media_file_ids'];
        }
        if (isset($payload['media_paths']) && is_array($payload['media_paths'])) {
            $out['media_paths'] = $payload['media_paths'];
        }
        if (isset($payload['audience']) && is_string($payload['audience'])) {
            $out['audience'] = $payload['audience'];
        }
        if (isset($payload['first_comment']) && is_string($payload['first_comment'])) {
            $out['first_comment'] = $payload['first_comment'];
        }
        if (isset($payload['comment_delay_value'])) {
            $out['comment_delay_value'] = $payload['comment_delay_value'];
        }
        if (isset($payload['comment_delay_unit']) && is_string($payload['comment_delay_unit'])) {
            $out['comment_delay_unit'] = $payload['comment_delay_unit'];
        }
        if (isset($payload['scheduled_at']) && is_string($payload['scheduled_at'])) {
            $out['scheduled_at'] = $payload['scheduled_at'];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{post_id: int, status: string}
     */
    private function createDraft(User $user, array $normalized): array
    {
        $post = $this->postScheduling->createDraft((int) $user->id, $normalized);

        return [
            'post_id' => (int) $post->id,
            'status' => (string) $post->status,
        ];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{post_id: int, status: string}
     */
    private function schedulePost(User $user, array $normalized): array
    {
        $scheduled = trim((string) ($normalized['scheduled_at'] ?? ''));
        if ($scheduled === '') {
            throw new InvalidArgumentException('Action schedule requires scheduled_at (ISO datetime string).');
        }
        if (!isset($normalized['platform_accounts']) || !is_array($normalized['platform_accounts']) || $normalized['platform_accounts'] === []) {
            throw new InvalidArgumentException('Action schedule requires platform_accounts.');
        }

        $post = $this->postScheduling->schedulePost((int) $user->id, $normalized);

        return [
            'post_id' => (int) $post->id,
            'status' => (string) $post->status,
        ];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{post_id: int, status: string}
     */
    private function publishNow(User $user, array $normalized): array
    {
        if (!isset($normalized['platform_accounts']) || !is_array($normalized['platform_accounts']) || $normalized['platform_accounts'] === []) {
            throw new InvalidArgumentException('Action publish_now requires platform_accounts.');
        }

        $post = $this->postScheduling->publishNow((int) $user->id, $normalized);
        $this->postPublishing->dispatchPublishing($post);
        $post = Post::query()->findOrFail($post->id);

        return [
            'post_id' => (int) $post->id,
            'status' => (string) $post->status,
        ];
    }

    private function generateWebhookKeyId(): string
    {
        return strtolower((string) Str::uuid());
    }

    private function generateWebhookSecret(): string
    {
        return Str::random(64);
    }
}

