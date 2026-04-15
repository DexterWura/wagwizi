<?php

namespace App\Models;

use App\Services\Ai\PlatformAiConfigService;
use App\Services\Ai\PlatformAiQuotaService;
use App\Services\Subscription\PlanWebhookFeatureService;
use App\Services\Subscription\PlanReplyFeatureService;
use App\Services\Subscription\PlanWorkflowFeatureService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'referral_code',
        'referred_by_user_id',
        'password',
        'google_id',
        'linkedin_id',
        'role',
        'status',
        'profile_completed',
        'avatar_path',
        'phone',
        'bio',
        'timezone',
        'theme_preference',
        'locale',
        'workspace_name',
        'default_posting_time',
        'notification_preferences',
        'marketing_email_opt_in',
        'last_login_at',
        'ai_source',
        'ai_provider',
        'ai_base_url',
        'ai_api_key',
        'webhook_key_id',
        'webhook_secret',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'ai_api_key',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'password'                 => 'hashed',
            'profile_completed'        => 'boolean',
            'notification_preferences' => 'array',
            'last_login_at'            => 'datetime',
            'marketing_email_opt_in' => 'boolean',
            'ai_api_key'             => 'encrypted',
            'webhook_secret'         => 'encrypted',
        ];
    }

    public function hasAiApiKeyStored(): bool
    {
        $raw = $this->getAttributes()['ai_api_key'] ?? null;

        return $raw !== null && $raw !== '';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isSupport(): bool
    {
        return $this->role === 'support';
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function planChanges(): HasMany
    {
        return $this->hasMany(PlanChange::class);
    }

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications(): HasMany
    {
        return $this->notifications()->whereNull('read_at');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_user_id');
    }

    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    public function activeWorkspaceMembership(): ?WorkspaceMembership
    {
        return $this->workspaceMemberships()
            ->where('status', 'active')
            ->with('workspace')
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    public function activeWorkspace(): ?Workspace
    {
        return $this->activeWorkspaceMembership()?->workspace;
    }

    public function marketingCampaignsCreated(): HasMany
    {
        return $this->hasMany(MarketingCampaign::class, 'created_by');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_user_id');
    }

    public function affiliateCommissionsEarned(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'referrer_user_id');
    }

    /**
     * Show upgrade CTA when the user is on the free tier (or has no plan linked yet).
     */
    public function shouldShowUpgradePlan(): bool
    {
        $this->loadMissing('subscription.planModel');
        $plan = $this->subscription?->planModel;

        if ($plan === null) {
            return true;
        }

        return $plan->is_free === true;
    }

    /**
     * Absolute URL for the user's avatar: remote OAuth URL, uploaded file under public/, or Gravatar.
     */
    public function avatarUrl(int $displaySize = 128): string
    {
        $raw = $this->avatar_path;
        if (is_string($raw)) {
            $trim = trim($raw);
            if ($trim !== '') {
                if (preg_match('#^https?://#i', $trim)) {
                    return $trim;
                }
                $path = ltrim(str_replace('\\', '/', $trim), '/');
                if ($path !== '' && is_file(public_path($path))) {
                    return asset($path);
                }
            }
        }

        return $this->gravatarUrl($displaySize);
    }

    private function gravatarUrl(int $size): string
    {
        $hash = md5(strtolower(trim((string) $this->email)));

        return 'https://www.gravatar.com/avatar/' . $hash . '?d=mp&s=' . $size;
    }

    /**
     * Paid (non-free) plan with an active or trialing subscription — used for platform-billed AI only.
     */
    public function hasPaidActiveSubscriptionForAi(): bool
    {
        $this->loadMissing('subscription.planModel');
        $sub  = $this->subscription;
        $plan = $sub?->planModel;

        if ($sub === null || $plan === null || $plan->is_free === true) {
            return false;
        }

        return $sub->isActive() || $sub->isTrialing();
    }

    /**
     * Whether the user is using bring-your-own-key (stored key + explicit BYOK mode).
     */
    public function usesComposerAiByok(): bool
    {
        return $this->ai_source === 'byok' && $this->hasAiApiKeyStored();
    }

    /**
     * Composer AI Assist: super admins; or BYOK with a saved key (any plan, including free);
     * or platform AI for paid active/trialing users when the selected platform provider key is configured server-side.
     */
    public function canAccessComposerAi(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->usesComposerAiByok()) {
            return true;
        }

        if (($this->ai_source ?? 'platform') !== 'platform') {
            return false;
        }

        if (! $this->hasPaidActiveSubscriptionForAi()) {
            return false;
        }

        if (! app(PlatformAiConfigService::class)->isConfigured()) {
            return false;
        }

        $quota = app(PlatformAiQuotaService::class);

        return ! $quota->isPlatformAiDisabledOnPlan($this)
            && ! $quota->isPlatformAiQuotaExhausted($this);
    }

    /**
     * Paid plan trial ended — user must subscribe or pick the free plan (if one exists).
     */
    public function isSubscriptionPastDueAfterTrial(): bool
    {
        $this->loadMissing('subscription.planModel');
        $sub = $this->subscription;
        if ($sub === null || $sub->status !== 'past_due') {
            return false;
        }

        $plan = $sub->planModel;

        return $plan !== null && ! $plan->is_free;
    }

    /**
     * First-comment / reply publishing after the main post (plan-gated).
     */
    public function canUseFirstCommentReplies(): bool
    {
        return app(PlanReplyFeatureService::class)
            ->userMayUseFirstCommentReplies($this->id);
    }

    public function canUseWorkflows(): bool
    {
        return app(PlanWorkflowFeatureService::class)
            ->userMayUseWorkflows($this->id);
    }

    public function canUseWebhooks(): bool
    {
        return app(PlanWebhookFeatureService::class)
            ->userMayUseWebhooks($this->id);
    }
}
