<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Workflow;
use App\Services\Workflow\WorkflowRunnerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly int $workflowId,
        private readonly string $triggerType,
        private readonly array $context = [],
    ) {}

    public function handle(WorkflowRunnerService $runner): void
    {
        $workflow = Workflow::query()
            ->with('user')
            ->find($this->workflowId);

        if (!$workflow instanceof Workflow) {
            return;
        }

        if ((string) $workflow->status !== 'active' && $this->triggerType !== 'manual') {
            return;
        }

        $runner->run($workflow, $this->triggerType, $this->context);
    }
}

