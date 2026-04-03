<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\CurrencyDisplayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin analytics: churn, cohort retention, ARPPU (avg revenue per paying user), and growth — with plan / gateway / currency filters.
 */
final class AdminAnalyticsService
{
    public function __construct(
        private readonly CurrencyDisplayService $currencyDisplay,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        [$from, $to] = $this->resolvePeriod($request);
        $planId = $this->nullableInt($request->input('plan_id'));
        $gateway = $this->nullableString($request->input('gateway'));
        $currency = $this->nullableString($request->input('currency'));

        $payingPlanScope = function ($q) use ($planId): void {
            $q->whereHas('planModel', static function ($pq): void {
                $pq->where('is_free', false);
            });
            if ($planId !== null) {
                $q->where('plan_id', $planId);
            }
        };

        $subGateway = function ($q) use ($gateway): void {
            if ($gateway !== null) {
                $q->where('gateway', $gateway);
            }
        };

        $churnQuery = Subscription::query()
            ->whereIn('status', ['canceled', 'past_due'])
            ->whereBetween('updated_at', [$from, $to]);
        $payingPlanScope($churnQuery);
        $subGateway($churnQuery);
        $churnedCount = (int) $churnQuery->count();

        $activePayingQuery = Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($q): void {
                $q->whereNull('current_period_end')
                    ->orWhere('current_period_end', '>', Carbon::now());
            });
        $payingPlanScope($activePayingQuery);
        $subGateway($activePayingQuery);
        $activePayingNow = (int) $activePayingQuery->count();

        $denom = max(1, $activePayingNow + $churnedCount);
        $churnRatePct = round(100 * $churnedCount / $denom, 2);

