<?php

namespace App\Services\Admin;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class SubscriptionInsightsService
{
    /**
     * @return array{
     *   active_count: int,
     *   trialing_count: int,
     *   mrr_cents: int,
     *   mrr_display: string,
     *   lifetime_active_count: int,
     *   lifetime_revenue_total_cents: int,
     *   lifetime_revenue_total_display: string,
     *   lifetime_revenue_30d_cents: int,
     *   lifetime_revenue_30d_display: string,
     *   plan_distribution: array<int, array{label: string, count: int, slug: string}>,
     *   gateway_breakdown: array<int, array{gateway: string, count: int}>,
     *   new_subs_by_day: array<int, array{date: string, count: int}>,
     *   revenue_by_day: array<int, array{date: string, cents: int}>,
     *   completed_payments_30d: int,
     *   pending_payments: int
     * }
     */
    public function build(): array
    {
        $now = Carbon::now();
        $start = $now->copy()->subDays(29)->startOfDay();

        $activeQuery = Subscription::query()
            ->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('current_period_end')
                    ->orWhere('current_period_end', '>', $now);
            });

        $activeCount = (clone $activeQuery)->count();

        $trialingCount = Subscription::query()
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', $now)
            ->count();

        $activeWithPlans = (clone $activeQuery)->with('planModel')->get();

        $mrrCents = 0;
        $lifetimeActiveCount = 0;
        foreach ($activeWithPlans as $sub) {
            $plan = $sub->planModel;
            if ($plan === null || $plan->is_free) {
                continue;
            }
            if ($plan->is_lifetime) {
                $lifetimeActiveCount++;

                continue;
            }
            if ($plan->monthly_price_cents !== null && $plan->monthly_price_cents > 0) {
                $mrrCents += (int) $plan->monthly_price_cents;
            } elseif ($plan->yearly_price_cents !== null && $plan->yearly_price_cents > 0) {
                $mrrCents += (int) round($plan->yearly_price_cents / 12);
            }
        }

        $lifetimeRevQuery = PaymentTransaction::query()
            ->where('status', 'completed')
            ->whereHas('plan', static fn ($q) => $q->where('is_lifetime', true));

        $lifetimeRevenueTotalCents = (int) (clone $lifetimeRevQuery)->sum('amount_cents');
        $lifetimeRevenue30dCents = (int) (clone $lifetimeRevQuery)
            ->where('updated_at', '>=', $start)
            ->sum('amount_cents');

        $planDistribution = (clone $activeQuery)
            ->select('plan_id', DB::raw('count(*) as c'))
            ->groupBy('plan_id')
            ->orderByDesc('c')
            ->get()
            ->map(function ($row) {
                $plan = Plan::find($row->plan_id);

                return [
                    'label' => $plan?->name ?? 'Unknown',
                    'slug'  => $plan?->slug ?? '',
                    'count' => (int) $row->c,
                ];
            })
            ->values()
            ->all();

        $gatewayBreakdown = (clone $activeQuery)
            ->select('gateway', DB::raw('count(*) as c'))
            ->whereNotNull('gateway')
            ->groupBy('gateway')
            ->orderByDesc('c')
            ->get()
            ->map(fn ($row) => [
                'gateway' => (string) $row->gateway,
                'count'   => (int) $row->c,
            ])
            ->values()
            ->all();

        $newSubsByDay = Subscription::query()
            ->where('created_at', '>=', $start)
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('count(*) as c'))
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn ($row) => [
                'date'  => (string) $row->d,
                'count' => (int) $row->c,
            ])
            ->values()
            ->all();

        $revenueByDay = PaymentTransaction::query()
            ->where('status', 'completed')
            ->where('created_at', '>=', $start)
            ->select(DB::raw('DATE(updated_at) as d'), DB::raw('sum(amount_cents) as total'))
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn ($row) => [
                'date'  => (string) $row->d,
                'cents' => (int) $row->total,
            ])
            ->values()
            ->all();

        $completedPayments30d = PaymentTransaction::query()
            ->where('status', 'completed')
            ->where('updated_at', '>=', $start)
            ->count();

        $pendingPayments = PaymentTransaction::query()->where('status', 'pending')->count();

        return [
            'active_count'           => $activeCount,
            'trialing_count'         => $trialingCount,
            'mrr_cents'              => $mrrCents,
            'mrr_display'            => '$' . number_format($mrrCents / 100, 0),
            'lifetime_active_count'  => $lifetimeActiveCount,
            'lifetime_revenue_total_cents' => $lifetimeRevenueTotalCents,
            'lifetime_revenue_total_display' => '$' . number_format($lifetimeRevenueTotalCents / 100, 0),
            'lifetime_revenue_30d_cents' => $lifetimeRevenue30dCents,
            'lifetime_revenue_30d_display' => '$' . number_format($lifetimeRevenue30dCents / 100, 0),
            'plan_distribution'      => $planDistribution,
            'gateway_breakdown'      => $gatewayBreakdown,
            'new_subs_by_day'        => $newSubsByDay,
            'revenue_by_day'         => $revenueByDay,
            'completed_payments_30d' => $completedPayments30d,
            'pending_payments'       => $pendingPayments,
        ];
    }
}
