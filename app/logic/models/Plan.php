<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'monthly_price_cents',
        'yearly_price_cents',
        'max_social_profiles',
        'max_scheduled_posts_per_month',
        'features',
        'allowed_platforms',
        'allowed_tools',
        'is_active',
        'is_lifetime',
        'lifetime_max_subscribers',
        'lifetime_current_count',
        'sort_order',
        'is_free',
        'has_free_trial',
        'free_trial_days',
        'platform_ai_tokens_per_period',
    ];

    protected function casts(): array
    {
        return [
            'features'           => 'array',
            'allowed_platforms'  => 'array',
            'allowed_tools'      => 'array',
            'is_active'          => 'boolean',
            'is_lifetime'        => 'boolean',
            'is_free'            => 'boolean',
            'has_free_trial'     => 'boolean',
            'free_trial_days'                 => 'integer',
            'platform_ai_tokens_per_period'   => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function getMonthlyPriceDollars(): ?float
    {
        return $this->monthly_price_cents !== null
            ? $this->monthly_price_cents / 100
            : null;
    }

    public function getYearlyPriceDollars(): ?float
    {
        return $this->yearly_price_cents !== null
            ? $this->yearly_price_cents / 100
            : null;
    }

    public function hasUnlimitedProfiles(): bool
    {
        return $this->max_social_profiles === null;
    }

    public function hasUnlimitedPosts(): bool
    {
        return $this->max_scheduled_posts_per_month === null;
    }

    public function allowsPlatform(string $slug): bool
    {
        if ($this->allowed_platforms === null) {
            return true;
        }

        return in_array($slug, $this->allowed_platforms, true);
    }

    public function allowsTool(string $slug): bool
    {
        if ($this->allowed_tools === null) {
            return true;
        }

        return in_array($slug, $this->allowed_tools, true);
    }

    public function hasReachedLifetimeCap(): bool
    {
        if (!$this->is_lifetime || $this->lifetime_max_subscribers === null) {
            return false;
        }

        return $this->lifetime_current_count >= $this->lifetime_max_subscribers;
    }

    public function freeTrialSummary(): ?string
    {
        if ($this->is_free) {
            return null;
        }

        if (! $this->has_free_trial || $this->free_trial_days === null || $this->free_trial_days < 1) {
            return null;
        }

        $d = (int) $this->free_trial_days;

        return $d === 1 ? '1-day free trial' : "{$d}-day free trial";
    }
}
