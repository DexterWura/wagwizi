<?php

namespace App\Controllers;

use App\Services\Cache\UserCacheVersionService;
use App\Services\Post\PostPublishingService;
use App\Services\Post\PostSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
            $cacheTtl = 20;
            $cacheVersion = app(UserCacheVersionService::class)->current($user->id);
            $queryHash = sha1(json_encode([
                'q' => (string) $request->query('q', ''),
                'status' => (string) $request->query('status', ''),
                'sort' => (string) $request->query('sort', ''),
                'month' => (string) $request->query('month', ''),
                'year' => (string) $request->query('year', ''),
                'page' => (int) $request->query('page', 1),
                'per_page' => (int) $request->query('per_page', 0),
            ]));
            $cacheKey = "posts_index:v1:{$cacheVersion}:user:{$user->id}:{$queryHash}";

            $payload = Cache::remember($cacheKey, $cacheTtl, function () use ($request, $user): array {
                $query = $user->posts()
                    ->select(['id', 'user_id', 'content', 'status', 'scheduled_at', 'published_at', 'created_at'])
                    ->with([
                        'postPlatforms:id,post_id,platform',
                        'mediaFiles:id,path,type,original_name',
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
                return $posts->toArray();
            });

            return response()->json(['success' => true, 'posts' => $payload]);
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
            'media_file_id'       => 'nullable|integer|exists:media_files,id',
            'media_file_ids'      => 'nullable|array',
            'media_file_ids.*'    => 'integer|exists:media_files,id',
            'media_path'          => 'nullable|string|max:2048',
            'media_paths'         => 'nullable|array',
            'media_paths.*'       => 'string|max:2048',
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
            'media_file_id'       => 'nullable|integer|exists:media_files,id',
            'media_file_ids'      => 'nullable|array',
            'media_file_ids.*'    => 'integer|exists:media_files,id',
            'media_path'          => 'nullable|string|max:2048',
            'media_paths'         => 'nullable|array',
            'media_paths.*'       => 'string|max:2048',
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
            'content'             => 'sometimes|string|min:1|max:40000',
            'scheduled_at'        => 'nullable|date|after:now',
            'platform_accounts'   => 'required|array|min:1',
            'platform_accounts.*' => 'integer|exists:social_accounts,id',
            'platform_content'    => 'nullable|array',
            'platform_content.*'  => 'nullable|string|max:40000',
            'media_file_id'       => 'nullable|integer|exists:media_files,id',
            'media_file_ids'      => 'nullable|array',
            'media_file_ids.*'    => 'integer|exists:media_files,id',
            'media_path'          => 'nullable|string|max:2048',
            'media_paths'         => 'nullable|array',
            'media_paths.*'       => 'string|max:2048',
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
            'platform_accounts'   => 'required|array|min:1',
            'platform_accounts.*' => 'integer|exists:social_accounts,id',
            'platform_content'    => 'nullable|array',
            'platform_content.*'  => 'nullable|string|max:40000',
            'media_file_id'       => 'nullable|integer|exists:media_files,id',
            'media_file_ids'      => 'nullable|array',
            'media_file_ids.*'    => 'integer|exists:media_files,id',
            'media_path'          => 'nullable|string|max:2048',
            'media_paths'         => 'nullable|array',
            'media_paths.*'       => 'string|max:2048',
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

    public function retryFailedPlatforms(int $id): JsonResponse
    {
        return $this->tryServiceCall(function () use ($id) {
            $post = \App\Models\Post::where('user_id', Auth::id())->findOrFail($id);

            if (! $post->postPlatforms()->where('status', 'failed')->exists()) {
                return response()->json(['error' => 'No failed platform targets to retry.'], 422);
            }

            if ($post->postPlatforms()->where('status', 'publishing')->exists()) {
                return response()->json(['error' => 'This post is still publishing. Try again when it finishes.'], 422);
            }

            $dispatched = $this->publishingService->retryFailedPlatforms($post);

            return response()->json([
                'message' => "{$dispatched} platform publish job(s) dispatched.",
                'post'    => $post->fresh(),
            ]);
        });
    }

    public function publishSummary(int $id): JsonResponse
    {
        return $this->tryServiceCall(function () use ($id) {
            $post = \App\Models\Post::where('user_id', Auth::id())
                ->with('postPlatforms:id,post_id,platform,status,error_message')
                ->findOrFail($id);

            $rows = $post->postPlatforms;
            $total = $rows->count();
            $published = $rows->where('status', 'published')->count();
            $failed = $rows->where('status', 'failed')->count();
            $pending = $rows->filter(fn ($pp) => in_array($pp->status, ['pending', 'publishing'], true))->count();
            $done = $pending === 0;

            $failures = $rows
                ->where('status', 'failed')
                ->map(fn ($pp) => [
                    'platform' => $pp->platform,
                    'error'    => $pp->error_message,
                ])
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'summary' => [
                    'post_id'            => $post->id,
                    'post_status'        => $post->status,
                    'done'               => $done,
                    'all_successful'     => $done && $failed === 0 && $published === $total && $total > 0,
                    'total_platforms'    => $total,
                    'published_count'    => $published,
                    'failed_count'       => $failed,
                    'pending_count'      => $pending,
                    'failures'           => $failures,
                    'can_retry_failed'   => $failed > 0,
                ],
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
