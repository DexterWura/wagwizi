<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Jobs\RunWorkflowJob;
use App\Models\User;
use App\Models\SocialAccount;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowRunStep;
use App\Services\Ai\ComposerAiChatService;
use App\Services\Post\PostSchedulingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class WorkflowRunnerService
{
    public function __construct(
        private readonly PostSchedulingService $postSchedulingService,
        private readonly ComposerAiChatService $composerAiChatService,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function run(Workflow $workflow, string $triggerType = 'manual', array $context = []): WorkflowRun
    {
        $user = $workflow->user;
        if (!($user instanceof User)) {
            throw new InvalidArgumentException('Workflow owner could not be resolved.');
        }

        $graph = is_array($workflow->graph) ? $workflow->graph : [];
        $nodes = $this->orderedNodes($graph);

        /** @var WorkflowRun $run */
        $run = WorkflowRun::query()->create([
            'workflow_id' => $workflow->id,
            'user_id' => $workflow->user_id,
            'trigger_type' => $triggerType,
            'status' => 'running',
            'context' => $context,
            'steps_total' => count($nodes),
            'started_at' => now(),
        ]);
        Log::info('Workflow run started', [
            'workflow_id' => $workflow->id,
            'run_id' => $run->id,
            'user_id' => $workflow->user_id,
            'trigger_type' => $triggerType,
            'steps_total' => count($nodes),
        ]);

        $state = [
            'draft' => (string) ($context['draft'] ?? ''),
            'post_payload' => null,
            'last_post_id' => null,
            'last_publish_status' => null,
            'vars' => is_array($context['vars'] ?? null) ? $context['vars'] : [],
        ];

        $stepsSucceeded = 0;
        $stepsFailed = 0;

        try {
            foreach ($nodes as $index => $node) {
                $start = microtime(true);
                $nodeId = (string) ($node['id'] ?? ('node_' . $index));
                $nodeType = strtolower((string) ($node['type'] ?? 'unknown'));
                $nodeConfig = is_array($node['config'] ?? null) ? $node['config'] : [];

                /** @var WorkflowRunStep $step */
                $step = WorkflowRunStep::query()->create([
                    'workflow_run_id' => $run->id,
                    'node_id' => $nodeId,
                    'node_type' => $nodeType,
                    'status' => 'running',
                    'position' => $index + 1,
                    'input_payload' => $nodeConfig,
                    'started_at' => now(),
                ]);

                try {
                    $output = $this->executeNode($user, $nodeType, $nodeConfig, $state, $context);
                    $state = $output['state'];
                    $usedTokens = (int) ($output['ai_tokens_used'] ?? 0);

                    $step->update([
                        'status' => 'completed',
                        'output_payload' => $output['output'] ?? [],
                        'ai_tokens_used' => $usedTokens,
                        'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                        'finished_at' => now(),
                    ]);
                    $stepsSucceeded++;
                } catch (\Throwable $e) {
                    $stepsFailed++;
                    $step->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                        'finished_at' => now(),
                    ]);

                    $run->update([
                        'status' => 'failed',
                        'steps_succeeded' => $stepsSucceeded,
                        'steps_failed' => $stepsFailed,
                        'error_message' => $e->getMessage(),
                        'context' => array_merge($context, ['state' => $state]),
                        'finished_at' => now(),
                    ]);

                    return $run->fresh(['steps']);
                }
            }

            $run->update([
                'status' => 'completed',
                'steps_succeeded' => $stepsSucceeded,
                'steps_failed' => $stepsFailed,
                'context' => array_merge($context, ['state' => $state]),
                'finished_at' => now(),
            ]);

            $workflow->update(['last_run_at' => now()]);
            Log::info('Workflow run completed', [
                'workflow_id' => $workflow->id,
                'run_id' => $run->id,
                'user_id' => $workflow->user_id,
                'steps_succeeded' => $stepsSucceeded,
                'steps_failed' => $stepsFailed,
            ]);
        } catch (\Throwable $e) {
            Log::error('Workflow run crashed unexpectedly', [
                'workflow_id' => $workflow->id,
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
            $run->update([
                'status' => 'failed',
                'steps_succeeded' => $stepsSucceeded,
                'steps_failed' => $stepsFailed + 1,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            Log::error('Workflow run failed', [
                'workflow_id' => $workflow->id,
                'run_id' => $run->id,
                'user_id' => $workflow->user_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $run->fresh(['steps']);
    }

    public function runDueScheduledWorkflows(): int
    {
        $count = 0;
        $workflows = Workflow::query()
            ->with('user')
            ->where('status', 'active')
            ->where('trigger_type', 'schedule')
            ->get();

        foreach ($workflows as $workflow) {
            $config = is_array($workflow->trigger_config) ? $workflow->trigger_config : [];
            $interval = max(1, (int) ($config['interval_minutes'] ?? 60));
            $last = $workflow->last_run_at;
            if ($last !== null && $last->gt(now()->subMinutes($interval))) {
                continue;
            }

            // Reserve this run window before queueing to avoid duplicate enqueues.
            $workflow->update(['last_run_at' => now()]);
            RunWorkflowJob::dispatch((int) $workflow->id, 'schedule', ['trigger' => 'schedule']);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function runEventTriggeredForUser(User $user, string $eventKey, array $payload): int
    {
        $count = 0;
        $workflows = Workflow::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('trigger_type', 'event')
            ->get();

        foreach ($workflows as $workflow) {
            $config = is_array($workflow->trigger_config) ? $workflow->trigger_config : [];
            if (strtolower((string) ($config['event_key'] ?? '')) !== strtolower($eventKey)) {
                continue;
            }
            RunWorkflowJob::dispatch((int) $workflow->id, 'event', [
                'trigger' => 'event',
                'event_key' => $eventKey,
                'event_payload' => $payload,
                'draft' => (string) ($payload['draft'] ?? ''),
                'vars' => is_array($payload['vars'] ?? null) ? $payload['vars'] : [],
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array<int, array<string, mixed>>
     */
    private function orderedNodes(array $graph): array
    {
        $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
        $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
        if ($nodes === []) {
            return [];
        }

        $byId = [];
        $inDegree = [];
        $adj = [];
        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $byId[$id] = $node;
            $inDegree[$id] = 0;
            $adj[$id] = [];
        }

        foreach ($edges as $edge) {
            $from = (string) ($edge['from'] ?? '');
            $to = (string) ($edge['to'] ?? '');
            if ($from === '' || $to === '' || !isset($byId[$from], $byId[$to])) {
                continue;
            }
            $adj[$from][] = $to;
            $inDegree[$to] = ($inDegree[$to] ?? 0) + 1;
        }

        $queue = [];
        foreach ($inDegree as $id => $deg) {
            if ($deg === 0) {
                $queue[] = $id;
            }
        }

        $ordered = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (!isset($byId[$id])) {
                continue;
            }
            $ordered[] = $byId[$id];
            foreach ($adj[$id] ?? [] as $next) {
                $inDegree[$next]--;
                if ($inDegree[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        return count($ordered) === count($byId) ? $ordered : array_values($nodes);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $context
     * @return array{state: array<string, mixed>, output: array<string, mixed>, ai_tokens_used?: int}
     */
    private function executeNode(User $user, string $nodeType, array $config, array $state, array $context): array
    {
        if (str_starts_with($nodeType, 'trigger.')) {
            return [
                'state' => $state,
                'output' => ['ok' => true, 'trigger' => $nodeType],
            ];
        }

        if ($nodeType === 'utility.set_variables') {
            $vars = is_array($config['variables'] ?? null) ? $config['variables'] : [];
            $state['vars'] = array_merge(is_array($state['vars'] ?? null) ? $state['vars'] : [], $vars);
            if (isset($vars['draft']) && is_string($vars['draft'])) {
                $state['draft'] = $vars['draft'];
            }

            return ['state' => $state, 'output' => ['variables_set' => array_keys($vars)]];
        }

        if ($nodeType === 'utility.select_platforms') {
            $payload = is_array($state['post_payload'] ?? null) ? $state['post_payload'] : null;
            if ($payload === null) {
                throw new InvalidArgumentException('Select platforms node requires a prepared post payload.');
            }
            $selected = is_array($payload['platform_accounts'] ?? null) ? $payload['platform_accounts'] : [];
            $selected = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $selected)));
            $selected = array_values(array_filter($selected, static fn (int $v): bool => $v > 0));
            if ($selected === []) {
                throw new InvalidArgumentException('Select platforms node requires platform accounts in payload.');
            }

            $platforms = is_array($config['platforms'] ?? null) ? $config['platforms'] : [];
            $platforms = array_values(array_unique(array_map(
                static fn ($v): string => strtolower(trim((string) $v)),
                $platforms
            )));
            $platforms = array_values(array_filter($platforms, static fn (string $v): bool => $v !== ''));
            if ($platforms === []) {
                throw new InvalidArgumentException('Select platforms node requires at least one platform.');
            }

            $accounts = SocialAccount::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $selected)
                ->get(['id', 'platform']);
            $selectedSet = array_fill_keys($platforms, true);
            $allowedIds = [];
            foreach ($accounts as $account) {
                $platform = strtolower((string) $account->platform);
                if (isset($selectedSet[$platform])) {
                    $allowedIds[] = (int) $account->id;
                }
            }
            $allowedIds = array_values(array_unique($allowedIds));
            if ($allowedIds === []) {
                throw new InvalidArgumentException('Select platforms node removed all platform accounts.');
            }
            $payload['platform_accounts'] = $allowedIds;
            $state['post_payload'] = $payload;

            return ['state' => $state, 'output' => ['platforms' => $platforms, 'accounts' => $allowedIds]];
        }

        if ($nodeType === 'utility.exclude_platforms') {
            $payload = is_array($state['post_payload'] ?? null) ? $state['post_payload'] : null;
            if ($payload === null) {
                throw new InvalidArgumentException('Exclude platforms node requires a prepared post payload.');
            }
            $selected = is_array($payload['platform_accounts'] ?? null) ? $payload['platform_accounts'] : [];
            $selected = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $selected)));
            $selected = array_values(array_filter($selected, static fn (int $v): bool => $v > 0));
            if ($selected === []) {
                throw new InvalidArgumentException('Exclude platforms node requires platform accounts in payload.');
            }

            $platforms = is_array($config['platforms'] ?? null) ? $config['platforms'] : [];
            $platforms = array_values(array_unique(array_map(
                static fn ($v): string => strtolower(trim((string) $v)),
                $platforms
            )));
            $platforms = array_values(array_filter($platforms, static fn (string $v): bool => $v !== ''));
            if ($platforms === []) {
                return ['state' => $state, 'output' => ['platforms_excluded' => [], 'accounts' => $selected]];
            }

            $accounts = SocialAccount::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $selected)
                ->get(['id', 'platform']);
            $excludedSet = array_fill_keys($platforms, true);
            $remainingIds = [];
            foreach ($accounts as $account) {
                $platform = strtolower((string) $account->platform);
                if (!isset($excludedSet[$platform])) {
                    $remainingIds[] = (int) $account->id;
                }
            }
            $remainingIds = array_values(array_unique($remainingIds));
            if ($remainingIds === []) {
                throw new InvalidArgumentException('Exclude platforms node removed all platform accounts.');
            }
            $payload['platform_accounts'] = $remainingIds;
            $state['post_payload'] = $payload;

            return ['state' => $state, 'output' => ['platforms_excluded' => $platforms, 'accounts' => $remainingIds]];
        }

        if ($nodeType === 'utility.limit_accounts') {
            $payload = is_array($state['post_payload'] ?? null) ? $state['post_payload'] : null;
            if ($payload === null) {
                throw new InvalidArgumentException('Limit accounts node requires a prepared post payload.');
            }
            $selected = is_array($payload['platform_accounts'] ?? null) ? $payload['platform_accounts'] : [];
            $selected = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $selected)));
            $selected = array_values(array_filter($selected, static fn (int $v): bool => $v > 0));
            if ($selected === []) {
                throw new InvalidArgumentException('Limit accounts node requires platform accounts in payload.');
            }

            $max = max(1, (int) ($config['max'] ?? 1));
            $payload['platform_accounts'] = array_slice($selected, 0, $max);
            $state['post_payload'] = $payload;

            return ['state' => $state, 'output' => ['accounts' => $payload['platform_accounts'], 'max' => $max]];
        }

        if ($nodeType === 'utility.delay') {
            $seconds = max(0, min(10, (int) ($config['seconds'] ?? 1)));
            if ($seconds > 0) {
                usleep($seconds * 1000000);
            }

            return ['state' => $state, 'output' => ['slept_seconds' => $seconds]];
        }

        if ($nodeType === 'utility.condition') {
            $left = (string) ($config['left'] ?? '');
            $operator = (string) ($config['operator'] ?? 'equals');
            $right = (string) ($config['right'] ?? '');
            $actual = $left === 'draft' ? (string) ($state['draft'] ?? '') : (string) (($state['vars'][$left] ?? '') ?: '');

            $result = match ($operator) {
                'contains' => str_contains(strtolower($actual), strtolower($right)),
                default => strtolower($actual) === strtolower($right),
            };
            if (!$result) {
                throw new InvalidArgumentException('Condition node failed; stopping workflow run.');
            }

            return ['state' => $state, 'output' => ['condition_passed' => true]];
        }

        if ($nodeType === 'ai.generate_caption') {
            $instruction = trim((string) ($config['instruction'] ?? 'Improve this post.'));
            $draft = (string) ($state['draft'] ?? '');
            if ($draft === '') {
                $draft = (string) ($context['draft'] ?? '');
            }
            if ($draft === '') {
                throw new InvalidArgumentException('AI node requires draft text.');
            }

            $ai = $this->composerAiChatService->complete($user, $instruction, $draft);
            $state['draft'] = (string) $ai->reply;

            return [
                'state' => $state,
                'output' => ['draft' => $state['draft'], 'billing_source' => $ai->billingSource],
                'ai_tokens_used' => max(0, (int) $ai->totalTokens),
            ];
        }

        if ($nodeType === 'action.compose_post') {
            $draft = trim((string) ($state['draft'] ?? ''));
            if ($draft === '') {
                throw new InvalidArgumentException('Compose node requires draft content.');
            }
            $accounts = is_array($config['platform_account_ids'] ?? null) ? $config['platform_account_ids'] : [];
            $accounts = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $accounts)));
            $accounts = array_values(array_filter($accounts, static fn (int $v): bool => $v > 0));
            if ($accounts === []) {
                throw new InvalidArgumentException('Compose node requires at least one platform account id.');
            }

            $state['post_payload'] = [
                'content' => $draft,
                'platform_accounts' => $accounts,
                'audience' => (string) ($config['audience'] ?? 'everyone'),
                'platform_content' => is_array($config['platform_content'] ?? null) ? $config['platform_content'] : [],
                'media_paths' => is_array($config['media_paths'] ?? null) ? $config['media_paths'] : [],
            ];

            return ['state' => $state, 'output' => ['post_payload_ready' => true, 'accounts' => $accounts]];
        }

        if ($nodeType === 'action.publish_post') {
            $payload = is_array($state['post_payload'] ?? null) ? $state['post_payload'] : null;
            if ($payload === null) {
                throw new InvalidArgumentException('Publish node requires a prepared post payload.');
            }

            $post = DB::transaction(fn () => $this->postSchedulingService->publishNow($user->id, $payload));
            $state['last_post_id'] = $post->id;
            $state['last_publish_status'] = $post->status;

            return ['state' => $state, 'output' => ['post_id' => $post->id, 'status' => $post->status]];
        }

        throw new InvalidArgumentException("Unsupported workflow node type: {$nodeType}");
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\SocialAccount;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowRunStep;
use App\Services\Ai\ComposerAiChatService;
use App\Services\Post\PostSchedulingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class WorkflowRunnerService
{
    public function __construct(
        private readonly PostSchedulingService $postSchedulingService,
        private readonly ComposerAiChatService $composerAiChatService,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function run(Workflow $workflow, string $triggerType = 'manual', array $context = []): WorkflowRun
    {
        $user = $workflow->user;
        if (!($user instanceof User)) {
            throw new InvalidArgumentException('Workflow owner could not be resolved.');
        }

        $graph = is_array($workflow->graph) ? $workflow->graph : [];
        $nodes = $this->orderedNodes($graph);

        /** @var WorkflowRun $run */
        $run = WorkflowRun::query()->create([
            'workflow_id' => $workflow->id,
            'user_id' => $workflow->user_id,
            'trigger_type' => $triggerType,
            'status' => 'running',
            'context' => $context,
            'steps_total' => count($nodes),
            'started_at' => now(),
        ]);

        $state = [
            'draft' => (string) ($context['draft'] ?? ''),
            'post_payload' => null,
            'last_post_id' => null,
            'last_publish_status' => null,
            'vars' => is_array($context['vars'] ?? null) ? $context['vars'] : [],
        ];

        $stepsSucceeded = 0;
        $stepsFailed = 0;

        try {
            foreach ($nodes as $index => $node) {
                $start = microtime(true);
                $nodeId = (string) ($node['id'] ?? ('node_' . $index));
                $nodeType = strtolower((string) ($node['type'] ?? 'unknown'));
                $nodeConfig = is_array($node['config'] ?? null) ? $node['config'] : [];

                /** @var WorkflowRunStep $step */
                $step = WorkflowRunStep::query()->create([
                    'workflow_run_id' => $run->id,
                    'node_id' => $nodeId,
                    'node_type' => $nodeType,
                    'status' => 'running',
                    'position' => $index + 1,
                    'input_payload' => $nodeConfig,
                    'started_at' => now(),
                ]);

                try {
                    $output = $this->executeNode($user, $nodeType, $nodeConfig, $state, $context);
                    $state = $output['state'];
                    $usedTokens = (int) ($output['ai_tokens_used'] ?? 0);

                    $step->update([
                        'status' => 'completed',
                        'output_payload' => $output['output'] ?? [],
                        'ai_tokens_used' => $usedTokens,
                        'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                        'finished_at' => now(),
                    ]);
                    $stepsSucceeded++;
                } catch (\Throwable $e) {
                    $stepsFailed++;
                    $step->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                        'finished_at' => now(),
                    ]);

                    $run->update([
                        'status' => 'failed',
                        'steps_succeeded' => $stepsSucceeded,
                        'steps_failed' => $stepsFailed,
                        'error_message' => $e->getMessage(),
                        'context' => array_merge($context, ['state' => $state]),
                        'finished_at' => now(),
                    ]);

                    return $run->fresh(['steps']);
                }
            }

            $run->update([
                'status' => 'completed',
                'steps_succeeded' => $stepsSucceeded,
                'steps_failed' => $stepsFailed,
                'context' => array_merge($context, ['state' => $state]),
                'finished_at' => now(),
            ]);

            $workflow->update(['last_run_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Workflow run crashed unexpectedly', [
                'workflow_id' => $workflow->id,
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
            $run->update([
                'status' => 'failed',
                'steps_succeeded' => $stepsSucceeded,
                'steps_failed' => $stepsFailed + 1,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return $run->fresh(['steps']);
    }

    public function runDueScheduledWorkflows(): int
    {
        $count = 0;
        $workflows = Workflow::query()
            ->with('user')
            ->where('status', 'active')
            ->where('trigger_type', 'schedule')
            ->get();

        foreach ($workflows as $workflow) {
            $config = is_array($workflow->trigger_config) ? $workflow->trigger_config : [];
            $interval = max(1, (int) ($config['interval_minutes'] ?? 60));
            $last = $workflow->last_run_at;
            if ($last !== null && $last->gt(now()->subMinutes($interval))) {
                continue;
            }

            $this->run($workflow, 'schedule', ['trigger' => 'schedule']);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function runEventTriggeredForUser(User $user, string $eventKey, array $payload): int
    {
        $count = 0;
        $workflows = Workflow::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('trigger_type', 'event')
            ->get();

        foreach ($workflows as $workflow) {
            $config = is_array($workflow->trigger_config) ? $workflow->trigger_config : [];
            if (strtolower((string) ($config['event_key'] ?? '')) !== strtolower($eventKey)) {
                continue;
            }
            $this->run($workflow, 'event', [
                'trigger' => 'event',
                'event_key' => $eventKey,
                'event_payload' => $payload,
                'draft' => (string) ($payload['draft'] ?? ''),
                'vars' => is_array($payload['vars'] ?? null) ? $payload['vars'] : [],
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array<int, array<string, mixed>>
     */
    private function orderedNodes(array $graph): array
    {
        $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
        $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
        if ($nodes === []) {
            return [];
        }

        $byId = [];
        $inDegree = [];
        $adj = [];
        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $byId[$id] = $node;
            $inDegree[$id] = 0;
            $adj[$id] = [];
        }

        foreach ($edges as $edge) {
            $from = (string) ($edge['from'] ?? '');
            $to = (string) ($edge['to'] ?? '');
            if ($from === '' || $to === '' || !isset($byId[$from], $byId[$to])) {
                continue;
            }
            $adj[$from][] = $to;
            $inDegree[$to] = ($inDegree[$to] ?? 0) + 1;
        }

        $queue = [];
        foreach ($inDegree as $id => $deg) {
            if ($deg === 0) {
                $queue[] = $id;
            }
        }

        $ordered = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (!isset($byId[$id])) {
                continue;
            }
            $ordered[] = $byId[$id];
            foreach ($adj[$id] ?? [] as $next) {
                $inDegree[$next]--;
                if ($inDegree[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        // If graph has cycles/bad edges, fallback to declaration order.
        return count($ordered) === count($byId) ? $ordered : array_values($nodes);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $context
     * @return array{state: array<string, mixed>, output: array<string, mixed>, ai_tokens_used?: int}
     */
    private function executeNode(User $user, string $nodeType, array $config, array $state, array $context): array
    {
        if (str_starts_with($nodeType, 'trigger.')) {
            return [
                'state' => $state,
                'output' => ['ok' => true, 'trigger' => $nodeType],
            ];
        }

        if ($nodeType === 'utility.set_variables') {
            $vars = is_array($config['variables'] ?? null) ? $config['variables'] : [];
            $state['vars'] = array_merge(is_array($state['vars'] ?? null) ? $state['vars'] : [], $vars);
            if (isset($vars['draft']) && is_string($vars['draft'])) {
                $state['draft'] = $vars['draft'];
            }

            return ['state' => $state, 'output' => ['variables_set' => array_keys($vars)]];
        }

        if ($nodeType === 'utility.select_platforms') {
            $payload = is_array($state['post_payload'] ?? null) ? $state['post_payload'] : null;
            if ($payload === null) {
                throw new InvalidArgumentException('Select platforms node requires a prepared post payload.');
            }
            $selected = is_array($payload['platform_accounts'] ?? null) ? $payload['platform_accounts'] : [];
            $selected = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $selected)));
            $selected = array_values(array_filter($selected, static fn (int $v): bool => $v > 0));
            if ($selected === []) {
                throw new InvalidArgumentException('Select platforms node requires platform accounts in payload.');
            }

            $platforms = is_array($config['platforms'] ?? null) ? $config['platforms'] : [];
            $platforms = array_values(array_unique(array_map(
                static fn ($v): string => strtolower(trim((string) $v)),
                $platforms
            )));
            $platforms = array_values(array_filter($platforms, static fn (string $v): bool => $v !== ''));
            if ($platforms === []) {
                throw new InvalidArgumentException('Select platforms node requires at least one platform.');
            }

            $accounts = SocialAccount::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $selected)
                ->get(['id', 'platform']);
            $selectedSet = array_fill_keys($platforms, true);
            $allowedIds = [];
            foreach ($accounts as $account) {
                $platform = strtolower((string) $account->platform);
                if (isset($selectedSet[$platform])) {
                    $allowedIds[] = (int) $account->id;
                }
            }
            $allowedIds = array_values(array_unique($allowedIds));
            if ($allowedIds === []) {
                throw new InvalidArgumentException('Select platforms node removed all platform accounts.');
            }
            $payload['platform_accounts'] = $allowedIds;
            $state['post_payload'] = $payload;

            return ['state' => $state, 'output' => ['platforms' => $platforms, 'accounts' => $allowedIds]];
        }

        if ($nodeType === 'utility.exclude_platforms') {
            $payload = is_array($state['post_payload'] ?? null) ? $state['post_payload'] : null;
            if ($payload === null) {
                throw new InvalidArgumentException('Exclude platforms node requires a prepared post payload.');
            }
            $selected = is_array($payload['platform_accounts'] ?? null) ? $payload['platform_accounts'] : [];
            $selected = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $selected)));
            $selected = array_values(array_filter($selected, static fn (int $v): bool => $v > 0));
            if ($selected === []) {
                throw new InvalidArgumentException('Exclude platforms node requires platform accounts in payload.');
            }

            $platforms = is_array($config['platforms'] ?? null) ? $config['platforms'] : [];
            $platforms = array_values(array_unique(array_map(
                static fn ($v): string => strtolower(trim((string) $v)),
                $platforms
            )));
            $platforms = array_values(array_filter($platforms, static fn (string $v): bool => $v !== ''));
            if ($platforms === []) {
                return ['state' => $state, 'output' => ['platforms_excluded' => [], 'accounts' => $selected]];
            }

            $accounts = SocialAccount::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $selected)
                ->get(['id', 'platform']);
            $excludedSet = array_fill_keys($platforms, true);
            $remainingIds = [];
            foreach ($accounts as $account) {
                $platform = strtolower((string) $account->platform);
                if (!isset($excludedSet[$platform])) {
                    $remainingIds[] = (int) $account->id;
                }
            }
            $remainingIds = array_values(array_unique($remainingIds));
            if ($remainingIds === []) {
                throw new InvalidArgumentException('Exclude platforms node removed all platform accounts.');
            }
            $payload['platform_accounts'] = $remainingIds;
            $state['post_payload'] = $payload;

            return ['state' => $state, 'output' => ['platforms_excluded' => $platforms, 'accounts' => $remainingIds]];
        }

        if ($nodeType === 'utility.limit_accounts') {
            $payload = is_array($state['post_payload'] ?? null) ? $state['post_payload'] : null;
            if ($payload === null) {
                throw new InvalidArgumentException('Limit accounts node requires a prepared post payload.');
            }
            $selected = is_array($payload['platform_accounts'] ?? null) ? $payload['platform_accounts'] : [];
            $selected = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $selected)));
            $selected = array_values(array_filter($selected, static fn (int $v): bool => $v > 0));
            if ($selected === []) {
                throw new InvalidArgumentException('Limit accounts node requires platform accounts in payload.');
            }

            $max = max(1, (int) ($config['max'] ?? 1));
            $payload['platform_accounts'] = array_slice($selected, 0, $max);
            $state['post_payload'] = $payload;

            return ['state' => $state, 'output' => ['accounts' => $payload['platform_accounts'], 'max' => $max]];
        }

        if ($nodeType === 'utility.delay') {
            $seconds = max(0, min(10, (int) ($config['seconds'] ?? 1)));
            if ($seconds > 0) {
                usleep($seconds * 1000000);
            }

            return ['state' => $state, 'output' => ['slept_seconds' => $seconds]];
        }

        if ($nodeType === 'utility.condition') {
            $left = (string) ($config['left'] ?? '');
            $operator = (string) ($config['operator'] ?? 'equals');
            $right = (string) ($config['right'] ?? '');
            $actual = $left === 'draft' ? (string) ($state['draft'] ?? '') : (string) (($state['vars'][$left] ?? '') ?: '');

            $result = match ($operator) {
                'contains' => str_contains(strtolower($actual), strtolower($right)),
                default => strtolower($actual) === strtolower($right),
            };
            if (!$result) {
                throw new InvalidArgumentException('Condition node failed; stopping workflow run.');
            }

            return ['state' => $state, 'output' => ['condition_passed' => true]];
        }

        if ($nodeType === 'ai.generate_caption') {
            $instruction = trim((string) ($config['instruction'] ?? 'Improve this post.'));
            $draft = (string) ($state['draft'] ?? '');
            if ($draft === '') {
                $draft = (string) ($context['draft'] ?? '');
            }
            if ($draft === '') {
                throw new InvalidArgumentException('AI node requires draft text.');
            }

            $ai = $this->composerAiChatService->complete($user, $instruction, $draft);
            $state['draft'] = (string) $ai->reply;

            return [
                'state' => $state,
                'output' => ['draft' => $state['draft'], 'billing_source' => $ai->billingSource],
                'ai_tokens_used' => max(0, (int) $ai->totalTokens),
            ];
        }

        if ($nodeType === 'action.compose_post') {
            $draft = trim((string) ($state['draft'] ?? ''));
            if ($draft === '') {
                throw new InvalidArgumentException('Compose node requires draft content.');
            }
            $accounts = is_array($config['platform_account_ids'] ?? null) ? $config['platform_account_ids'] : [];
            $accounts = array_values(array_unique(array_map(static fn ($v): int => (int) $v, $accounts)));
            $accounts = array_values(array_filter($accounts, static fn (int $v): bool => $v > 0));
            if ($accounts === []) {
                throw new InvalidArgumentException('Compose node requires at least one platform account id.');
            }

            $state['post_payload'] = [
                'content' => $draft,
                'platform_accounts' => $accounts,
                'audience' => (string) ($config['audience'] ?? 'everyone'),
                'platform_content' => is_array($config['platform_content'] ?? null) ? $config['platform_content'] : [],
                'media_paths' => is_array($config['media_paths'] ?? null) ? $config['media_paths'] : [],
            ];

            return ['state' => $state, 'output' => ['post_payload_ready' => true, 'accounts' => $accounts]];
        }

        if ($nodeType === 'action.publish_post') {
            $payload = is_array($state['post_payload'] ?? null) ? $state['post_payload'] : null;
            if ($payload === null) {
                throw new InvalidArgumentException('Publish node requires a prepared post payload.');
            }

            $post = DB::transaction(fn () => $this->postSchedulingService->publishNow($user->id, $payload));
            $state['last_post_id'] = $post->id;
            $state['last_publish_status'] = $post->status;

            return ['state' => $state, 'output' => ['post_id' => $post->id, 'status' => $post->status]];
        }

        throw new InvalidArgumentException("Unsupported workflow node type: {$nodeType}");
    }
}

