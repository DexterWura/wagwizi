<?php

namespace App\Controllers;

use App\Jobs\QueueTemplatedEmailForUserJob;
use App\Models\Plan;
use App\Models\PlanChange;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\Faq;
use App\Models\Testimonial;
use App\Services\Billing\CurrencyDisplayService;
use App\Services\Billing\PaymentGatewayConfigService;
use App\Services\Billing\SubscriptionFulfillmentService;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\Subscription\SubscriptionAccessService;
use App\Services\SocialAccount\SocialAccountLimitService;
use App\Services\Subscription\SubscriptionTrialService;
use App\Services\Insights\AudienceInsightsService;
use App\Services\Landing\LandingFeaturesDeepService;
use App\Services\Ai\PlatformAiQuotaService;
use App\Services\Platform\Platform;
use App\Services\Media\MediaLibraryService;
use App\Services\Notifications\InAppNotificationService;
use App\Services\Platform\PlatformRegistry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function landing(): View
    {
        $registry = app(PlatformRegistry::class);
        $enabledPlatforms = $registry->enabledPlatforms();
        $testimonials = Testimonial::active()->ordered()->get();
        $plans = Plan::active()->get();
        $faqs = Faq::active()->ordered()->get();

        $heroEyebrow = trim((string) SiteSetting::get('hero_eyebrow', ''));
        if ($heroEyebrow === '') {
            $heroEyebrow = 'Social OS';
        }

        $heroHeading = trim((string) SiteSetting::get('hero_heading', ''));
        if ($heroHeading === '') {
            $heroHeading = 'Your agentic social media scheduling tool';
        }

        $heroSubheading = trim((string) SiteSetting::get('hero_subheading', ''));
        if ($heroSubheading === '') {
            $heroSubheading = 'One workspace to compose, preview every network, schedule with drag-and-drop, and ship with confidence — powered by the same polished app UI you already use.';
        }

        $featuresDeepService = app(LandingFeaturesDeepService::class);
        $landingFeaturesDeep = [];
        foreach ($featuresDeepService->resolvedBlocks() as $row) {
            $row['cta_url']         = $featuresDeepService->resolveCtaHref((string) ($row['cta_href'] ?? ''));
            $row['icon_class_list'] = $featuresDeepService->parseIconClasses((string) ($row['icon_classes'] ?? ''));
            $landingFeaturesDeep[]  = $row;
        }

        $currencyDisplay = app(CurrencyDisplayService::class);

        return view('index', compact(
            'enabledPlatforms',
            'testimonials',
            'plans',
            'faqs',
            'heroEyebrow',
            'heroHeading',
            'heroSubheading',
            'landingFeaturesDeep',
            'currencyDisplay'
        ));
    }

    public function terms(): View
    {
        return view('terms');
    }

    public function privacy(): View
    {
        return view('privacy');
    }

    public function dashboard(Request $request): View
    {
        $user = Auth::user();
        $data = app(DashboardMetricsService::class)->build($user, $request);

        $range    = $data['range'];
        $scope    = $data['scope'];
        $platform = $data['platform'];

        $dashUrl = static function (array $overrides) use ($range, $scope, $platform): string {
            $merged = array_merge([
                'range' => $range,
                'scope' => $scope,
            ], $overrides);

            $effectiveScope = $merged['scope'] ?? $scope;
            if ($effectiveScope === DashboardMetricsService::SCOPE_PLATFORM) {
                $plat = $merged['platform'] ?? $platform;
                if (is_string($plat) && $plat !== '') {
                    $merged['platform'] = $plat;
                }
            } else {
                unset($merged['platform']);
            }

            return route('dashboard', array_filter($merged, static fn ($v) => $v !== null && $v !== ''));
        };

        $quota = app(PlatformAiQuotaService::class);

        return view('dashboard', array_merge($data, compact('dashUrl'), [
            'composerAiLocked'           => ! $user->canAccessComposerAi(),
            'composerAiQuotaExhausted'     => $quota->isPlatformAiQuotaExhausted($user),
            'composerAiPlanNoPlatformAi' => $quota->isPlatformAiDisabledOnPlan($user),
        ]));
    }

    public function composer(): View
    {
        $user = Auth::user();

        $audienceInsights = app(AudienceInsightsService::class)->buildForUser($user);
        $composerMediaCounts = app(MediaLibraryService::class)->typeCountsForUser($user);

        $quota = app(PlatformAiQuotaService::class);

        $composerPlatformMediaCaps = collect(config('platforms'))
            ->map(static fn (array $cfg): array => [
                'supports_images'   => (bool) ($cfg['supports_images'] ?? false),
                'supports_video'    => (bool) ($cfg['supports_video'] ?? false),
                'supports_carousel' => (bool) ($cfg['supports_carousel'] ?? false),
            ])
            ->all();

        $composerPlatformLabels = collect(Platform::cases())
            ->mapWithKeys(static fn (Platform $p): array => [$p->value => $p->label()])
            ->all();

        return view('composer', [
            'socialAccounts'               => $user->socialAccounts()->active()->get(['id', 'platform', 'username', 'display_name']),
            'audienceInsights'             => $audienceInsights,
            'composerAiLocked'             => ! $user->canAccessComposerAi(),
            'composerAiQuotaExhausted'     => $quota->isPlatformAiQuotaExhausted($user),
            'composerAiPlanNoPlatformAi'   => $quota->isPlatformAiDisabledOnPlan($user),
            'composerMediaCounts'          => $composerMediaCounts,
            'composerPlatformMediaCaps'    => $composerPlatformMediaCaps,
            'composerPlatformMediaRules'   => config('platform_media_constraints'),
            'composerPlatformLabels'       => $composerPlatformLabels,
        ]);
    }

    public function posts(): View
    {
        return view('posts-index');
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
        $user->loadMissing('subscription.planModel');
        $plan = $user->subscription?->planModel;
        $enabledPlatforms = $registry->enabledForPlan($plan);
        $limits = app(SocialAccountLimitService::class)->summary($user);

        return view('accounts', [
            'connectedAccounts' => $user->socialAccounts()->get(['id', 'platform', 'username', 'display_name', 'status', 'metadata']),
            'enabledPlatforms'  => $enabledPlatforms,
            'canAddSocialAccounts'     => $limits['canAdd'],
            'socialAccountLimit'        => $limits['max'],
            'socialAccountActiveTotal'  => $limits['active'],
        ]);
    }

    public function insights(Request $request): View
    {
        $user = Auth::user();

        $statusCounts = $user->posts()
            ->whereIn('status', ['published', 'scheduled'])
            ->selectRaw("status, count(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalPublished = (int) ($statusCounts['published'] ?? 0);
        $totalScheduled = (int) ($statusCounts['scheduled'] ?? 0);

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
        $gatewayCfg   = app(PaymentGatewayConfigService::class);
        $fulfillment  = app(SubscriptionFulfillmentService::class);

        $paidPlanSlugs = $plans->filter(static fn (Plan $p): bool => $fulfillment->requiresOnlinePayment($p))
            ->pluck('slug')
            ->values()
            ->all();

        $currencyDisplay = app(CurrencyDisplayService::class);

        $freePlanSlug = $plans->where('is_free', true)->sortBy('sort_order')->first()?->slug;

        return view('plans', [
            'currentSubscription'           => $subscription,
            'plans'                         => $plans,
            'paynowCheckoutAvailable'       => $gatewayCfg->hostedCheckoutAvailable(),
            'checkoutGateway'               => $gatewayCfg->activeCheckoutGateway(),
            'checkoutRequiresGatewayChoice' => $gatewayCfg->checkoutRequiresGatewayChoice(),
            'defaultCheckoutGateway'        => $gatewayCfg->defaultCheckoutGatewayForUi(),
            'paidPlanSlugs'                 => $paidPlanSlugs,
            'currencyDisplay'               => $currencyDisplay,
            'subscriptionAccess'            => app(SubscriptionAccessService::class),
            'freePlanSlug'                  => $freePlanSlug,
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
            'marketingEmailOptIn'  => (bool) ($user->marketing_email_opt_in ?? false),
            'notifPreferences'     => $user->notification_preferences ?? [
                'email_on_failure' => true,
                'weekly_digest'    => true,
                'product_updates'  => false,
            ],
            'platformAiTokenSummary' => app(PlatformAiQuotaService::class)->summaryForLayout($user),
        ]);
    }

    public function changePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_slug' => ['required', 'string', Rule::exists('plans', 'slug')->where('is_active', true)],
        ]);

        $user    = Auth::user();
        $newPlan = Plan::where('slug', $validated['plan_slug'])->where('is_active', true)->firstOrFail();
        $oldSub  = $user->subscription;
        $oldPlanId = $oldSub?->plan_id;

        $access = app(SubscriptionAccessService::class);
        if ($access->userHasActiveAccessToPlan($user, $newPlan)) {
            return response()->json([
                'success' => false,
                'message' => 'You are already on this plan.',
            ], 422);
        }

        if ($newPlan->is_lifetime && $newPlan->hasReachedLifetimeCap()) {
            return response()->json([
                'success' => false,
                'message' => 'This lifetime deal has reached its subscriber limit.',
            ], 422);
        }

        $fulfillment  = app(SubscriptionFulfillmentService::class);
        $gateways     = app(PaymentGatewayConfigService::class);
        $trialService = app(SubscriptionTrialService::class);

        if ($gateways->hostedCheckoutAvailable() && $fulfillment->requiresOnlinePayment($newPlan)) {
            if ($trialService->canStartTrial($user, $newPlan)) {
                try {
                    $trialService->startTrial($user, $newPlan, $oldPlanId);
                } catch (\RuntimeException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ], 422);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Your trial for ' . $newPlan->name . ' has started.',
                    'trial'   => true,
                ]);
            }

            return response()->json([
                'success'           => false,
                'checkout_required' => true,
                'message'           => 'Complete checkout to activate this plan.',
            ], 402);
        }

        $directPayload = [
            'plan_id'              => $newPlan->id,
            'plan'                 => $newPlan->slug,
            'status'               => 'active',
            'current_period_start' => now(),
            'current_period_end'   => $newPlan->is_lifetime ? null : now()->addMonth(),
            'trial_ends_at'        => null,
        ];

        if (! $fulfillment->requiresOnlinePayment($newPlan)) {
            $directPayload['gateway']                 = null;
            $directPayload['gateway_subscription_id'] = null;
        }

        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id],
            $directPayload
        );

        app(PlatformAiQuotaService::class)->applyPlanBudgetToSubscription($subscription, $newPlan);

        if ($newPlan->is_lifetime && $oldPlanId !== $newPlan->id) {
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
                'change_type'  => 'upgrade',
            ]);
        }

        if ($oldPlanId !== $newPlan->id) {
            $oldPlan = $oldPlanId ? Plan::find($oldPlanId) : null;
            $templateKey = ($oldPlan && ! $oldPlan->is_free && $newPlan->is_free)
                ? 'subscription.downgrade'
                : 'subscription.updated';

            QueueTemplatedEmailForUserJob::dispatch($user->id, $templateKey, [
                'planName'         => $newPlan->name,
                'previousPlanName' => $oldPlan?->name ?? '',
            ]);

            if (! $newPlan->is_free) {
                DB::afterCommit(function () use ($user, $newPlan): void {
                    try {
                        app(InAppNotificationService::class)->notifySuperAdminsNewSubscription($user, $newPlan);
                    } catch (\Throwable) {
                    }
                });
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Plan changed to ' . $newPlan->name . '.',
        ]);
    }
}
