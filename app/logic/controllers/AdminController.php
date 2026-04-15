<?php

namespace App\Controllers;

use App\Models\BillingCurrencySetting;
use App\Models\AuditTrail;
use App\Models\IpBlock;
use App\Models\CronTask;
use App\Models\CronTaskRun;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\PlanChange;
use App\Models\PostPlatform;
use App\Models\Subscription;
use App\Models\SiteSetting;
use App\Models\Timezone;
use App\Models\SocialAccount;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\Faq;
use App\Models\Testimonial;
use App\Models\User;
use App\Jobs\PublishPostCommentJob;
use App\Jobs\PublishPostToPlatformJob;
use App\Jobs\QueueTemplatedEmailForUserJob;
use App\Services\Admin\AdminAnalyticsService;
use App\Services\Admin\MigrationService;
use App\Services\Admin\PlanAdminValidationService;
use App\Services\Admin\PaymentTransactionListService;
use App\Services\Admin\SubscriptionInsightsService;
use App\Services\Ai\PlatformAiQuotaService;
use App\Services\Subscription\PlanSubscriberReconciliationService;
use App\Services\Billing\CurrencyDisplayService;
use App\Services\Cron\CronSecretResolver;
use App\Services\Cron\CronService;
use App\Services\Billing\PaymentGatewayConfigService;
use App\Services\Auth\SocialLoginAvailability;
use App\Services\Audit\AuditTrailService;
use App\Services\Platform\Platform;
use App\Services\Landing\LandingFeaturesDeepService;
use App\Services\Notifications\InAppNotificationService;
use App\Services\Seo\PublicSeoFilesService;
use App\Services\Cache\PublicCatalogCache;
use App\Services\Tools\ToolAccessService;
use App\Utils\FileUploadUtil;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    // ── Users ───────────────────────────────────────────

    public function users(Request $request): View
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }
        if ($status = $request->input('status')) {
            if ($status === 'trialing') {
                $query->whereHas('subscription', function ($q): void {
                    $q->where('status', 'trialing');
                });
            } else {
                $query->where('status', $status);
            }
        }

        $users = $query->with('subscription.planModel')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->appends($request->only(['search', 'role', 'status']));

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_free', 'is_lifetime']);

        $trialingUsersCount = Subscription::query()
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->distinct('user_id')
            ->count('user_id');

        return view('admin.users', compact('users', 'plans', 'trialingUsersCount'));
    }

    public function updateUserRole(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:user,support,super_admin',
        ]);

        $user = User::findOrFail($id);

        if ($user->id === Auth::id() && $validated['role'] !== 'super_admin') {
            return back()->with('error', 'You cannot demote your own account.');
        }

        $user->update(['role' => $validated['role']]);

        return back()->with('success', "Role updated to {$validated['role']} for {$user->name}.");
    }

    public function updateUserStatus(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:active,suspended,banned',
        ]);

        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot change your own status.');
        }

        $user->update(['status' => $validated['status']]);

        return back()->with('success', "Status updated to {$validated['status']} for {$user->name}.");
    }

    public function loginAsUser(Request $request, int $id): RedirectResponse
    {
        $target = User::findOrFail($id);
        $admin = Auth::user();

        if ($target->id === $admin->id) {
            return back()->with('error', 'You are already logged in as this account.');
        }
        if ($target->status !== 'active') {
            return back()->with('error', 'Only active users can be impersonated.');
        }

        Auth::login($target);
        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $admin->id);

        return redirect()->route('dashboard')->with('success', "You are now logged in as {$target->name}.");
    }

    public function stopLoginAsUser(Request $request): RedirectResponse
    {
        $impersonatorId = (int) $request->session()->get('impersonator_id', 0);
        if ($impersonatorId <= 0) {
            return redirect()->route('dashboard');
        }

        $admin = User::query()
            ->where('id', $impersonatorId)
            ->where('role', 'super_admin')
            ->where('status', 'active')
            ->first();

        if ($admin === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Impersonation session expired. Please sign in again.');
        }

        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->forget('impersonator_id');

        return redirect()->route('admin.users')->with('success', 'Returned to your admin account.');
    }

    public function updateUserPlan(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|integer|exists:plans,id',
            'action' => 'required|string|in:change,gift,trial',
            'trial_days' => 'nullable|integer|min:1|max:3650',
            'billing_interval' => 'nullable|string|in:monthly,yearly',
        ]);

        $targetUser = User::query()->with('subscription.planModel')->findOrFail($id);
        $newPlan = Plan::query()->where('is_active', true)->findOrFail((int) $validated['plan_id']);
        $action = (string) $validated['action'];
        $trialDays = isset($validated['trial_days']) ? (int) $validated['trial_days'] : null;

        $oldSub = $targetUser->subscription;
        $oldPlanId = $oldSub?->plan_id;
        $oldPlan = $oldPlanId ? Plan::find($oldPlanId) : null;

        if ($oldPlanId === $newPlan->id) {
            return back()->with('error', "{$targetUser->name} is already on {$newPlan->name}.");
        }

        if ($newPlan->is_lifetime && $newPlan->hasReachedLifetimeCap()) {
            return back()->with('error', "Cannot apply {$newPlan->name}: lifetime cap reached.");
        }

        if ($action !== 'trial' && ! $newPlan->is_free && ! $newPlan->is_lifetime) {
            $pickYearly = (($validated['billing_interval'] ?? 'monthly') === 'yearly');
            if ($pickYearly && ($newPlan->yearly_price_cents === null || (int) $newPlan->yearly_price_cents < 1)) {
                return back()->with('error', 'Yearly billing is not available for this plan.');
            }
        }

        $status = 'active';
        $periodEnd = null;
        $trialEndsAt = null;
        $billingIntervalForSub = null;

        if ($action === 'trial') {
            $days = max(1, (int) ($trialDays ?? 0));
            $status = 'trialing';
            $periodEnd = now()->addDays($days)->endOfDay();
            $trialEndsAt = $periodEnd;
        } elseif ($newPlan->is_lifetime || $newPlan->is_free) {
            $periodEnd = null;
            $billingIntervalForSub = null;
        } else {
            $interval = (($validated['billing_interval'] ?? 'monthly') === 'yearly') ? 'yearly' : 'monthly';
            $billingIntervalForSub = $interval;
            $periodEnd = $interval === 'yearly' ? now()->addYear() : now()->addMonth();
        }

        DB::transaction(function () use ($targetUser, $newPlan, $oldPlanId, $oldPlan, $action, $trialDays, $status, $periodEnd, $trialEndsAt, $billingIntervalForSub): void {
            $gateway = $action === 'gift' ? 'admin_gift' : 'admin_manual';
            $eventId = 'admin:' . Auth::id() . ':' . now()->format('YmdHis');

            $subscription = Subscription::updateOrCreate(
                ['user_id' => $targetUser->id],
                [
                    'plan_id' => $newPlan->id,
                    'plan' => $newPlan->slug,
                    'billing_interval' => $billingIntervalForSub,
                    'gateway' => $gateway,
                    'gateway_subscription_id' => $eventId,
                    'status' => $status,
                    'current_period_start' => now(),
                    'current_period_end' => $periodEnd,
                    'trial_ends_at' => $trialEndsAt,
                ]
            );

            app(PlatformAiQuotaService::class)->applyPlanBudgetToSubscription($subscription, $newPlan);

            if ($newPlan->is_lifetime) {
                $newPlan->increment('lifetime_current_count');
            }
            if ($oldPlan?->is_lifetime) {
                $oldPlan->decrement('lifetime_current_count');
            }

            PlanChange::create([
                'user_id' => $targetUser->id,
                'from_plan_id' => $oldPlanId,
                'to_plan_id' => $newPlan->id,
                'change_type' => $this->planChangeTypeFor($oldPlan, $newPlan),
                'gateway' => $gateway,
                'gateway_event_id' => $eventId,
            ]);

            DB::afterCommit(function () use ($targetUser, $newPlan, $oldPlan, $action, $trialDays): void {
                if ($action === 'gift') {
                    QueueTemplatedEmailForUserJob::dispatch($targetUser->id, 'subscription.gifted', [
                        'planName' => $newPlan->name,
                        'previousPlanName' => $oldPlan?->name ?? '',
                    ]);
                    app(InAppNotificationService::class)->notifyUserPlanGiftedByAdmin($targetUser, $newPlan);
                    return;
                }

                if ($action === 'trial') {
                    QueueTemplatedEmailForUserJob::dispatch($targetUser->id, 'subscription.updated', [
                        'planName' => $newPlan->name,
                        'previousPlanName' => $oldPlan?->name ?? '',
                        'trialStarted' => true,
                        'trialDays' => (int) ($trialDays ?? 0),
                    ]);
                    app(InAppNotificationService::class)->notifyUserPlanChangedByAdmin($targetUser, $newPlan);
                    return;
                }

                QueueTemplatedEmailForUserJob::dispatch($targetUser->id, 'subscription.admin_changed', [
                    'planName' => $newPlan->name,
                    'previousPlanName' => $oldPlan?->name ?? '',
                ]);
                app(InAppNotificationService::class)->notifyUserPlanChangedByAdmin($targetUser, $newPlan);
            });
        });

        $actionLabel = match ($action) {
            'gift' => 'gifted',
            'trial' => 'set to trial',
            default => 'changed',
        };

        return back()->with('success', "Plan {$actionLabel} to {$newPlan->name} for {$targetUser->name}.");
    }

    private function planChangeTypeFor(?Plan $oldPlan, Plan $newPlan): string
    {
        if ($oldPlan === null) {
            return 'upgrade';
        }

        if (! $oldPlan->is_free && $newPlan->is_free) {
            return 'downgrade';
        }

        return 'upgrade';
    }

    // ── Plans ───────────────────────────────────────────

    public function plans(): View
    {
        $plans = Plan::orderBy('sort_order')->get();
        $enabledPlatforms = SiteSetting::getJson('enabled_platforms', []);
        $toolAccess = app(ToolAccessService::class);
        $toolCatalog = $toolAccess->catalog();
        $enabledToolSlugs = $toolAccess->globallyEnabledToolSlugs();
        $pricingBaseCurrency = app(CurrencyDisplayService::class)->baseCurrency();

        return view('admin.plans', compact(
            'plans',
            'enabledPlatforms',
            'pricingBaseCurrency',
            'toolCatalog',
            'enabledToolSlugs'
        ));
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug'                          => 'required|string|max:50|unique:plans,slug',
            'name'                          => 'required|string|max:100',
            'description'                   => 'nullable|string',
            'monthly_price_cents'           => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(fn () => $request->boolean('is_lifetime') && ! $request->boolean('is_free')),
            ],
            'yearly_price_cents'            => 'nullable|integer|min:0',
            'max_social_profiles'           => 'nullable|integer|min:1',
            'max_scheduled_posts_per_month' => 'nullable|integer|min:1',
            'media_storage_limit_mb'        => 'required|integer|min:100|max:524288',
            'features'                      => 'nullable|string',
            'allowed_platforms'             => 'nullable|array',
            'allowed_platforms.*'           => 'string',
            'allowed_tools'                 => 'nullable|array',
            'allowed_tools.*'               => 'string',
            'tools_present'                 => 'nullable|boolean',
            'is_active'                     => 'boolean',
            'is_most_popular'               => 'boolean',
            'includes_replies'              => 'boolean',
            'includes_workflows'            => 'boolean',
            'includes_webhooks'             => 'boolean',
            'includes_workspaces'           => 'boolean',
            'max_workspace_members'         => 'nullable|integer|min:1',
            'max_accounts_per_platform'     => 'nullable|integer|min:1',
            'is_free'                       => 'boolean',
            'is_lifetime'                   => 'boolean',
            'lifetime_max_subscribers'      => 'nullable|integer|min:1',
            'sort_order'                    => 'integer|min:0',
            'has_free_trial'                   => 'boolean',
            'free_trial_days'                  => ['nullable', 'integer', 'min:1', 'max:366', Rule::requiredIf(fn () => $request->boolean('has_free_trial'))],
            'platform_ai_tokens_per_period'    => 'required|integer|min:0|max:999999999999',
        ]);

        $validated['features'] = $this->parseFeaturesList($validated['features'] ?? null);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_most_popular'] = $request->boolean('is_most_popular');
        $validated['includes_replies'] = $request->boolean('includes_replies');
        $validated['includes_workflows'] = $request->boolean('includes_workflows');
        $validated['includes_webhooks'] = $request->boolean('includes_webhooks');
        $validated['includes_workspaces'] = $request->boolean('includes_workspaces');
        $validated['is_free'] = $request->boolean('is_free');
        $validated['is_lifetime'] = $request->boolean('is_lifetime');
        $validated['has_free_trial'] = $request->boolean('has_free_trial');
        $toolCatalogSlugs = array_keys(app(ToolAccessService::class)->catalog());
        if ($request->boolean('tools_present')) {
            $validated['allowed_tools'] = array_values(array_filter(
                $validated['allowed_tools'] ?? [],
                static fn (string $slug): bool => in_array($slug, $toolCatalogSlugs, true),
            ));
        } else {
            unset($validated['allowed_tools']);
        }
        if (! $validated['has_free_trial']) {
            $validated['free_trial_days'] = null;
        }

        app(PlanAdminValidationService::class)->assertBusinessRules($request);
        if ($validated['is_free']) {
            $validated['has_free_trial'] = false;
            $validated['free_trial_days'] = null;
            $validated['is_most_popular'] = false;
        }

        $validated = $this->normalizedPlanPricingFromRequest($validated);

        $validated['platform_ai_tokens_per_period'] = (int) $validated['platform_ai_tokens_per_period'];
        $validated['media_storage_limit_mb'] = (int) $validated['media_storage_limit_mb'];

        if ($validated['is_most_popular']) {
            Plan::query()->update(['is_most_popular' => false]);
        }

        Plan::create($validated);
        PublicCatalogCache::forgetPlans();

        return back()->with('success', "Plan \"{$validated['name']}\" created.");
    }

    public function updatePlan(Request $request, int $id): RedirectResponse
    {
        $plan = Plan::findOrFail($id);
        $previousPlatformAiBudget = (int) $plan->platform_ai_tokens_per_period;

        $validated = $request->validate([
            'slug'                          => "required|string|max:50|unique:plans,slug,{$id}",
            'name'                          => 'required|string|max:100',
            'description'                   => 'nullable|string',
            'monthly_price_cents'           => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(fn () => $request->boolean('is_lifetime') && ! $request->boolean('is_free')),
            ],
            'yearly_price_cents'            => 'nullable|integer|min:0',
            'max_social_profiles'           => 'nullable|integer|min:1',
            'max_scheduled_posts_per_month' => 'nullable|integer|min:1',
            'media_storage_limit_mb'        => 'required|integer|min:100|max:524288',
            'features'                      => 'nullable|string',
            'allowed_platforms'             => 'nullable|array',
            'allowed_platforms.*'           => 'string',
            'allowed_tools'                 => 'nullable|array',
            'allowed_tools.*'               => 'string',
            'tools_present'                 => 'nullable|boolean',
            'is_active'                     => 'boolean',
            'is_most_popular'               => 'boolean',
            'includes_replies'              => 'boolean',
            'includes_workflows'            => 'boolean',
            'includes_webhooks'             => 'boolean',
            'includes_workspaces'           => 'boolean',
            'max_workspace_members'         => 'nullable|integer|min:1',
            'max_accounts_per_platform'     => 'nullable|integer|min:1',
            'is_free'                       => 'boolean',
            'is_lifetime'                   => 'boolean',
            'lifetime_max_subscribers'      => 'nullable|integer|min:1',
            'sort_order'                    => 'integer|min:0',
            'has_free_trial'                   => 'boolean',
            'free_trial_days'                  => ['nullable', 'integer', 'min:1', 'max:366', Rule::requiredIf(fn () => $request->boolean('has_free_trial'))],
            'platform_ai_tokens_per_period'    => 'required|integer|min:0|max:999999999999',
        ]);

        $validated['features'] = $this->parseFeaturesList($validated['features'] ?? null);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_most_popular'] = $request->boolean('is_most_popular');
        $validated['includes_replies'] = $request->boolean('includes_replies');
        $validated['includes_workflows'] = $request->boolean('includes_workflows');
        $validated['includes_webhooks'] = $request->boolean('includes_webhooks');
        $validated['includes_workspaces'] = $request->boolean('includes_workspaces');
        $validated['is_free'] = $request->boolean('is_free');
        $validated['is_lifetime'] = $request->boolean('is_lifetime');
        $validated['has_free_trial'] = $request->boolean('has_free_trial');
        $toolCatalogSlugs = array_keys(app(ToolAccessService::class)->catalog());
        if ($request->boolean('tools_present')) {
            $validated['allowed_tools'] = array_values(array_filter(
                $validated['allowed_tools'] ?? [],
                static fn (string $slug): bool => in_array($slug, $toolCatalogSlugs, true),
            ));
        }
        if (! $validated['has_free_trial']) {
            $validated['free_trial_days'] = null;
        }

        app(PlanAdminValidationService::class)->assertBusinessRules($request, $plan->id);
        if ($validated['is_free']) {
            $validated['has_free_trial'] = false;
            $validated['free_trial_days'] = null;
            $validated['is_most_popular'] = false;
        }

        $validated = $this->normalizedPlanPricingFromRequest($validated);

        if (! $request->has('allowed_platforms')) {
            $validated['allowed_platforms'] = null;
        }
        if ($request->boolean('tools_present') && ! $request->has('allowed_tools')) {
            $validated['allowed_tools'] = [];
        } elseif (! $request->boolean('tools_present') && ! $request->has('allowed_tools')) {
            $validated['allowed_tools'] = null;
        }

        $validated['platform_ai_tokens_per_period'] = (int) $validated['platform_ai_tokens_per_period'];
        $validated['media_storage_limit_mb'] = (int) $validated['media_storage_limit_mb'];

        DB::transaction(function () use ($plan, $validated, $previousPlatformAiBudget): void {
            if ($validated['is_most_popular']) {
                Plan::query()->where('id', '<>', $plan->id)->update(['is_most_popular' => false]);
            }

            $plan->update($validated);
            $plan->refresh();

            app(PlanSubscriberReconciliationService::class)->reconcileSubscribersForPlan($plan);

            if ($previousPlatformAiBudget !== (int) $plan->platform_ai_tokens_per_period) {
                $subscriberStatuses = PlanSubscriberReconciliationService::SUBSCRIBER_STATUSES;
                $affectedUserIds = Subscription::query()
                    ->where('plan_id', $plan->id)
                    ->whereIn('status', $subscriberStatuses)
                    ->pluck('user_id')
                    ->unique()
                    ->values();
                Subscription::query()
                    ->where('plan_id', $plan->id)
                    ->whereIn('status', $subscriberStatuses)
                    ->update(['platform_ai_tokens_remaining' => (int) $plan->platform_ai_tokens_per_period]);
                $quota = app(PlatformAiQuotaService::class);
                foreach ($affectedUserIds as $uid) {
                    $quota->invalidateLayoutSummaryCache((int) $uid);
                }
            }
        });

        PublicCatalogCache::forgetPlans();

        return back()->with('success', "Plan \"{$plan->name}\" updated.");
    }

    public function destroyPlan(int $id): RedirectResponse
    {
        $plan = Plan::findOrFail($id);

        if ($plan->subscriptions()->where('status', 'active')->exists()) {
            return back()->with('error', "Cannot delete \"{$plan->name}\" — it has active subscribers.");
        }

        $plan->delete();
        PublicCatalogCache::forgetPlans();

        return back()->with('success', "Plan \"{$plan->name}\" deleted.");
    }

    private function parseFeaturesList(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') return null;
        return array_values(array_filter(array_map('trim', explode("\n", $raw))));
    }

    /**
     * Lifetime plans use a single amount in {@see Plan::$monthly_price_cents}; yearly is always cleared.
     * Free plans clear both prices.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizedPlanPricingFromRequest(array $validated): array
    {
        if (! empty($validated['is_free'])) {
            $validated['monthly_price_cents'] = null;
            $validated['yearly_price_cents'] = null;

            return $validated;
        }

        if (! empty($validated['is_lifetime'])) {
            $validated['yearly_price_cents'] = null;
        }

        return $validated;
    }

    /**
     * @return array<string, array{label: string, category: string}>
     */
    private function toolCatalog(): array
    {
        return app(ToolAccessService::class)->catalog();
    }

    // ── Platforms ────────────────────────────────────────

    public function platforms(): View
    {
        $allPlatforms = Platform::cases();
        $enabledSlugs = SiteSetting::getJson('enabled_platforms', []);
        $plans = Plan::orderBy('sort_order')->get(['id', 'name', 'allowed_platforms']);

        return view('admin.platforms', compact('allPlatforms', 'enabledSlugs', 'plans'));
    }

    public function updatePlatforms(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => 'nullable|array',
            'enabled.*' => 'string',
        ]);

        $enabled = $validated['enabled'] ?? [];
        SiteSetting::setJson('enabled_platforms', $enabled);

        return back()->with('success', 'Platform settings saved.');
    }

    public function updatePlanTools(Request $request): RedirectResponse
    {
        $catalog = $this->toolCatalog();
        $allowedToolSlugs = array_keys($catalog);

        $validated = $request->validate([
            'enabled_tools' => 'nullable|array',
            'enabled_tools.*' => 'string',
        ]);

        $enabled = array_values(array_unique(array_filter(
            $validated['enabled_tools'] ?? [],
            static fn (string $slug): bool => in_array($slug, $allowedToolSlugs, true),
        )));

        SiteSetting::setJson('enabled_download_tools', $enabled);

        return back()->with('success', 'Tool availability updated.');
    }

    // ── Testimonials ────────────────────────────────────

    public function testimonials(): View
    {
        $testimonials = Testimonial::ordered()->get();
        return view('admin.testimonials', compact('testimonials'));
    }

    public function storeTestimonial(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'author_name'   => 'required|string|max:150',
            'author_title'  => 'nullable|string|max:200',
            'author_avatar' => 'nullable|url|max:500',
            'body'          => 'required|string',
            'rating'        => 'required|integer|min:1|max:5',
            'is_active'     => 'boolean',
            'sort_order'    => 'integer|min:0',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        Testimonial::create($validated);
        PublicCatalogCache::forgetTestimonials();

        return back()->with('success', 'Testimonial added.');
    }

    public function updateTestimonial(Request $request, int $id): RedirectResponse
    {
        $testimonial = Testimonial::findOrFail($id);

        $validated = $request->validate([
            'author_name'   => 'required|string|max:150',
            'author_title'  => 'nullable|string|max:200',
            'author_avatar' => 'nullable|url|max:500',
            'body'          => 'required|string',
            'rating'        => 'required|integer|min:1|max:5',
            'is_active'     => 'boolean',
            'sort_order'    => 'integer|min:0',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $testimonial->update($validated);
        PublicCatalogCache::forgetTestimonials();

        return back()->with('success', 'Testimonial updated.');
    }

    public function destroyTestimonial(int $id): RedirectResponse
    {
        Testimonial::findOrFail($id)->delete();
        PublicCatalogCache::forgetTestimonials();

        return back()->with('success', 'Testimonial deleted.');
    }

    // ── FAQs ────────────────────────────────────────────

    public function faqs(): View
    {
        $faqs = Faq::ordered()->get();

        return view('admin.faqs', compact('faqs'));
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question'   => 'required|string|max:500',
            'answer'     => 'required|string',
            'is_active'  => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        Faq::create($validated);
        PublicCatalogCache::forgetFaqs();

        return back()->with('success', 'FAQ added.');
    }

    public function updateFaq(Request $request, int $id): RedirectResponse
    {
        $faq = Faq::findOrFail($id);

        $validated = $request->validate([
            'question'   => 'required|string|max:500',
            'answer'     => 'required|string',
            'is_active'  => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $faq->update($validated);
        PublicCatalogCache::forgetFaqs();

        return back()->with('success', 'FAQ updated.');
    }

    public function destroyFaq(int $id): RedirectResponse
    {
        Faq::findOrFail($id)->delete();
        PublicCatalogCache::forgetFaqs();

        return back()->with('success', 'FAQ deleted.');
    }

    // ── Support Tickets ─────────────────────────────────

    public function tickets(Request $request): View
    {
        $query = SupportTicket::with(['user:id,name,email', 'replies.user:id,name']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $tickets = $query->orderByDesc('created_at')->paginate(25)->appends($request->only('status'));

        return view('admin.tickets', compact('tickets'));
    }

    public function replyTicket(Request $request, int $id): RedirectResponse
    {
        $ticket = SupportTicket::findOrFail($id);

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'message'   => $validated['message'],
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        $responder = Auth::user();
        if ($responder !== null && $ticket->user_id !== $responder->id) {
            try {
                app(InAppNotificationService::class)->notifyUserSupportTicketReplied($ticket, $responder);
                QueueTemplatedEmailForUserJob::dispatch($ticket->user_id, 'support.ticket_replied', [
                    'ticketId'      => (string) $ticket->id,
                    'ticketSubject' => $ticket->subject,
                    'ticketUrl'     => url(route('support-tickets.show', $ticket->id, false)),
                    'responderName' => $responder->name,
                ]);
            } catch (\Throwable) {
            }
        }

        return back()->with('success', 'Reply sent.');
    }

    public function updateTicketStatus(Request $request, int $id): RedirectResponse
    {
        $ticket = SupportTicket::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $updates = ['status' => $validated['status']];
        if (in_array($validated['status'], ['resolved', 'closed'])) {
            $updates['closed_at'] = now();
        }

        $ticket->update($updates);

        return back()->with('success', 'Ticket status updated.');
    }

    // ── Site Settings ───────────────────────────────────

    public function settings(): View
    {
        $settings = [
            'app_name'                   => SiteSetting::get('app_name', config('app.name')),
            'app_tagline'                => SiteSetting::get('app_tagline', ''),
            'hero_eyebrow'               => SiteSetting::get('hero_eyebrow', ''),
            'hero_heading'               => SiteSetting::get('hero_heading', ''),
            'hero_subheading'            => SiteSetting::get('hero_subheading', ''),
            'seo_meta_title'             => SiteSetting::get('seo_meta_title', ''),
            'seo_meta_description'       => SiteSetting::get('seo_meta_description', ''),
            'seo_social_description'     => SiteSetting::get('seo_social_description', ''),
            'seo_keywords'               => SiteSetting::get('seo_keywords', ''),
            'seo_twitter_site'           => SiteSetting::get('seo_twitter_site', ''),
            'seo_image_path'             => SiteSetting::get('seo_image_path', ''),
            'seo_favicon_path'           => SiteSetting::get('seo_favicon_path', ''),
            'seo_local_business_name'    => SiteSetting::get('seo_local_business_name', ''),
            'seo_local_phone'            => SiteSetting::get('seo_local_phone', ''),
            'seo_local_email'            => SiteSetting::get('seo_local_email', ''),
            'seo_local_address'          => SiteSetting::get('seo_local_address', ''),
            'seo_local_city'             => SiteSetting::get('seo_local_city', ''),
            'seo_local_region'           => SiteSetting::get('seo_local_region', ''),
            'seo_local_postal_code'      => SiteSetting::get('seo_local_postal_code', ''),
            'seo_local_country_code'     => SiteSetting::get('seo_local_country_code', ''),
            'registration_open'          => SiteSetting::get('registration_open', '1'),
            'show_floating_help'         => SiteSetting::get('show_floating_help', '1'),
            'affiliate_program_enabled'  => SiteSetting::get('affiliate_program_enabled', '0'),
            'affiliate_first_subscription_percent' => SiteSetting::get('affiliate_first_subscription_percent', '10.00'),
            'social_login_google'        => SiteSetting::get('social_login_google', '1'),
            'social_login_linkedin'      => SiteSetting::get('social_login_linkedin', '1'),
            'default_display_timezone'   => SiteSetting::get('default_display_timezone', 'UTC'),
            'under_construction'         => SiteSetting::get('under_construction', '0'),
        ];

        $timezonesForSelect = Schema::hasTable('timezones')
            ? Timezone::query()->orderBy('identifier')->get()
            : collect();

        $availability       = app(SocialLoginAvailability::class);
        $socialGoogleConfigured   = $availability->googleCredentialsConfigured();
        $socialLinkedinConfigured = $availability->linkedinCredentialsConfigured();

        $sitemapExists = is_file(public_path('sitemap.xml'));
        $robotsExists = is_file(public_path('robots.txt'));

        $landingFeaturesDeep = app(LandingFeaturesDeepService::class)->resolvedBlocks();
        $landingFeaturesVisualLabels = [
            LandingFeaturesDeepService::VISUAL_GLASS_CARD => 'Glass card (eyebrow + body)',
            LandingFeaturesDeepService::VISUAL_GLASS_MONO => 'Glass — single line',
            LandingFeaturesDeepService::VISUAL_ICONS       => 'Icon row (or platform icons if empty)',
            LandingFeaturesDeepService::VISUAL_IMAGE       => 'Photo / image',
            LandingFeaturesDeepService::VISUAL_GRID        => 'Decorative grid',
        ];

        return view('admin.settings', compact(
            'settings',
            'timezonesForSelect',
            'socialGoogleConfigured',
            'socialLinkedinConfigured',
            'sitemapExists',
            'robotsExists',
            'landingFeaturesDeep',
            'landingFeaturesVisualLabels'
        ));
    }

    public function updateLandingFeaturesDeep(Request $request, LandingFeaturesDeepService $service): RedirectResponse
    {
        $request->validate([
            'features'                 => 'required|array',
            'features.*.title'         => 'nullable|string|max:220',
            'features.*.body'          => 'nullable|string|max:2000',
            'features.*.cta_label'     => 'nullable|string|max:120',
            'features.*.cta_href'      => 'nullable|string|max:500',
            'features.*.visual'       => 'nullable|string|max:32',
            'features.*.glass_eyebrow' => 'nullable|string|max:120',
            'features.*.glass_body'    => 'nullable|string|max:500',
            'features.*.glass_mono'    => 'nullable|string|max:500',
            'features.*.icon_classes'  => 'nullable|string|max:1000',
            'features.*.image'         => 'nullable|file|image|max:5120',
            'features.*.image_existing' => 'nullable|string|max:500',
        ]);

        $dir = public_path('assets/uploads/landing');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $defaults = $service->defaultBlocks();
        $prevAll  = SiteSetting::getJson('landing_features_deep', []);
        $out      = [];

        for ($i = 0; $i < 4; $i++) {
            $f    = $request->input("features.$i", []);
            $def  = $defaults[$i];
            $prev = is_array($prevAll[$i] ?? null) ? $prevAll[$i] : [];

            $visual = $f['visual'] ?? $def['visual'];
            if (! in_array($visual, LandingFeaturesDeepService::visualOptions(), true)) {
                $visual = $def['visual'];
            }

            $iconRaw = strip_tags((string) ($f['icon_classes'] ?? ''));
            $iconParts = array_filter(array_map('trim', explode(',', $iconRaw)));
            $iconSafe  = [];
            foreach ($iconParts as $p) {
                if ($p !== '' && preg_match('/^[\w\s\-]+$/', $p)) {
                    $iconSafe[] = preg_replace('/\s+/', ' ', $p);
                }
            }
            $iconClasses = Str::limit(implode(',', array_slice($iconSafe, 0, 24)), 1000);

            $block = [
                'reverse'       => $request->boolean("features.$i.reverse"),
                'title'         => Str::limit(strip_tags((string) ($f['title'] ?? '')), 220),
                'body'          => Str::limit(strip_tags((string) ($f['body'] ?? '')), 2000),
                'cta_label'     => Str::limit(strip_tags((string) ($f['cta_label'] ?? '')), 120),
                'cta_href'      => Str::limit(trim((string) ($f['cta_href'] ?? '')), 500),
                'visual'        => $visual,
                'glass_eyebrow' => Str::limit(strip_tags((string) ($f['glass_eyebrow'] ?? '')), 120),
                'glass_body'    => Str::limit(strip_tags((string) ($f['glass_body'] ?? '')), 500),
                'glass_mono'    => Str::limit(strip_tags((string) ($f['glass_mono'] ?? '')), 500),
                'icon_classes'  => $iconClasses,
                'image'         => '',
            ];

            if ($visual === LandingFeaturesDeepService::VISUAL_IMAGE) {
                if ($request->hasFile("features.$i.image")) {
                    if (! empty($prev['image'])) {
                        FileUploadUtil::delete($prev['image']);
                    }
                    $block['image'] = FileUploadUtil::store($request->file("features.$i.image"), 'landing');
                } else {
                    $existing = trim((string) ($f['image_existing'] ?? ''));
                    if ($existing !== '' && str_starts_with($existing, 'assets/uploads/landing/')) {
                        $block['image'] = $existing;
                    } elseif (! empty($prev['image'])) {
                        $block['image'] = $prev['image'];
                    }
                }
            } elseif (! empty($prev['image'])) {
                FileUploadUtil::delete($prev['image']);
            }

            $out[] = $block;
        }

        SiteSetting::setJson('landing_features_deep', $out);

        return $this->redirectToSettingsSection(
            (string) $request->input('return_section', 'landing'),
            'Landing features deep section saved.',
            'success'
        );
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'under_construction' => 'nullable|boolean',
            'seo_meta_title' => 'nullable|string|max:120',
            'seo_meta_description' => 'nullable|string|max:320',
            'seo_social_description' => 'nullable|string|max:320',
            'seo_keywords' => 'nullable|string|max:500',
            'seo_twitter_site' => 'nullable|string|max:50',
            'seo_local_business_name' => 'nullable|string|max:120',
            'seo_local_phone' => 'nullable|string|max:64',
            'seo_local_email' => 'nullable|email|max:255',
            'seo_local_address' => 'nullable|string|max:255',
            'seo_local_city' => 'nullable|string|max:120',
            'seo_local_region' => 'nullable|string|max:120',
            'seo_local_postal_code' => 'nullable|string|max:32',
            'seo_local_country_code' => 'nullable|string|size:2',
            'affiliate_program_enabled' => 'nullable|boolean',
            'affiliate_first_subscription_percent' => 'nullable|numeric|min:0|max:100',
            'seo_image' => 'nullable|file|image|max:5120',
            'seo_image_existing' => 'nullable|string|max:500',
            'seo_image_remove' => 'nullable|boolean',
            'seo_favicon' => 'nullable|file|mimes:ico,png,jpg,jpeg,webp,svg|max:2048',
            'seo_favicon_existing' => 'nullable|string|max:500',
            'seo_favicon_remove' => 'nullable|boolean',
        ]);

        $fields = ['app_name', 'app_tagline', 'hero_eyebrow', 'hero_heading', 'hero_subheading', 'registration_open', 'show_floating_help'];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                SiteSetting::set($field, $request->input($field, ''));
            }
        }

        SiteSetting::set('under_construction', $request->boolean('under_construction') ? '1' : '0');

        SiteSetting::set('seo_meta_title', Str::limit(trim(strip_tags((string) $request->input('seo_meta_title', ''))), 120));
        SiteSetting::set('seo_meta_description', Str::limit(trim(strip_tags((string) $request->input('seo_meta_description', ''))), 320));
        SiteSetting::set('seo_social_description', Str::limit(trim(strip_tags((string) $request->input('seo_social_description', ''))), 320));
        SiteSetting::set('seo_keywords', Str::limit(trim(strip_tags((string) $request->input('seo_keywords', ''))), 500));
        SiteSetting::set('seo_twitter_site', Str::limit(trim(strip_tags((string) $request->input('seo_twitter_site', ''))), 50));
        SiteSetting::set('seo_local_business_name', Str::limit(trim(strip_tags((string) $request->input('seo_local_business_name', ''))), 120));
        SiteSetting::set('seo_local_phone', Str::limit(trim(strip_tags((string) $request->input('seo_local_phone', ''))), 64));
        SiteSetting::set('seo_local_email', Str::limit(trim(strip_tags((string) $request->input('seo_local_email', ''))), 255));
        SiteSetting::set('seo_local_address', Str::limit(trim(strip_tags((string) $request->input('seo_local_address', ''))), 255));
        SiteSetting::set('seo_local_city', Str::limit(trim(strip_tags((string) $request->input('seo_local_city', ''))), 120));
        SiteSetting::set('seo_local_region', Str::limit(trim(strip_tags((string) $request->input('seo_local_region', ''))), 120));
        SiteSetting::set('seo_local_postal_code', Str::limit(trim(strip_tags((string) $request->input('seo_local_postal_code', ''))), 32));
        SiteSetting::set('seo_local_country_code', strtoupper(Str::limit(trim(strip_tags((string) $request->input('seo_local_country_code', ''))), 2)));
        SiteSetting::set('affiliate_program_enabled', $request->boolean('affiliate_program_enabled') ? '1' : '0');
        $affiliatePercent = max(0, min(100, (float) $request->input('affiliate_first_subscription_percent', 10)));
        SiteSetting::set('affiliate_first_subscription_percent', number_format($affiliatePercent, 2, '.', ''));

        $existingSeoImage = trim((string) SiteSetting::get('seo_image_path', ''));
        if ($request->boolean('seo_image_remove')) {
            if ($existingSeoImage !== '' && str_starts_with($existingSeoImage, 'assets/uploads/seo/')) {
                FileUploadUtil::delete($existingSeoImage);
            }
            SiteSetting::set('seo_image_path', '');
        } elseif ($request->hasFile('seo_image')) {
            if ($existingSeoImage !== '' && str_starts_with($existingSeoImage, 'assets/uploads/seo/')) {
                FileUploadUtil::delete($existingSeoImage);
            }
            SiteSetting::set('seo_image_path', FileUploadUtil::store($request->file('seo_image'), 'seo'));
        } else {
            $incomingExisting = trim((string) $request->input('seo_image_existing', ''));
            if ($incomingExisting !== '' && str_starts_with($incomingExisting, 'assets/uploads/seo/')) {
                SiteSetting::set('seo_image_path', $incomingExisting);
            }
        }

        $existingFavicon = trim((string) SiteSetting::get('seo_favicon_path', ''));
        if ($request->boolean('seo_favicon_remove')) {
            if ($existingFavicon !== '' && str_starts_with($existingFavicon, 'assets/uploads/seo/')) {
                FileUploadUtil::delete($existingFavicon);
            }
            SiteSetting::set('seo_favicon_path', '');
        } elseif ($request->hasFile('seo_favicon')) {
            if ($existingFavicon !== '' && str_starts_with($existingFavicon, 'assets/uploads/seo/')) {
                FileUploadUtil::delete($existingFavicon);
            }
            SiteSetting::set('seo_favicon_path', FileUploadUtil::store($request->file('seo_favicon'), 'seo'));
        } else {
            $incomingFavicon = trim((string) $request->input('seo_favicon_existing', ''));
            if ($incomingFavicon !== '' && str_starts_with($incomingFavicon, 'assets/uploads/seo/')) {
                SiteSetting::set('seo_favicon_path', $incomingFavicon);
            }
        }

        if (Schema::hasTable('timezones') && Timezone::query()->exists()) {
            $validated = $request->validate([
                'default_display_timezone' => ['required', 'string', 'max:128', Rule::exists('timezones', 'identifier')],
            ]);
            SiteSetting::set('default_display_timezone', $validated['default_display_timezone']);
        }

        $availability = app(SocialLoginAvailability::class);
        if ($availability->googleCredentialsConfigured() && $request->has('social_login_google')) {
            SiteSetting::set('social_login_google', $request->input('social_login_google', '0'));
        }
        if ($availability->linkedinCredentialsConfigured() && $request->has('social_login_linkedin')) {
            SiteSetting::set('social_login_linkedin', $request->input('social_login_linkedin', '0'));
        }

        Cache::forget('global:seo_defaults');

        return $this->redirectToSettingsSection(
            (string) $request->input('return_section', 'general'),
            'Settings saved.',
            'success'
        );
    }

    // ── Migrations ──────────────────────────────────────

    public function migrations(): View
    {
        $service = app(MigrationService::class);
        $allMigrations = collect($service->getAllMigrations());
        $pendingMigrationsCount = $allMigrations->where('ran', false)->count();

        $perPage = 25;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $migrations = new LengthAwarePaginator(
            $allMigrations->forPage($currentPage, $perPage)->values(),
            $allMigrations->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        return view('admin.migrations', compact('migrations', 'pendingMigrationsCount'));
    }

    public function runMigrations(Request $request): RedirectResponse
    {
        $service = app(MigrationService::class);

        try {
            $migration = $request->input('migration');

            if ($migration) {
                $service->runSingle($migration);
                return back()->with('success', "Migration \"{$migration}\" executed.");
            }

            $ran = $service->runAll();

            if (empty($ran)) {
                return back()->with('info', 'No pending migrations.');
            }

            return back()->with('success', count($ran) . ' migration(s) executed.');
        } catch (\Throwable $e) {
            Log::error('Admin migration run failed', ['message' => $e->getMessage(), 'class' => $e::class]);

            return back()->with('error', 'Migration failed. Check application logs for details.');
        }
    }

    public function rollbackMigrations(Request $request): RedirectResponse
    {
        $service = app(MigrationService::class);

        try {
            $migration = $request->input('migration');

            if ($migration) {
                $service->rollbackSingle($migration);
                return back()->with('success', "Migration \"{$migration}\" rolled back.");
            }

            $rolled = $service->rollbackBatch();

            if (empty($rolled)) {
                return back()->with('info', 'Nothing to roll back.');
            }

            return back()->with('success', count($rolled) . ' migration(s) rolled back.');
        } catch (\Throwable $e) {
            Log::error('Admin migration rollback failed', ['message' => $e->getMessage(), 'class' => $e::class]);

            return back()->with('error', 'Rollback failed. Check application logs for details.');
        }
    }

    // ── Payment gateways (extensible) ───────────────────

    public function paymentGateways(PaymentGatewayConfigService $cfg): View
    {
        $gateways = $cfg->all();
        $paynowKey = (string) ($gateways['paynow']['integration_key'] ?? '');
        $gateways['paynow']['integration_key_masked'] = $paynowKey !== ''
            ? '••••••••' . substr($paynowKey, -4)
            : '';

        $pesepayKey = (string) ($gateways['pesepay']['integration_key'] ?? '');
        $gateways['pesepay']['integration_key_masked'] = $pesepayKey !== ''
            ? '••••••••' . substr($pesepayKey, -4)
            : '';
        $pesepayEnc = (string) ($gateways['pesepay']['encryption_key'] ?? '');
        $gateways['pesepay']['encryption_key_masked'] = $pesepayEnc !== ''
            ? '••••••••' . substr($pesepayEnc, -4)
            : '';
        $stripeSecret = (string) ($gateways['stripe']['secret_key'] ?? '');
        $gateways['stripe']['secret_key_masked'] = $stripeSecret !== ''
            ? '••••••••' . substr($stripeSecret, -4)
            : '';
        $stripeWebhook = (string) ($gateways['stripe']['webhook_secret'] ?? '');
        $gateways['stripe']['webhook_secret_masked'] = $stripeWebhook !== ''
            ? '••••••••' . substr($stripeWebhook, -4)
            : '';
        $paypalSecret = (string) ($gateways['paypal']['client_secret'] ?? '');
        $gateways['paypal']['client_secret_masked'] = $paypalSecret !== ''
            ? '••••••••' . substr($paypalSecret, -4)
            : '';
        $activeCheckoutGateways = $cfg->availableCheckoutGateways();

        return view('admin.payment-gateways', compact('gateways', 'activeCheckoutGateways'));
    }

    public function updatePaymentGateways(Request $request, PaymentGatewayConfigService $cfg): RedirectResponse
    {
        $request->validate([
            'checkout_gateway'             => 'nullable|string|in:paynow,pesepay,stripe,paypal',
            'paynow_enabled'               => 'nullable|boolean',
            'paynow_integration_id'        => 'nullable|string|max:120',
            'paynow_integration_key'       => 'nullable|string|max:500',
            'paynow_checkout_currency'     => 'required|string|size:3',
            'pesepay_enabled'              => 'nullable|boolean',
            'pesepay_integration_key'      => 'nullable|string|max:500',
            'pesepay_encryption_key'       => 'nullable|string|max:64',
            'pricing_base_currency'        => 'required|string|size:3',
            'pricing_default_currency'     => 'required|string|size:3',
            'exchange_rate_codes'          => 'nullable|array',
            'exchange_rate_codes.*'        => 'nullable|string|max:3',
            'exchange_rate_values'         => 'nullable|array',
            'exchange_rate_values.*'       => 'nullable|numeric',
            'stripe_enabled'               => 'nullable|boolean',
            'stripe_publishable_key'       => 'nullable|string|max:300',
            'stripe_secret_key'            => 'nullable|string|max:300',
            'stripe_webhook_secret'        => 'nullable|string|max:300',
            'paypal_enabled'               => 'nullable|boolean',
            'paypal_client_id'             => 'nullable|string|max:300',
            'paypal_client_secret'         => 'nullable|string|max:300',
            'paypal_webhook_id'            => 'nullable|string|max:200',
            'paypal_mode'                  => 'nullable|string|in:sandbox,live',
            'paypal_checkout_currency'     => 'nullable|string|size:3',
        ]);

        $current = $cfg->all();

        $baseCur = strtoupper(trim((string) $request->input('pricing_base_currency', 'USD')));
        $defCur  = strtoupper(trim((string) $request->input('pricing_default_currency', 'USD')));
        $paynowCur = strtoupper(trim((string) $request->input('paynow_checkout_currency', 'USD')));

        $rates = [];
        $codes = $request->input('exchange_rate_codes', []);
        $values = $request->input('exchange_rate_values', []);
        if (is_array($codes) && is_array($values)) {
            foreach ($codes as $i => $codeRaw) {
                $code = strtoupper(trim((string) $codeRaw));
                if (strlen($code) !== 3) {
                    continue;
                }
                $valRaw = $values[$i] ?? null;
                if (! is_numeric($valRaw)) {
                    continue;
                }
                $rates[$code] = max(1e-9, (float) $valRaw);
            }
        }
        $rates[$baseCur] = 1.0;

        $billingRow = BillingCurrencySetting::query()->first();
        $billingAttrs = [
            'base_currency'            => $baseCur,
            'default_display_currency' => $defCur,
            'paynow_checkout_currency' => strlen($paynowCur) === 3 ? $paynowCur : 'USD',
            'exchange_rates'           => $rates,
        ];
        if ($billingRow === null) {
            BillingCurrencySetting::query()->create($billingAttrs);
        } else {
            $billingRow->update($billingAttrs);
        }

        $cg = strtolower(trim((string) $request->input('checkout_gateway', '')));

        $current['paynow']['enabled'] = $request->boolean('paynow_enabled');
        $id = trim((string) $request->input('paynow_integration_id', ''));
        if ($id !== '') {
            $current['paynow']['integration_id'] = $id;
        }
        $key = $request->input('paynow_integration_key');
        if (is_string($key) && trim($key) !== '') {
            $current['paynow']['integration_key'] = trim($key);
        }

        $current['pesepay']['enabled'] = $request->boolean('pesepay_enabled');
        $pKey = $request->input('pesepay_integration_key');
        if (is_string($pKey) && trim($pKey) !== '') {
            $current['pesepay']['integration_key'] = trim($pKey);
        }
        $pEnc = $request->input('pesepay_encryption_key');
        if (is_string($pEnc) && trim($pEnc) !== '') {
            $current['pesepay']['encryption_key'] = trim($pEnc);
        }

        $current['stripe']['enabled'] = $request->boolean('stripe_enabled');
        foreach (['publishable_key', 'secret_key', 'webhook_secret'] as $f) {
            $v = $request->input('stripe_' . $f);
            if (is_string($v) && trim($v) !== '') {
                $current['stripe'][$f] = trim($v);
            }
        }

        $current['paypal']['enabled'] = $request->boolean('paypal_enabled');
        $paypalMode = strtolower(trim((string) $request->input('paypal_mode', 'sandbox')));
        $current['paypal']['mode'] = $paypalMode === 'live' ? 'live' : 'sandbox';
        $current['paypal']['webhook_id'] = trim((string) $request->input('paypal_webhook_id', ''));
        $ppc = strtoupper(trim((string) $request->input('paypal_checkout_currency', '')));
        $current['paypal']['checkout_currency'] = strlen($ppc) === 3 ? $ppc : '';
        foreach (['client_id', 'client_secret'] as $f) {
            $v = $request->input('paypal_' . $f);
            if (is_string($v) && trim($v) !== '') {
                $current['paypal'][$f] = trim($v);
            }
        }

        $activeGateways = $cfg->availableCheckoutGatewaysFromConfig($current);
        if ($activeGateways !== []) {
            if (! in_array($cg, $activeGateways, true)) {
                return back()
                    ->withErrors([
                        'checkout_gateway' => 'Select a preferred gateway from active and configured gateways.',
                    ])
                    ->withInput();
            }
            $current['checkout_gateway'] = $cg;
        } else {
            $current['checkout_gateway'] = 'paynow';
        }

        $cfg->save($current);

        return back()->with('success', 'Payment gateway settings saved.');
    }

    public function analytics(Request $request, AdminAnalyticsService $analytics): View
    {
        $report = $analytics->build($request);
        $plans = Plan::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);

        return view('admin.analytics', compact('report', 'plans'));
    }

    public function subscriptionsDashboard(SubscriptionInsightsService $insights): View
    {
        $stats = $insights->build();

        $recentSubs = Subscription::query()
            ->with(['user:id,name,email', 'planModel:id,name,slug'])
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get();

        $recentPayments = PaymentTransaction::query()
            ->with(['user:id,name,email', 'plan:id,name'])
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('admin.subscriptions', compact('stats', 'recentSubs', 'recentPayments'));
    }

    public function paymentTransactions(Request $request, PaymentTransactionListService $list): View
    {
        $transactions = $list->paginate($request);

        return view('admin.payment-transactions', compact('transactions'));
    }

    public function ipBlocks(): View
    {
        $blocks = IpBlock::query()
            ->with('creator:id,name,email')
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.ip-blocks', compact('blocks'));
    }

    public function storeIpBlock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => 'required|ip|max:64',
            'reason' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $block = IpBlock::query()->updateOrCreate(
            ['ip_address' => (string) $validated['ip_address']],
            [
                'reason' => isset($validated['reason']) ? (string) $validated['reason'] : null,
                'expires_at' => isset($validated['expires_at']) ? (string) $validated['expires_at'] : null,
                'is_active' => true,
                'created_by' => Auth::id(),
            ]
        );

        app(AuditTrailService::class)->record(
            category: 'security',
            event: 'ip_block_created',
            userId: Auth::id(),
            request: $request,
            statusCode: 200,
            metadata: [
                'ip_address' => $block->ip_address,
                'reason' => $block->reason,
                'expires_at' => optional($block->expires_at)->toDateTimeString(),
            ],
        );

        return back()->with('success', "IP {$block->ip_address} has been blocked.");
    }

    public function destroyIpBlock(int $id): RedirectResponse
    {
        $row = IpBlock::query()->findOrFail($id);
        $ip = (string) $row->ip_address;
        app(AuditTrailService::class)->record(
            category: 'security',
            event: 'ip_block_removed',
            userId: Auth::id(),
            metadata: ['ip_address' => $ip],
            statusCode: 200,
        );
        $row->delete();

        return back()->with('success', "IP {$ip} has been unblocked.");
    }

    public function auditTrail(Request $request): View
    {
        $query = AuditTrail::query()
            ->with('user:id,name,email')
            ->orderByDesc('occurred_at');

        if ($category = trim((string) $request->input('category', ''))) {
            $query->where('category', $category);
        }
        if ($event = trim((string) $request->input('event', ''))) {
            $query->where('event', 'like', '%' . $event . '%');
        }
        if ($method = strtoupper(trim((string) $request->input('method', '')))) {
            $query->where('method', $method);
        }
        if ($statusCode = trim((string) $request->input('status_code', ''))) {
            $query->where('status_code', (int) $statusCode);
        }
        if ($userId = trim((string) $request->input('user_id', ''))) {
            $query->where('user_id', (int) $userId);
        }
        if ($path = trim((string) $request->input('path', ''))) {
            $query->where('path', 'like', '%' . $path . '%');
        }

        $events = $query->paginate(50)->appends($request->query());

        $categories = AuditTrail::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('admin.audit-trail', compact('events', 'categories'));
    }

    public function auditTrailExport(Request $request): StreamedResponse
    {
        $query = AuditTrail::query()
            ->with('user:id,name,email')
            ->orderByDesc('occurred_at');

        if ($category = trim((string) $request->input('category', ''))) {
            $query->where('category', $category);
        }
        if ($event = trim((string) $request->input('event', ''))) {
            $query->where('event', 'like', '%' . $event . '%');
        }
        if ($method = strtoupper(trim((string) $request->input('method', '')))) {
            $query->where('method', $method);
        }
        if ($statusCode = trim((string) $request->input('status_code', ''))) {
            $query->where('status_code', (int) $statusCode);
        }
        if ($userId = trim((string) $request->input('user_id', ''))) {
            $query->where('user_id', (int) $userId);
        }
        if ($path = trim((string) $request->input('path', ''))) {
            $query->where('path', 'like', '%' . $path . '%');
        }

        $fileName = 'audit-trail-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, [
                'occurred_at',
                'category',
                'event',
                'severity',
                'user_id',
                'user_email',
                'method',
                'path',
                'route_name',
                'status_code',
                'ip_address',
                'metadata_json',
            ]);

            $query->chunkById(500, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    $severity = 'info';
                    $status = (int) ($row->status_code ?? 0);
                    if ($status >= 500) {
                        $severity = 'critical';
                    } elseif ($status >= 400) {
                        $severity = 'warning';
                    } elseif (str_starts_with((string) $row->event, 'login_failed')) {
                        $severity = 'warning';
                    } elseif ((string) $row->category === 'auth') {
                        $severity = 'security';
                    }

                    fputcsv($out, [
                        optional($row->occurred_at)->format('Y-m-d H:i:s'),
                        (string) $row->category,
                        (string) $row->event,
                        $severity,
                        (string) ($row->user_id ?? ''),
                        (string) ($row->user->email ?? ''),
                        (string) ($row->method ?? ''),
                        (string) ($row->path ?? ''),
                        (string) ($row->route_name ?? ''),
                        (string) ($row->status_code ?? ''),
                        (string) ($row->ip_address ?? ''),
                        json_encode($row->metadata ?? [], JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });
            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Operations & Reliability ───────────────────────

    public function operations(): View
    {
        $failedPublishes = PostPlatform::with(['post:id,user_id,content', 'socialAccount:id,user_id,platform,display_name,token_expires_at,status'])
            ->where('status', 'failed')
            ->orderByDesc('updated_at')
            ->paginate(25, ['*'], 'publish_page');

        $failedComments = PostPlatform::with(['post:id,user_id,content', 'socialAccount:id,user_id,platform,display_name,token_expires_at,status'])
            ->where('comment_status', 'failed')
            ->orderByDesc('updated_at')
            ->paginate(25, ['*'], 'comment_page');

        $tokenHealth = [
            'expired' => SocialAccount::active()->whereNotNull('token_expires_at')->where('token_expires_at', '<=', now())->count(),
            'expiring_24h' => SocialAccount::active()->whereNotNull('token_expires_at')->whereBetween('token_expires_at', [now(), now()->addDay()])->count(),
            'expiring_7d' => SocialAccount::active()->whereNotNull('token_expires_at')->whereBetween('token_expires_at', [now()->addDay(), now()->addDays(7)])->count(),
            'no_expiry' => SocialAccount::active()->whereNull('token_expires_at')->count(),
        ];

        $allPlatforms = \App\Services\Platform\Platform::cases();
        $pausedPlatforms = SiteSetting::getJson('paused_platforms', []);
        $retryPolicy = SiteSetting::getJson('publish_retry_policy', [
            'max_tries' => 3,
            'backoff_seconds' => [10, 30, 90],
            'text_only_fallback' => true,
        ]);

        return view('admin.operations', compact(
            'failedPublishes',
            'failedComments',
            'tokenHealth',
            'allPlatforms',
            'pausedPlatforms',
            'retryPolicy'
        ));
    }

    public function retryPublish(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'post_platform_id' => 'required|integer|exists:post_platforms,id',
        ]);

        $pp = PostPlatform::findOrFail($validated['post_platform_id']);
        $pp->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        PublishPostToPlatformJob::dispatch($pp->id);

        return back()->with('success', 'Publish retry queued.');
    }

    public function retryComment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'post_platform_id' => 'required|integer|exists:post_platforms,id',
        ]);

        $pp = PostPlatform::findOrFail($validated['post_platform_id']);
        $pp->update([
            'comment_status' => 'queued',
            'comment_error_message' => null,
        ]);

        PublishPostCommentJob::dispatch($pp->id);

        return back()->with('success', 'Comment retry queued.');
    }

    public function updateOperationsSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'paused_platforms' => 'nullable|array',
            'paused_platforms.*' => 'string',
            'max_tries' => 'required|integer|min:1|max:10',
            'backoff_seconds' => 'required|string',
            'text_only_fallback' => 'nullable|boolean',
        ]);

        $paused = $validated['paused_platforms'] ?? [];
        SiteSetting::setJson('paused_platforms', $paused);

        $backoff = array_values(array_filter(array_map(
            fn ($v) => max(1, (int) trim($v)),
            explode(',', $validated['backoff_seconds'])
        )));
        if ($backoff === []) {
            $backoff = [10, 30, 90];
        }

        SiteSetting::setJson('publish_retry_policy', [
            'max_tries' => (int) $validated['max_tries'],
            'backoff_seconds' => $backoff,
            'text_only_fallback' => (bool) $request->boolean('text_only_fallback'),
        ]);

        return back()->with('success', 'Operations settings updated.');
    }

    public function clearApplicationCache(Request $request): RedirectResponse
    {
        $section = (string) $request->input('return_section', 'maintenance');

        try {
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            Artisan::call('event:clear');
        } catch (\Throwable $e) {
            return $this->redirectToSettingsSection($section, 'Could not clear caches: ' . $e->getMessage(), 'error');
        }

        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
        } catch (\Throwable $e) {
            return $this->redirectToSettingsSection($section, 'Caches cleared but could not rebuild: ' . $e->getMessage(), 'success');
        }

        return $this->redirectToSettingsSection(
            $section,
            'All caches cleared and rebuilt (config, routes, views, events, application cache).',
            'success'
        );
    }

    public function clearSiteCache(Request $request): RedirectResponse
    {
        return $this->clearApplicationCache($request);
    }

    public function cronJobs(CronSecretResolver $cronSecrets): View
    {
        $tasks = CronTask::query()
            ->orderBy('label')
            ->paginate(20, ['*'], 'tasks_page');

        $runs = CronTaskRun::query()
            ->with('cronTask:id,key,label')
            ->orderByDesc('ran_at')
            ->paginate(30, ['*'], 'runs_page');

        $cronSecret = $cronSecrets->get();
        $cronSecretFromDb = $cronSecrets->isStoredInDatabase();
        $cronTriggerUrl = $cronSecret !== ''
            ? url('/cron') . '?' . http_build_query(['token' => $cronSecret])
            : '';
        $cronApiUrl = url('/api/cron/run');
        $cpanelCommand = $cronTriggerUrl !== ''
            ? 'curl -s "' . $cronTriggerUrl . '"'
            : '';

        return view('admin.cron-jobs', compact(
            'tasks',
            'runs',
            'cronSecret',
            'cronSecretFromDb',
            'cronTriggerUrl',
            'cronApiUrl',
            'cpanelCommand'
        ));
    }

    public function updateCronSecret(Request $request, CronSecretResolver $cronSecrets): RedirectResponse
    {
        if ($request->boolean('clear_stored_cron_secret')) {
            $cronSecrets->clearStored();

            return back()->with('success', 'Stored cron secret removed. The app now uses CRON_SECRET from the server environment (if set).');
        }

        if ($request->boolean('generate_cron_secret')) {
            $plain = Str::random(48);
            $cronSecrets->store($plain);

            return back()
                ->with('success', 'A new random cron secret was saved. Copy the cPanel command below — it includes the new token.')
                ->with('new_cron_secret_once', $plain);
        }

        $secret = trim((string) $request->input('cron_secret', ''));
        if ($secret === '') {
            return back()->with('info', 'No change — enter a new secret, use Generate, or clear the stored secret.');
        }

        $request->validate([
            'cron_secret' => ['required', 'string', 'min:16', 'max:512', 'regex:/^\S+$/'],
        ], [
            'cron_secret.regex' => 'The secret must not contain spaces.',
        ]);

        $cronSecrets->store($secret);

        return back()->with('success', 'Cron secret saved. Update your cPanel job if the token changed.');
    }

    public function updateCronJob(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'interval_minutes' => 'required|integer|min:1|max:10080',
        ]);

        $task = CronTask::query()->findOrFail($id);
        $task->update([
            'enabled' => $request->boolean('enabled'),
            'interval_minutes' => (int) $validated['interval_minutes'],
        ]);

        return back()->with('success', "Cron task \"{$task->label}\" updated.");
    }

    public function runCronTaskNow(CronService $cronService, int $id): RedirectResponse
    {
        $task = CronTask::query()->findOrFail($id);
        $result = $cronService->runTask($task->key);

        if (($result['status'] ?? 'error') === 'success') {
            return back()->with('success', "Task \"{$task->label}\" ran successfully.");
        }

        $output = is_string($result['output'] ?? null) ? $result['output'] : 'Task failed.';

        return back()->with('error', "Task \"{$task->label}\" failed: {$output}");
    }

    public function runDueCronTasksNow(CronService $cronService): RedirectResponse
    {
        $results = $cronService->runDueTasks();
        $ran = collect($results)->where('status', '!=', 'skipped')->count();
        $failed = collect($results)->where('status', 'failed')->count();

        if ($ran === 0) {
            return back()->with('success', 'No due cron tasks to run right now.');
        }
        if ($failed > 0) {
            return back()->with('error', "Ran {$ran} task(s), {$failed} failed. Check run history below.");
        }

        return back()->with('success', "Ran {$ran} due cron task(s) successfully.");
    }

    public function generateSitemap(Request $request, PublicSeoFilesService $seo): RedirectResponse
    {
        $section = (string) $request->input('return_section', 'seo');

        try {
            $seo->writeSitemap();
        } catch (\Throwable $e) {
            return $this->redirectToSettingsSection($section, 'Could not write sitemap.xml: ' . $e->getMessage(), 'error');
        }

        return $this->redirectToSettingsSection($section, 'sitemap.xml was generated and saved at the site root.', 'success');
    }

    public function generateRobotsTxt(Request $request, PublicSeoFilesService $seo): RedirectResponse
    {
        $section = (string) $request->input('return_section', 'seo');

        try {
            $seo->writeRobotsTxt();
        } catch (\Throwable $e) {
            return $this->redirectToSettingsSection($section, 'Could not write robots.txt: ' . $e->getMessage(), 'error');
        }

        return $this->redirectToSettingsSection($section, 'robots.txt was generated and saved at the site root.', 'success');
    }

    private function redirectToSettingsSection(string $section, string $message, string $type = 'success'): RedirectResponse
    {
        $allowed = ['general', 'seo', 'landing', 'maintenance'];
        $normalized = in_array($section, $allowed, true) ? $section : 'general';

        return redirect()
            ->route('admin.settings', ['section' => $normalized])
            ->with($type, $message);
    }
}
