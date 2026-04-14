<?php

namespace App\Controllers;

use App\Jobs\QueueTemplatedEmailForUserJob;
use App\Models\MediaFile;
use App\Models\Plan;
use App\Models\PlanChange;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Services\Ai\AiOutboundUrlValidator;
use App\Services\Billing\CurrencyDisplayService;
use App\Services\Billing\PaymentGatewayConfigService;
use App\Services\Billing\SubscriptionFulfillmentService;
use App\Services\Cache\PublicCatalogCache;
use App\Services\Cache\UserCacheVersionService;
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
use App\Services\Workflow\WorkflowTemplateService;
use App\Services\Workspace\WorkspaceAccessService;
use App\Services\Tools\ToolAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function landing(): View
    {
        $registry = app(PlatformRegistry::class);
        $enabledPlatforms = $registry->enabledPlatforms();
        $testimonials = PublicCatalogCache::activeTestimonials();
        $plans        = PublicCatalogCache::activePlans();
        $planSupportedPlatforms = [];
        foreach ($plans as $plan) {
            $planSupportedPlatforms[$plan->slug] = array_values(
                array_filter($enabledPlatforms, static fn (Platform $p): bool => $plan->allowsPlatform($p->value))
            );
        }
        $faqs         = PublicCatalogCache::activeFaqs();

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

        $user = null;
        $subscriptionAccess = null;
        $landingCheckout = null;
        if (Auth::check()) {
            $user = Auth::user();
            $user->loadMissing('subscription.planModel');
            $subscriptionAccess = app(SubscriptionAccessService::class);
            $gatewayCfg = app(PaymentGatewayConfigService::class);
            $fulfillment = app(SubscriptionFulfillmentService::class);
            $landingCheckout = [
                'hosted_available'  => $gatewayCfg->hostedCheckoutAvailable(),
                'paid_plan_slugs'     => $plans->filter(static fn (Plan $p): bool => $fulfillment->requiresOnlinePayment($p))
                    ->pluck('slug')
                    ->values()
                    ->all(),
                'free_plan_slug'      => $plans->where('is_free', true)->sortBy('sort_order')->first()?->slug,
                'current_plan_slug'   => $user->subscription?->plan ?? '',
                'checkout_mode'       => $gatewayCfg->checkoutRequiresGatewayChoice() ? 'choose' : 'single',
                'default_gateway'     => $gatewayCfg->defaultCheckoutGatewayForUi(),
                'gateways'            => $gatewayCfg->availableCheckoutGateways(),
            ];
        }

        return view('index', compact(
            'enabledPlatforms',
            'testimonials',
            'plans',
            'faqs',
            'heroEyebrow',
            'heroHeading',
            'heroSubheading',
            'landingFeaturesDeep',
            'currencyDisplay',
            'user',
            'subscriptionAccess',
            'landingCheckout',
            'planSupportedPlatforms'
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

    public function tools(): View
    {
        $user = Auth::user();
        $toolAccess = app(ToolAccessService::class);
        $toolCatalog = $toolAccess->catalog();
        $tools = [];

        foreach ($toolCatalog as $slug => $meta) {
            $decision = $toolAccess->evaluateUserAccess($user, $slug);
            $actionUrl = null;
            $actionLabel = null;
            $implemented = false;
            $isDownload = (string) ($meta['category'] ?? '') === 'Downloads';

            if ($slug === 'ai_caption_generator') {
                $actionUrl = route('composer');
                $actionLabel = 'Open Composer';
                $implemented = true;
            } elseif ($slug === 'bulk_media_import' || $slug === 'canva_export_import') {
                $actionUrl = route('media-library');
                $actionLabel = 'Open Media Library';
                $implemented = true;
            }

            $tools[] = [
                'slug' => $slug,
                'label' => (string) ($meta['label'] ?? $slug),
                'category' => (string) ($meta['category'] ?? 'Tools'),
                'enabled' => (bool) ($decision['allowed'] ?? false),
                'message' => (string) ($decision['message'] ?? ''),
                'implemented' => $implemented,
                'is_download' => $isDownload,
                'action_url' => $actionUrl,
                'action_label' => $actionLabel,
            ];
        }

        usort($tools, static function (array $a, array $b): int {
            if ($a['enabled'] !== $b['enabled']) {
                return $a['enabled'] ? -1 : 1;
            }
            if ($a['implemented'] !== $b['implemented']) {
                return $a['implemented'] ? -1 : 1;
            }
            return strcasecmp($a['label'], $b['label']);
        });

        return view('tools', [
            'tools' => $tools,
        ]);
    }

    public function toolDownload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tool_slug' => 'required|string|max:120',
            'media_url' => 'required|url|max:2000',
        ]);

        $user = Auth::user();
        $toolAccess = app(ToolAccessService::class);
        $toolSlug = trim(strtolower($validated['tool_slug']));
        $catalog = $toolAccess->catalog();
        $meta = $catalog[$toolSlug] ?? null;
        if (! is_array($meta) || (($meta['category'] ?? '') !== 'Downloads')) {
            return back()->with('error', 'This download tool is not available.');
        }

        $decision = $toolAccess->evaluateUserAccess($user, $toolSlug);
        if (! ($decision['allowed'] ?? false)) {
            return back()->with('error', (string) ($decision['message'] ?? 'Your plan does not allow this tool.'));
        }

        $mediaUrl = (string) $validated['media_url'];
        try {
            app(AiOutboundUrlValidator::class)->assertSafeForServerSideHttp($mediaUrl);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['media_url' => $e->getMessage()])->withInput();
        }

        $resp = Http::timeout(45)
            ->withOptions(['allow_redirects' => true])
            ->get($mediaUrl);

        if (! $resp->successful()) {
            return back()->with('error', 'Could not fetch that URL. Please check the link and try again.');
        }

        $contentLength = (int) ($resp->header('Content-Length') ?? 0);
        $maxBytes = 50 * 1024 * 1024;
        if ($contentLength > $maxBytes) {
            return back()->with('error', 'The file is too large. Maximum size is 50 MB.');
        }

        $body = $resp->body();
        $sizeBytes = strlen($body);
        if ($sizeBytes < 1) {
            return back()->with('error', 'No media file was returned by that URL.');
        }
        if ($sizeBytes > $maxBytes) {
            return back()->with('error', 'The file is too large. Maximum size is 50 MB.');
        }

        $rawType = strtolower(trim((string) $resp->header('Content-Type', '')));
        $mimeType = explode(';', $rawType)[0] ?? '';
        [$mediaType, $extension] = $this->detectDownloadMediaType($mediaUrl, $mimeType);
        if ($mediaType === null || $extension === null) {
            return back()->with('error', 'Unsupported file format. Use a direct image/video URL.');
        }

        $subDir = $mediaType === 'video' ? 'videos' : 'images';
        $destinationDir = public_path('assets/uploads/' . $subDir);
        if (! is_dir($destinationDir)) {
            @mkdir($destinationDir, 0775, true);
        }
        if (! is_dir($destinationDir)) {
            return back()->with('error', 'Upload directory is not writable.');
        }

        $fileName = (string) Str::uuid() . '.' . $extension;
        $fullPath = $destinationDir . DIRECTORY_SEPARATOR . $fileName;
        if (file_put_contents($fullPath, $body) === false) {
            return back()->with('error', 'Could not save downloaded media.');
        }

        $path = 'assets/uploads/' . $subDir . '/' . $fileName;
        $sourcePath = (string) parse_url($mediaUrl, PHP_URL_PATH);
        $originalName = basename($sourcePath);
        if (! is_string($originalName) || trim($originalName) === '' || $originalName === '/' || $originalName === '.') {
            $originalName = $toolSlug . '-' . now()->format('Ymd-His') . '.' . $extension;
        }

        MediaFile::create([
            'user_id' => $user->id,
            'file_name' => $fileName,
            'original_name' => $originalName,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $mimeType !== '' ? $mimeType : ($mediaType === 'video' ? 'video/mp4' : 'image/jpeg'),
            'size_bytes' => $sizeBytes,
            'type' => $mediaType,
            'metadata' => [
                'source_url' => $mediaUrl,
                'imported_via_tool' => $toolSlug,
            ],
        ]);

        app(UserCacheVersionService::class)->bump((int) $user->id);

        return redirect()->route('media-library')->with('success', 'Media downloaded and added to your library.');
    }

    /**
     * @return array{0: ('image'|'video')|null, 1: string|null}
     */
    private function detectDownloadMediaType(string $mediaUrl, string $mimeType): array
    {
        $mimeMap = [
            'image/jpeg' => ['image', 'jpg'],
            'image/png' => ['image', 'png'],
            'image/gif' => ['image', 'gif'],
            'image/webp' => ['image', 'webp'],
            'video/mp4' => ['video', 'mp4'],
            'video/webm' => ['video', 'webm'],
            'video/quicktime' => ['video', 'mov'],
            'video/x-msvideo' => ['video', 'avi'],
        ];
        if (isset($mimeMap[$mimeType])) {
            return $mimeMap[$mimeType];
        }

        $path = strtolower((string) parse_url($mediaUrl, PHP_URL_PATH));
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $extMap = [
            'jpg' => ['image', 'jpg'],
            'jpeg' => ['image', 'jpg'],
            'png' => ['image', 'png'],
            'gif' => ['image', 'gif'],
            'webp' => ['image', 'webp'],
            'mp4' => ['video', 'mp4'],
            'webm' => ['video', 'webm'],
            'mov' => ['video', 'mov'],
            'avi' => ['video', 'avi'],
        ];

        return $extMap[$ext] ?? [null, null];
    }

    public function composer(): View
    {
        $user = Auth::user();
        $cacheVersion = app(UserCacheVersionService::class)->current($user->id);

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

        $socialAccounts = Cache::remember(
            "composer_social_accounts:v1:{$cacheVersion}:{$user->id}",
            60,
            fn () => $user->socialAccounts()->active()->get(['id', 'platform', 'username', 'display_name', 'avatar_url'])
        );

        $composerPlatformProfiles = $socialAccounts->mapWithKeys(function ($account) {
            $plat = Platform::tryFrom($account->platform);
            $label = $plat?->label() ?? ucfirst((string) $account->platform);
            $name  = trim((string) ($account->display_name ?? ''));
            if ($name === '') {
                $name = trim((string) ($account->username ?? ''));
            }
            if ($name === '') {
                $name = $label;
            }

            return [
                (string) $account->id => [
                    'platform'  => $account->platform,
                    'avatar'    => $account->composerPreviewAvatarUrl(),
                    'name'      => $name,
                    'iconClass' => $plat?->icon() ?? 'fa-solid fa-globe',
                ],
            ];
        })->all();

        return view('composer', [
            'socialAccounts'               => $socialAccounts,
            'composerPlatformProfiles'     => $composerPlatformProfiles,
            'audienceInsights'             => $audienceInsights,
            'composerAiLocked'             => ! $user->canAccessComposerAi(),
            'composerAiQuotaExhausted'     => $quota->isPlatformAiQuotaExhausted($user),
            'composerAiPlanNoPlatformAi'   => $quota->isPlatformAiDisabledOnPlan($user),
            'composerRepliesAllowed'       => $user->canUseFirstCommentReplies(),
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
        $cacheVersion = app(UserCacheVersionService::class)->current($user->id);
        $now   = Carbon::now();
        $start = $now->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $end   = $now->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $calendarPosts = Cache::remember(
            "calendar_posts:v1:{$cacheVersion}:{$user->id}:{$start->toDateString()}:{$end->toDateString()}",
            30,
            function () use ($user, $start, $end) {
                return $user->posts()
                    ->where(function ($q) use ($start, $end) {
                        $q->whereBetween('scheduled_at', [$start, $end])
                          ->orWhereBetween('published_at', [$start, $end])
                          ->orWhere(function ($q2) {
                              $q2->where('status', 'draft')->whereNull('scheduled_at');
                          });
                    })
                    ->orderByRaw('COALESCE(scheduled_at, published_at, created_at) asc')
                    ->get(['id', 'content', 'status', 'scheduled_at', 'published_at', 'platforms']);
            }
        );

        $drafts = $calendarPosts->where('status', 'draft')->whereNull('scheduled_at');
        $scheduled = $calendarPosts->filter(
            fn ($post) => $post->scheduled_at !== null || $post->published_at !== null
        );

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
            'connectedAccounts' => $user->socialAccounts()->get(['id', 'platform', 'platform_user_id', 'username', 'display_name', 'status', 'metadata', 'created_at']),
            'enabledPlatforms'  => $enabledPlatforms,
            'canAddSocialAccounts'     => $limits['canAdd'],
            'socialAccountLimit'        => $limits['max'],
            'socialAccountActiveTotal'  => $limits['active'],
            'socialAccountPerPlatformLimit' => $limits['maxPerPlatform'],
        ]);
    }

    public function insights(Request $request): View
    {
        $user = Auth::user();
        $cacheVersion = app(UserCacheVersionService::class)->current($user->id);

        $statusCounts = Cache::remember("insights_status_counts:v1:{$cacheVersion}:{$user->id}", 90, function () use ($user) {
            return $user->posts()
                ->whereIn('status', ['published', 'scheduled'])
                ->selectRaw("status, count(*) as total")
                ->groupBy('status')
                ->pluck('total', 'status');
        });

        $totalPublished = (int) ($statusCounts['published'] ?? 0);
        $totalScheduled = (int) ($statusCounts['scheduled'] ?? 0);

        $platformCounts = Cache::remember("insights_platform_counts:v1:{$cacheVersion}:{$user->id}", 90, function () use ($user) {
            return $user->socialAccounts()
                ->active()
                ->selectRaw('platform, count(*) as total')
                ->groupBy('platform')
                ->pluck('total', 'platform');
        });

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

    public function workflows(): View
    {
        $user = Auth::user();
        $accounts = $user->socialAccounts()
            ->active()
            ->get(['id', 'platform', 'display_name', 'username']);

        return view('workflows', [
            'workflowTemplates' => app(WorkflowTemplateService::class)->templates(),
            'workflowAccounts' => $accounts,
        ]);
    }

    public function plans(): View
    {
        $user = Auth::user();
        $user->loadMissing('subscription.planModel');
        $subscription = $user->subscription;
        $plans        = PublicCatalogCache::activePlans();
        $registry     = app(PlatformRegistry::class);
        $enabledPlatforms = $registry->enabledPlatforms();
        $gatewayCfg   = app(PaymentGatewayConfigService::class);
        $fulfillment  = app(SubscriptionFulfillmentService::class);
        $planSupportedPlatforms = [];
        foreach ($plans as $plan) {
            $planSupportedPlatforms[$plan->slug] = array_values(
                array_filter($enabledPlatforms, static fn (Platform $p): bool => $plan->allowsPlatform($p->value))
            );
        }

        $paidPlanSlugs = $plans->filter(static fn (Plan $p): bool => $fulfillment->requiresOnlinePayment($p))
            ->pluck('slug')
            ->values()
            ->all();

        $currencyDisplay = app(CurrencyDisplayService::class);

        $freePlanSlug = $plans->where('is_free', true)->sortBy('sort_order')->first()?->slug;

        $anyYearlyOffers = $plans->contains(static function (Plan $p): bool {
            if ($p->is_free || $p->is_lifetime) {
                return false;
            }

            return $p->yearly_price_cents !== null && (int) $p->yearly_price_cents > 0;
        });
        $currentPlanBillingInterval = 'monthly';
        if ($anyYearlyOffers && $subscription?->billing_interval === 'yearly') {
            $currentPlanBillingInterval = 'yearly';
        }

        return view('plans', [
            'currentSubscription'           => $subscription,
            'plans'                         => $plans,
            'paynowCheckoutAvailable'       => $gatewayCfg->hostedCheckoutAvailable(),
            'checkoutGateway'               => $gatewayCfg->activeCheckoutGateway(),
            'checkoutRequiresGatewayChoice' => $gatewayCfg->checkoutRequiresGatewayChoice(),
            'availableCheckoutGateways'     => $gatewayCfg->availableCheckoutGateways(),
            'defaultCheckoutGateway'        => $gatewayCfg->defaultCheckoutGatewayForUi(),
            'paidPlanSlugs'                 => $paidPlanSlugs,
            'currencyDisplay'               => $currencyDisplay,
            'subscriptionAccess'            => app(SubscriptionAccessService::class),
            'freePlanSlug'                  => $freePlanSlug,
            'anyYearlyOffers'               => $anyYearlyOffers,
            'currentPlanBillingInterval'    => $currentPlanBillingInterval,
            'planSupportedPlatforms'        => $planSupportedPlatforms,
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
        $membership = app(WorkspaceAccessService::class)->activeMembership($user);
        $workspace = $membership?->workspace;
        $members = $workspace?->memberships()->with('user:id,name,email')->orderBy('id')->get() ?? collect();
        $pendingInvites = $workspace?->invites()->where('status', 'pending')->where('expires_at', '>', now())->orderByDesc('id')->get() ?? collect();

        return view('settings', [
            'workspaceName'        => $workspace?->name ?? $user->workspace_name ?? 'Personal brand',
            'defaultPostingTime'   => $user->default_posting_time ?? '09:00',
            'marketingEmailOptIn'  => (bool) ($user->marketing_email_opt_in ?? false),
            'workspaceRole'        => $membership?->role ?? 'member',
            'workspaceMembers'     => $members,
            'workspaceInvites'     => $pendingInvites,
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
            'billing_interval'     => ($newPlan->is_lifetime || $newPlan->is_free) ? null : 'monthly',
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
                        $inApp = app(InAppNotificationService::class);
                        $inApp->notifySuperAdminsNewSubscription($user, $newPlan);
                        $inApp->emailSuperAdminsPaidSubscription($user, $newPlan, false);
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
