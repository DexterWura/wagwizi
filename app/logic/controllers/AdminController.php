<?php

namespace App\Controllers;

use App\Models\Plan;
use App\Models\PostPlatform;
use App\Models\SiteSetting;
use App\Models\SocialAccount;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\Testimonial;
use App\Models\User;
use App\Jobs\PublishPostCommentJob;
use App\Jobs\PublishPostToPlatformJob;
use App\Services\Admin\MigrationService;
use App\Services\Platform\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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
            $query->where('status', $status);
        }

        $users = $query->with('subscription.planModel')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->appends($request->only(['search', 'role', 'status']));

        return view('admin.users', compact('users'));
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

    // ── Plans ───────────────────────────────────────────

    public function plans(): View
    {
        $plans = Plan::orderBy('sort_order')->get();
        $enabledPlatforms = SiteSetting::getJson('enabled_platforms', []);

        return view('admin.plans', compact('plans', 'enabledPlatforms'));
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug'                          => 'required|string|max:50|unique:plans,slug',
            'name'                          => 'required|string|max:100',
            'description'                   => 'nullable|string',
            'monthly_price_cents'           => 'nullable|integer|min:0',
            'yearly_price_cents'            => 'nullable|integer|min:0',
            'max_social_profiles'           => 'nullable|integer|min:1',
            'max_scheduled_posts_per_month' => 'nullable|integer|min:1',
            'features'                      => 'nullable|string',
            'allowed_platforms'             => 'nullable|array',
            'allowed_platforms.*'           => 'string',
            'is_active'                     => 'boolean',
            'is_lifetime'                   => 'boolean',
            'lifetime_max_subscribers'      => 'nullable|integer|min:1',
            'sort_order'                    => 'integer|min:0',
        ]);

        $validated['features'] = $this->parseFeaturesList($validated['features'] ?? null);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_lifetime'] = $request->boolean('is_lifetime');

        Plan::create($validated);

        return back()->with('success', "Plan \"{$validated['name']}\" created.");
    }

    public function updatePlan(Request $request, int $id): RedirectResponse
    {
        $plan = Plan::findOrFail($id);

        $validated = $request->validate([
            'slug'                          => "required|string|max:50|unique:plans,slug,{$id}",
            'name'                          => 'required|string|max:100',
            'description'                   => 'nullable|string',
            'monthly_price_cents'           => 'nullable|integer|min:0',
            'yearly_price_cents'            => 'nullable|integer|min:0',
            'max_social_profiles'           => 'nullable|integer|min:1',
            'max_scheduled_posts_per_month' => 'nullable|integer|min:1',
            'features'                      => 'nullable|string',
            'allowed_platforms'             => 'nullable|array',
            'allowed_platforms.*'           => 'string',
            'is_active'                     => 'boolean',
            'is_lifetime'                   => 'boolean',
            'lifetime_max_subscribers'      => 'nullable|integer|min:1',
            'sort_order'                    => 'integer|min:0',
        ]);

        $validated['features'] = $this->parseFeaturesList($validated['features'] ?? null);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_lifetime'] = $request->boolean('is_lifetime');

        if (!$request->has('allowed_platforms')) {
            $validated['allowed_platforms'] = null;
        }

        $plan->update($validated);

        return back()->with('success', "Plan \"{$plan->name}\" updated.");
    }

    public function destroyPlan(int $id): RedirectResponse
    {
        $plan = Plan::findOrFail($id);

        if ($plan->subscriptions()->where('status', 'active')->exists()) {
            return back()->with('error', "Cannot delete \"{$plan->name}\" — it has active subscribers.");
        }

        $plan->delete();

        return back()->with('success', "Plan \"{$plan->name}\" deleted.");
    }

    private function parseFeaturesList(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') return null;
        return array_values(array_filter(array_map('trim', explode("\n", $raw))));
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

        return back()->with('success', 'Testimonial updated.');
    }

    public function destroyTestimonial(int $id): RedirectResponse
    {
        Testimonial::findOrFail($id)->delete();
        return back()->with('success', 'Testimonial deleted.');
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
            'app_name'         => SiteSetting::get('app_name', config('app.name')),
            'app_tagline'      => SiteSetting::get('app_tagline', ''),
            'hero_heading'     => SiteSetting::get('hero_heading', ''),
            'hero_subheading'  => SiteSetting::get('hero_subheading', ''),
            'registration_open' => SiteSetting::get('registration_open', '1'),
        ];

        return view('admin.settings', compact('settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $fields = ['app_name', 'app_tagline', 'hero_heading', 'hero_subheading', 'registration_open'];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                SiteSetting::set($field, $request->input($field, ''));
            }
        }

        return back()->with('success', 'Settings saved.');
    }

    // ── Migrations ──────────────────────────────────────

    public function migrations(): View
    {
        $service    = app(MigrationService::class);
        $migrations = $service->getAllMigrations();

        return view('admin.migrations', compact('migrations'));
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
            return back()->with('error', 'Migration failed: ' . $e->getMessage());
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
            return back()->with('error', 'Rollback failed: ' . $e->getMessage());
        }
    }

    // ── Operations & Reliability ───────────────────────

    public function operations(): View
    {
        $failedPublishes = PostPlatform::with(['post:id,user_id,content', 'socialAccount:id,user_id,platform,display_name,token_expires_at,status'])
            ->where('status', 'failed')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        $failedComments = PostPlatform::with(['post:id,user_id,content', 'socialAccount:id,user_id,platform,display_name,token_expires_at,status'])
            ->where('comment_status', 'failed')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

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
}
