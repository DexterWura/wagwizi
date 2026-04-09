<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Workflow;
use App\Services\Workflow\WorkflowRunnerService;
use App\Services\Workflow\WorkflowTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = Workflow::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        return response()->json(['success' => true, 'workflows' => $rows]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $workflow = Workflow::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['success' => true, 'workflow' => $workflow]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'description' => 'nullable|string|max:5000',
            'status' => ['nullable', Rule::in(['draft', 'active', 'paused', 'archived'])],
            'trigger_type' => ['nullable', Rule::in(['manual', 'schedule', 'event'])],
            'trigger_config' => 'nullable|array',
            'graph' => 'nullable|array',
            'graph_version' => 'nullable|integer|min:1|max:1000',
        ]);

        $workflow = Workflow::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'draft',
            'trigger_type' => $validated['trigger_type'] ?? 'manual',
            'trigger_config' => $validated['trigger_config'] ?? [],
            'graph' => $validated['graph'] ?? ['nodes' => [], 'edges' => []],
            'graph_version' => (int) ($validated['graph_version'] ?? 1),
        ]);

        return response()->json(['success' => true, 'workflow' => $workflow], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $workflow = Workflow::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:160',
            'description' => 'nullable|string|max:5000',
            'status' => ['sometimes', Rule::in(['draft', 'active', 'paused', 'archived'])],
            'trigger_type' => ['sometimes', Rule::in(['manual', 'schedule', 'event'])],
            'trigger_config' => 'sometimes|array',
            'graph' => 'sometimes|array',
            'graph_version' => 'sometimes|integer|min:1|max:1000',
        ]);

        $workflow->update($validated);

        return response()->json(['success' => true, 'workflow' => $workflow->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $workflow = Workflow::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $workflow->delete();

        return response()->json(['success' => true]);
    }

    public function run(Request $request, int $id): JsonResponse
    {
        $workflow = Workflow::query()
            ->with('user')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $payload = $request->validate([
            'context' => 'nullable|array',
        ]);

        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $run = app(WorkflowRunnerService::class)->run($workflow, 'manual', $context);

        return response()->json([
            'success' => true,
            'run' => $run,
            'workflow' => $workflow->fresh(),
        ]);
    }

    public function runs(Request $request, int $id): JsonResponse
    {
        $workflow = Workflow::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $runs = $workflow->runs()->with('steps')->limit(30)->get();

        return response()->json(['success' => true, 'runs' => $runs]);
    }

    public function triggerEvent(Request $request, string $eventKey): JsonResponse
    {
        $payload = $request->validate([
            'payload' => 'nullable|array',
        ]);
        $count = app(WorkflowRunnerService::class)->runEventTriggeredForUser(
            $request->user(),
            $eventKey,
            is_array($payload['payload'] ?? null) ? $payload['payload'] : [],
        );

        return response()->json(['success' => true, 'triggered_runs' => $count]);
    }

    public function templates(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'templates' => app(WorkflowTemplateService::class)->templates(),
        ]);
    }
}

