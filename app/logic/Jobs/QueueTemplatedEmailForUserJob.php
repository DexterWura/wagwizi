<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Notifications\SystemMessageSendService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Schedules a templated email for a user (creates delivery row + SendTemplatedEmailJob).
 * For HTTP flows, the actual send runs after response; for CLI flows it runs synchronously.
 */
class QueueTemplatedEmailForUserJob
{
    use Dispatchable, SerializesModels;

    public function __construct(
        private readonly int $userId,
        private readonly string $templateKey,
        private readonly array $vars = [],
        private readonly array $metadataExtra = [],
    ) {}

    public function handle(SystemMessageSendService $send): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $send->queueEmailToUser($user, $this->templateKey, $this->vars, $this->metadataExtra);
    }
}
