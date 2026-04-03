<?php

namespace App\Services\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds a User query from marketing segment_rules JSON.
 *
 * Supported keys (combined with AND):
 * - paid_subscribers (bool): active/trialing subscription on a non-free plan
 * - free_only (bool): current plan is_free
 * - plan_slugs (string[]): subscription.plan in list
 * - active_last_n_days (int): last_login_at within N days
 * - inactive_last_n_days (int): last_login_at null or older than N days
 */
final class AudienceQueryService
{
    /**
     * @param  array<string, mixed>  $rules
     */
    public function queryUsers(array $rules): Builder
    {
        $q = User::query()->where('status', 'active');

        if (! empty($rules['paid_subscribers'])) {
            $q->whereHas('subscription', function (Builder $sub): void {
                $sub->whereIn('status', ['active', 'trialing'])
                    ->whereHas('planModel', function (Builder $plan): void {
                        $plan->where('is_free', false);
                    });
            });
        }

        if (! empty($rules['free_only'])) {
            $q->whereHas('subscription.planModel', function (Builder $plan): void {
                $plan->where('is_free', true);
            });
        }

        if (! empty($rules['plan_slugs']) && is_array($rules['plan_slugs'])) {
            $slugs = array_values(array_filter(array_map('strval', $rules['plan_slugs'])));
            if ($slugs !== []) {
                $q->whereHas('subscription', function (Builder $sub) use ($slugs): void {
                    $sub->whereIn('plan', $slugs);
                });
            }
        }

        if (isset($rules['active_last_n_days']) && $rules['active_last_n_days'] !== null && $rules['active_last_n_days'] !== '') {
            $n = max(0, (int) $rules['active_last_n_days']);
            $q->whereNotNull('last_login_at')
                ->where('last_login_at', '>=', now()->subDays($n));
        }

        if (isset($rules['inactive_last_n_days']) && $rules['inactive_last_n_days'] !== null && $rules['inactive_last_n_days'] !== '') {
            $n = max(0, (int) $rules['inactive_last_n_days']);
            $q->where(function (Builder $w) use ($n): void {
                $w->whereNull('last_login_at')
                    ->orWhere('last_login_at', '<=', now()->subDays($n));
            });
        }

        return $q;
    }
}
