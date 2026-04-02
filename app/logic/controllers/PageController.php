<?php

namespace App\Controllers;

use App\Models\Plan;
use App\Models\PlanChange;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\Testimonial;
use App\Services\Insights\AudienceInsightsService;
use App\Services\Platform\Platform;
use App\Services\Platform\PlatformRegistry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PageController extends Controller
{
    public function landing(): View
    {
        $registry = app(PlatformRegistry::class);
        $enabledPlatforms = $registry->enabledPlatforms();
        $testimonials = Testimonial::active()->ordered()->get();
        $plans = Plan::active()->get();

        return view('index', compact('enabledPlatforms', 'testimonials', 'plans'));
    }

    public function dashboard(): View
    {
        $user = Auth::user();
        $now  = Carbon::now();

        $connectedCount  = $user->socialAccounts()->active()->count();
        $scheduledCount  = $user->posts()->scheduled()->count();
        $publishedCount  = $user->posts()->published()->count();

        $recentPosts = $user->posts()
            ->whereIn('status', ['published', 'scheduled', 'draft'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'content', 'status', 'scheduled_at', 'published_at', 'updated_at']);

        $nextUp = $user->posts()
            ->scheduled()
            ->where('scheduled_at', '>', $now)
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get(['id', 'content', 'scheduled_at']);

        return view('dashboard', [
            'connectedAccountsCount' => $connectedCount,
            'scheduledPostsCount'    => $scheduledCount,
            'publishedPostsCount'    => $publishedCount,
            'recentPosts'            => $recentPosts,
            'nextUp'                 => $nextUp,
        ]);
    }

    public function composer(): View
    {
        $user = Auth::user();

        $audienceInsights = app(AudienceInsightsService::class)->buildForUser($user);

        return view('composer', [
            'socialAccounts'     => $user->socialAccounts()->active()->get(['id', 'platform', 'username', 'display_name']),
            'audienceInsights'   => $audienceInsights,
        ]);
    }

    public function calendar(): View
    {
        $user  = Auth::user();
        $now   = Carbon::now();
        $start = $now->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $end   = $now->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $scheduledPosts = $user->posts()
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('scheduled_at', [$start, $end])
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'draft')->whereNull('scheduled_at');
                  });
            })
            ->orderBy('scheduled_at')
            ->get(['id', 'content', 'status', 'scheduled_at', 'platforms']);

        $drafts = $scheduledPosts->where('status', 'draft')->whereNull('scheduled_at');
        $scheduled = $scheduledPosts->whereNotNull('scheduled_at');

        $audienceInsights = app(AudienceInsightsService::class)->buildForUser($user);

        return view('calendar', [
            'scheduledPosts'   => $scheduled,
            'draftPosts'       => $drafts,
            'currentMonth'     => $now->format('F Y'),
            'calendarStart'    => $start,
            'calendarEnd'      => $end,
            'today'            => $now,
            'audienceInsights' => $audienceInsights,
        ]);
    }

    public function mediaLibrary(): View
    {
        $user = Auth::user();

        $media = $user->mediaFiles()
            ->orderByDesc('created_at')
            ->paginate(24);

        return view('media-library', [
            'mediaFiles' => $media,
        ]);
    }

    public function accounts(): View
    {
        $user = Auth::user();
        $registry = app(PlatformRegistry::class);
        $plan = $user->subscription?->planModel;
        $enabledPlatforms = $registry->enabledForPlan($plan);

        return view('accounts', [
            'connectedAccounts' => $user->socialAccounts()->get(['id', 'platform', 'username', 'display_name', 'status', 'metadata']),
            'enabledPlatforms'  => $enabledPlatforms,
        ]);
    }

    public function insights(Request $request): View
    {
        $user = Auth::user();

        $totalPublished = $user->posts()->published()->count();
        $totalScheduled = $user->posts()->scheduled()->count();

        $platformCounts = $user->socialAccounts()
            ->active()
            ->selectRaw('platform, count(*) as total')
            ->groupBy('platform')
            ->pluck('total', 'platform');

        $from = $request->query('from') ? Carbon::parse($request->query('from')) : null;
        $to = $request->query('to') ? Carbon::parse($request->query('to')) : null;

        $audienceInsights = app(AudienceInsightsService::class)->buildForUser($user, $from, $to);

        return view('insights', [
            'totalPublished'   => $totalPublished,
            'totalScheduled'   => $totalScheduled,
            'platformCounts'   => $platformCounts,
            'audienceInsights' => $audienceInsights,
            'insightsFrom'     => $from,
            'insightsTo'       => $to,
        ]);
    }

    public function plans(): View
    {
        $user         = Auth::user();
        $subscription = $user->subscription;
        $plans        = Plan::active()->get();

        return view('plans', [
            'currentSubscription' => $subscription,
            'plans'               => $plans,
        ]);
    }

    public function planHistory(): View
    {
        $user = Auth::user();

        return view('plan-history', [
            'planChanges' => $user->planChanges()
                ->with(['fromPlan:id,name', 'toPlan:id,name'])
                ->latest('created_at')
                ->paginate(20),
        ]);
    }

    public function profile(): View
    {
        return view('profile');
    }

    public function settings(): View
    {
        $user = Auth::user();

        return view('settings', [
            'workspaceName'        => $user->workspace_name ?? 'Personal brand',
            'workspaceSlug'        => $user->workspace_slug ?? 'personal-brand',
            'defaultPostingTime'   => $user->default_posting_time ?? '09:00',
            'notifPreferences'     => $user->notification_preferences ?? [
                'email_on_failure' => true,
                'weekly_digest'    => true,
                'product_updates'  => false,
            ],
        ]);
    }

    public function changePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_slug' => 'required|string|exists:plans,slug',
        ]);

        $user    = Auth::user();
        $newPlan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();
        $oldSub  = $user->subscription;
        $oldPlanId = $oldSub?->plan_id;

        if ($newPlan->is_lifetime && $newPlan->hasReachedLifetimeCap()) {
            return response()->json([
                'success' => false,
                'message' => 'This lifetime deal has reached its subscriber limit.',
            ], 422);
        }

        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan_id'              => $newPlan->id,
                'plan'                 => $newPlan->slug,
                'status'               => 'active',
                'current_period_start' => now(),
                'current_period_end'   => $newPlan->is_lifetime ? null : now()->addMonth(),
            ]
        );

        if ($newPlan->is_lifetime) {
            $newPlan->increment('lifetime_current_count');
        }

        if ($oldPlanId && $oldPlanId !== $newPlan->id) {
            $oldPlan = Plan::find($oldPlanId);
            if ($oldPlan?->is_lifetime) {
                $oldPlan->decrement('lifetime_current_count');
            }

            PlanChange::create([
                'user_id'      => $user->id,
                'from_plan_id' => $oldPlanId,
                'to_plan_id'   => $newPlan->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Plan changed to ' . $newPlan->name . '.',
        ]);
    }
}