        $newPayingQuery = Subscription::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['active', 'trialing', 'past_due', 'canceled']);
        $payingPlanScope($newPayingQuery);
        $subGateway($newPayingQuery);
        $newPayingSubsInPeriod = (int) $newPayingQuery->count();

        $paymentBase = PaymentTransaction::query()
            ->where('status', 'completed')
            ->whereRaw('COALESCE(completed_at, updated_at) BETWEEN ? AND ?', [$from->toDateTimeString(), $to->toDateTimeString()]);

        if ($planId !== null) {
            $paymentBase->where('plan_id', $planId);
        }
        if ($gateway !== null) {
            $paymentBase->where('gateway', $gateway);
        }
        if ($currency !== null) {
            $paymentBase->whereRaw('UPPER(TRIM(currency)) = ?', [strtoupper($currency)]);
        }

        $totalCents = (int) (clone $paymentBase)->sum('amount_cents');
        $txCount = (int) (clone $paymentBase)->count();
        $distinctPayers = (int) ((clone $paymentBase)
            ->select(DB::raw('COUNT(DISTINCT user_id) as payer_count'))
            ->value('payer_count') ?? 0);
        $arpuCents = $distinctPayers > 0 ? (int) round($totalCents / $distinctPayers) : 0;

        $byCurrency = (clone $paymentBase)
            ->select(
                DB::raw('UPPER(TRIM(currency)) as c'),
                DB::raw('SUM(amount_cents) as cents'),
                DB::raw('COUNT(*) as n'),
                DB::raw('COUNT(DISTINCT user_id) as payers')
            )
            ->whereNotNull('currency')
            ->groupBy(DB::raw('UPPER(TRIM(currency))'))
            ->orderByDesc(DB::raw('SUM(amount_cents)'))
            ->get()
            ->map(static fn ($row) => [
                'currency' => (string) $row->c,
                'cents'    => (int) $row->cents,
                'tx_count' => (int) $row->n,
                'payers'   => (int) $row->payers,
            ])
            ->values()
            ->all();

        $churnedSample = (clone $churnQuery)
            ->with(['user:id,name,email', 'planModel:id,name,slug'])
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get();

        $cohortEnd = $to->copy()->min(Carbon::now())->endOfMonth();
        $cohortStart = $cohortEnd->copy()->subMonthsNoOverflow(11)->startOfMonth();

        $retentionCohorts = $this->retentionCohorts(
            $cohortStart,
            $cohortEnd,
            $planId,
            $gateway,
        );

        $signupSeries = $this->signupBuckets($from, $to);

        $currencyOptions = PaymentTransaction::query()
            ->whereNotNull('currency')
            ->selectRaw('DISTINCT UPPER(TRIM(currency)) as c')
            ->orderBy('c')
            ->pluck('c')
            ->filter()
            ->values()
            ->all();

        $gatewayOptions = collect()
            ->merge(
                Subscription::query()->whereNotNull('gateway')->distinct()->pluck('gateway')
            )
            ->merge(
                PaymentTransaction::query()->whereNotNull('gateway')->distinct()->pluck('gateway')
            )
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'period' => [
                'from'        => $from,
                'to'          => $to,
                'from_date'   => $from->toDateString(),
                'to_date'     => $to->toDateString(),
                'preset'      => $request->input('preset', '30'),
            ],
            'filters' => [
                'plan_id'  => $planId,
                'gateway'  => $gateway,
                'currency' => $currency !== null ? strtoupper($currency) : null,
            ],
            'churn' => [
                'churned_count'           => $churnedCount,
                'active_paying_now'       => $activePayingNow,
                'churn_rate_pct'          => $churnRatePct,
                'new_paying_subs_period'  => $newPayingSubsInPeriod,
                'denominator_note'        => 'Churn % uses churned in period ÷ (churned + currently active paying), with the same plan/gateway filters.',
            ],
            'revenue' => [
                'total_cents'      => $totalCents,
                'total_display'    => $this->formatMoney($totalCents, $currency ?? $this->currencyDisplay->baseCurrency()),
                'tx_count'         => $txCount,
                'distinct_payers'  => $distinctPayers,
                'arpu_cents'       => $arpuCents,
                'arpu_display'     => $this->formatMoney($arpuCents, $currency ?? $this->currencyDisplay->baseCurrency()),
                'by_currency'      => $byCurrency,
                'arpu_explanation' => 'Average revenue per distinct user with ≥1 completed payment in the period (ARPPU). Filters apply.',
            ],
            'retention_cohorts' => $retentionCohorts,
            'signup_series'     => $signupSeries,
            'churned_sample'    => $churnedSample,
            'currency_options'  => $currencyOptions,
            'gateway_options'   => $gatewayOptions,
        ];
    }

    /**
     * @return list<array{month: string, label: string, cohort_size: int, still_active: int, retention_pct: float}>
     */
    private function retentionCohorts(Carbon $cohortStart, Carbon $cohortEnd, ?int $planId, ?string $gateway): array
    {
        $out = [];
        $cursor = $cohortStart->copy();

        while ($cursor->lte($cohortEnd)) {
            $mStart = $cursor->copy()->startOfMonth();
            $mEnd = $cursor->copy()->endOfMonth();

            $q = Subscription::query()->whereBetween('created_at', [$mStart, $mEnd]);
            if ($planId !== null) {
                $q->where('plan_id', $planId);
            }
            if ($gateway !== null) {
                $q->where('gateway', $gateway);
            }

            $ids = $q->pluck('id');
            $size = $ids->count();
            $still = $size === 0 ? 0 : (int) Subscription::query()
                ->whereIn('id', $ids->all())
                ->whereIn('status', ['active', 'trialing'])
                ->where(function ($q2): void {
                    $q2->whereNull('current_period_end')
                        ->orWhere('current_period_end', '>', Carbon::now());
                })
                ->count();

            $pct = $size > 0 ? round(100 * $still / $size, 1) : 0.0;

            $out[] = [
                'month'         => $mStart->format('Y-m'),
                'label'         => $mStart->format('M Y'),
                'cohort_size'   => $size,
                'still_active'  => $still,
                'retention_pct' => $pct,
            ];

            $cursor->addMonthNoOverflow();
        }

        return $out;
    }

    /**
     * @return list<array{date: string, label: string, signups: int}>
     */
    private function signupBuckets(Carbon $from, Carbon $to): array
    {
        $days = min(120, max(1, $from->diffInDays($to) + 1));
        $useDaily = $days <= 45;

        if ($useDaily) {
            $rows = User::query()
                ->whereBetween('created_at', [$from, $to])
                ->select(DB::raw('DATE(created_at) as d'), DB::raw('count(*) as c'))
                ->groupBy('d')
                ->orderBy('d')
                ->get()
                ->keyBy('d');

            $out = [];
            $walk = $from->copy()->startOfDay();
            while ($walk->lte($to)) {
                $key = $walk->toDateString();
                $out[] = [
                    'date'    => $key,
                    'label'   => $walk->format('M j'),
                    'signups' => (int) ($rows[$key]->c ?? 0),
                ];
                $walk->addDay();
            }

            return $out;
        }

        $rows = User::query()
            ->whereBetween('created_at', [$from, $to])
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-01') as m"),
                DB::raw('count(*) as c')
            )
            ->groupBy('m')
            ->orderBy('m')
            ->get()
            ->keyBy('m');

        $out = [];
        $walk = $from->copy()->startOfMonth();
        $endM = $to->copy()->endOfMonth();
        while ($walk->lte($endM)) {
            $key = $walk->format('Y-m-01');
            $out[] = [
                'date'    => $key,
                'label'   => $walk->format('M Y'),
                'signups' => (int) ($rows[$key]->c ?? 0),
            ];
            $walk->addMonthNoOverflow();
        }

        return $out;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(Request $request): array
    {
        $preset = $request->input('preset', '30');

        if ($preset === 'custom') {
            $fromStr = $request->input('date_from');
            $toStr = $request->input('date_to');
            if (!is_string($fromStr) || !is_string($toStr) || $fromStr === '' || $toStr === '') {
                $to = Carbon::now()->endOfDay();
                $from = $to->copy()->subDays(29)->startOfDay();

                return [$from, $to];
            }
            $from = Carbon::parse($fromStr)->startOfDay();
            $to = Carbon::parse($toStr)->endOfDay();
            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            return [$from, $to];
        }

        $days = match ($preset) {
            '7' => 7,
            '90' => 90,
            '365' => 365,
            default => 30,
        };

        $to = Carbon::now()->endOfDay();
        $from = $to->copy()->subDays($days - 1)->startOfDay();

        return [$from, $to];
    }

    private function nullableInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $i = (int) $v;

        return $i > 0 ? $i : null;
    }

    private function nullableString(mixed $v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $t = trim($v);

        return $t === '' ? null : $t;
    }

    private function formatMoney(int $cents, string $currency): string
    {
        $code = strtoupper(strlen($currency) === 3 ? $currency : $this->currencyDisplay->baseCurrency());
        $amount = number_format($cents / 100, 2);

        return $code . ' ' . $amount;
    }
}
