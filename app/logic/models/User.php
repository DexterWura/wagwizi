<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'locale',
        'workspace_name',
        'workspace_slug',
        'default_posting_time',
        'notification_preferences',
        'marketing_email_opt_in',
        'last_login_at',
        'ai_source',
        'ai_provider',
        'ai_base_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
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
        ];
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

    public function marketingCampaignsCreated(): HasMany
    {
        return $this->hasMany(MarketingCampaign::class, 'created_by');
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
     * Composer “AI Assist” for non–free plans with an active or trialing subscription; super admins always allowed.
     */
    public function canAccessComposerAi(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->loadMissing('subscription.planModel');
        $sub  = $this->subscription;
        $plan = $sub?->planModel;

        if ($sub === null || $plan === null || $plan->is_free === true) {
            return false;
        }

        return $sub->isActive() || $sub->isTrialing();
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
}
