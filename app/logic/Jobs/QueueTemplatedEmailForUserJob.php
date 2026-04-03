<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Notifications\SystemMessageSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queues a templated email for a user (creates delivery row + SendTemplatedEmailJob).
 * Use from HTTP or domain code so the actual SMTP work stays off the request thread.
 */
class QueueTemplatedEmailForUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
