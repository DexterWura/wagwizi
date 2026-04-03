<?php

namespace App\Controllers;

use App\Services\Post\PostPublishingService;
use App\Services\Post\PostSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PostController extends Controller
{
    public function __construct(
        private readonly PostSchedulingService  $schedulingService,
        private readonly PostPublishingService  $publishingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->tryServiceCall(function () use ($request) {
            $user = Auth::user();
            $query = $user->posts()->with([
                'postPlatforms:id,post_id,platform',
            ]);

            if ($request->filled('q')) {
                $term = (string) $request->query('q');
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $query->where('content', 'LIKE', $like);
            }

            if ($request->filled('status') && $request->query('status') !== 'all') {
                $query->where('status', (string) $request->query('status'));
            }

            $calendarScope = $request->has('month') && $request->has('year');
            if ($calendarScope) {
                $month = (int) $request->query('month');
                $year  = (int) $request->query('year');
                $start = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
                $end   = $start->copy()->endOfMonth();
                $query->where(function ($q) use ($start, $end) {
                    $q->whereBetween('scheduled_at', [$start, $end])
                      ->orWhere(function ($q2) {
                          $q2->where('status', 'draft')->whereNull('scheduled_at');
                      });
                });
            }

            if ($calendarScope) {
                $query->orderByRaw('scheduled_at IS NULL ASC')
                    ->orderBy('scheduled_at', 'asc')
                    ->orderByDesc('created_at');
            } elseif ($request->query('sort') === 'scheduled') {
                $query->orderByRaw('scheduled_at IS NULL ASC')
                    ->orderBy('scheduled_at', 'asc')
                    ->orderByDesc('created_at');
            } else {
                $query->orderByDesc('created_at')->orderByDesc('id');
            }

            $posts = $query->paginate($request->integer('per_page', $calendarScope ? 50 : 15));

            return response()->json(['success' => true, 'posts' => $posts]);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $post = Auth::user()->posts()
            ->with([
                'postPlatforms:id,post_id,social_account_id,platform,platform_content,first_comment,comment_delay_minutes,status',
                'mediaFiles:id,path,type,original_name,size_bytes,mime_type',
            ])
            ->findOrFail($id);

        return response()->json(['success' => true, 'post' => $post]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content'             => 'required|string|min:1|max:40000',
            'platform_accounts'   => 'nullable|array',
            'platform_accounts.*' => 'integer|exists:social_accounts,id',
            'platform_content'    => 'nullable|array',
            'platform_content.*'  => 'nullable|string|max:40000',
            'first_comment'       => 'nullable|string|max:40000',
            'comment_delay_value' => 'nullable|integer|min:1|max:10080',
            'comment_delay_unit'  => 'nullable|in:minutes,hours',
        ]);

        return $this->tryServiceCall(fn () =>
            response()->json(['post' => $this->schedulingService->createDraft(Auth::id(), $validated)], 201)
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'content'             => 'sometimes|string|min:1|max:40000',
            'scheduled_at'        => 'nullable|date|after:now',
            'platform_accounts'   => 'nullable|array',
            'platform_accounts.*' => 'integer|exists:social_accounts,id',
            'platform_content'    => 'nullable|array',
            'platform_content.*'  => 'nullable|string|max:40000',
            'first_comment'       => 'nullable|string|max:40000',
            'comment_delay_value' => 'nullable|integer|min:1|max:10080',
            'comment_delay_unit'  => 'nullable|in:minutes,hours',
        ]);

        if (empty($validated)) {
            return response()->json(['error' => 'No fields provided to update.'], 422);
        }

        return $this->tryServiceCall(fn () =>
            response()->json(['post' => $this->schedulingService->updatePost(Auth::id(), $id, $validated)])
        );
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->tryServiceCall(function () use ($id) {
            $this->schedulingService->deletePost(Auth::id(), $id);
            return response()->json(null, 204);
        });
    }

    public function schedule(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_at'        => 'nullable|date|after:now',
            'delay_value'         => 'nullable|integer|min:1|max:10080',
            'delay_unit'          => 'nullable|in:minutes,hours',
            'platform_accounts'   => 'required|array|min:1',
            'platform_accounts.*' => 'integer|exists:social_accounts,id',
            'platform_content'    => 'nullable|array',
            'platform_content.*'  => 'nullable|string|max:40000',
            'first_comment'       => 'nullable|string|max:40000',
            'comment_delay_value' => 'nullable|integer|min:1|max:10080',
            'comment_delay_unit'  => 'nullable|in:minutes,hours',
        ]);

        return $this->tryServiceCall(fn () =>
            response()->json(['post' => $this->schedulingService->scheduleExistingPost(Auth::id(), $id, $validated)])
        );
    }

    public function scheduleNew(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content'             => 'required|string|min:1|max:40000',
            'scheduled_at'        => 'nullable|date|after:now',
            'delay_value'         => 'nullable|integer|min:1|max:10080',
            'delay_unit'          => 'nullable|in:minutes,hours',
            'platform_accounts'   => 'required|array|min:1',
            'platform_accounts.*' => 'integer|exists:social_accounts,id',
            'platform_content'    => 'nullable|array',
            'platform_content.*'  => 'nullable|string|max:40000',
            'first_comment'       => 'nullable|string|max:40000',
            'comment_delay_value' => 'nullable|integer|min:1|max:10080',
            'comment_delay_unit'  => 'nullable|in:minutes,hours',
        ]);

        return $this->tryServiceCall(fn () =>
            response()->json(['post' => $this->schedulingService->schedulePost(Auth::id(), $validated)], 201)
        );
    }

    public function publish(int $id): JsonResponse
    {
        return $this->tryServiceCall(function () use ($id) {
            $post = \App\Models\Post::where('user_id', Auth::id())->findOrFail($id);

            if ($post->status === 'published') {
                return response()->json(['error' => 'This post has already been published.'], 422);
            }

            if ($post->status === 'publishing') {
                return response()->json(['error' => 'This post is already being published.'], 422);
            }

            if ($post->postPlatforms()->count() === 0) {
                return response()->json(['error' => 'No platform targets configured for this post.'], 422);
            }

            $hasPending = $post->postPlatforms()->where('status', 'pending')->exists();
            if (!$hasPending) {
                return response()->json(['error' => 'No pending platform targets to publish.'], 422);
            }

            $dispatched = $this->publishingService->dispatchPublishing($post);

            return response()->json([
                'message' => "{$dispatched} platform publish job(s) dispatched.",
                'post'    => $post->fresh(),
            ]);
        });
    }

    public function cancel(int $id): JsonResponse
    {
        return $this->tryServiceCall(fn () =>
            response()->json(['post' => $this->schedulingService->cancelScheduled(Auth::id(), $id)])
        );
    }

    public function reschedule(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_at' => 'nullable|date',
        ]);

        return $this->tryServiceCall(function () use ($id, $validated) {
            $post = \App\Models\Post::where('user_id', Auth::id())->findOrFail($id);

            if ($post->status === 'published' || $post->status === 'publishing') {
                return response()->json([
                    'error' => "Cannot reschedule a post with status '{$post->status}'.",
                ], 422);
            }

            $scheduledAt = $validated['scheduled_at'] ?? null;
            $post->update([
                'scheduled_at' => $scheduledAt,
                'status'       => $scheduledAt ? 'scheduled' : 'draft',
            ]);

            return response()->json(['success' => true, 'post' => $post->fresh()]);
        });
    }

    private function tryServiceCall(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $e) {
            Log::warning('Post operation validation failed', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            Log::warning('Post not found', ['user_id' => Auth::id()]);
            return response()->json(['error' => 'Post not found.'], 404);
        } catch (\Throwable $e) {
            Log::error('Post operation failed unexpectedly', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);
            report($e);
            return response()->json(['error' => 'Unexpected server error while processing this post action.'], 500);
        }
    }
}
