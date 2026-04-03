@extends('app')

@section('title', 'Analytics — ' . config('app.name'))
@section('page-id', 'admin-analytics')

@php
  $p = $report['period'];
  $ch = $report['churn'];
  $rev = $report['revenue'];
  $cohorts = $report['retention_cohorts'];
  $signups = $report['signup_series'];
  $hasFilters = request()->hasAny(['preset', 'plan_id', 'gateway', 'currency', 'date_from', 'date_to']);
@endphp

@section('content')
        <main class="app-content app-content--admin-analytics">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-chart-pie"></i></div>
                <div>
                  <h1>Analytics</h1>
                  <p>Churn, cohort retention, revenue, and ARPPU with filters. Subscription metrics consider <strong>paid</strong> plans only; cohorts include every subscription unless you filter by plan.</p>
                </div>
              </div>
            </div>
          </div>

          <div class="card admin-analytics-filters">
            <div class="card__head">Filters</div>
            <div class="card__body">
              <form method="GET" action="{{ route('admin.analytics') }}" class="admin-filter-bar admin-filter-bar--wrap">
                <select class="select select--sm" name="preset" id="analytics-preset" data-analytics-preset>
                  <option value="7" {{ ($p['preset'] ?? '') === '7' ? 'selected' : '' }}>Last 7 days</option>
                  <option value="30" {{ ($p['preset'] ?? '30') === '30' ? 'selected' : '' }}>Last 30 days</option>
                  <option value="90" {{ ($p['preset'] ?? '') === '90' ? 'selected' : '' }}>Last 90 days</option>
                  <option value="365" {{ ($p['preset'] ?? '') === '365' ? 'selected' : '' }}>Last 365 days</option>
                  <option value="custom" {{ ($p['preset'] ?? '') === 'custom' ? 'selected' : '' }}>Custom range</option>
                </select>
                <span class="admin-analytics-custom-dates {{ ($p['preset'] ?? '') === 'custom' ? '' : 'admin-analytics-custom-dates--hidden' }}" data-analytics-custom-wrap>
                  <label class="admin-filter-bar__date"><span class="sr-only">From</span>
                    <input class="input input--sm" type="date" name="date_from" value="{{ request('date_from', $p['from_date'] ?? '') }}" data-analytics-date-from />
                  </label>
                  <label class="admin-filter-bar__date"><span class="sr-only">To</span>
                    <input class="input input--sm" type="date" name="date_to" value="{{ request('date_to', $p['to_date'] ?? '') }}" data-analytics-date-to />
                  </label>
                </span>
                <select class="select select--sm" name="plan_id">
                  <option value="">All paid plans (churn / ARPPU)</option>
                  @foreach($plans as $pl)
                  <option value="{{ $pl->id }}" {{ (string) request('plan_id') === (string) $pl->id ? 'selected' : '' }}>{{ $pl->name }}</option>
                  @endforeach
                </select>
                <select class="select select--sm" name="gateway">
                  <option value="">All gateways</option>
                  @foreach($report['gateway_options'] as $gw)
                  <option value="{{ $gw }}" {{ request('gateway') === $gw ? 'selected' : '' }}>{{ $gw }}</option>
                  @endforeach
                </select>
                <select class="select select--sm" name="currency">
                  <option value="">All currencies (revenue)</option>
                  @foreach($report['currency_options'] as $cur)
                  <option value="{{ $cur }}" {{ strtoupper((string) request('currency')) === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                  @endforeach
                </select>
                <button class="btn btn--primary btn--compact" type="submit">Apply</button>
                @if($hasFilters)
                <a class="btn btn--ghost btn--compact" href="{{ route('admin.analytics') }}">Reset</a>
                @endif
              </form>
              <p class="admin-analytics-period prose-muted">
                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                Reporting window: <strong>{{ $p['from']->format('M j, Y') }}</strong> → <strong>{{ $p['to']->format('M j, Y') }}</strong>
                @if(($p['preset'] ?? '') !== 'custom')
                  <span class="prose-muted">({{ $p['preset'] }}-day preset)</span>
                @endif
              </p>
            </div>
          </div>

          <div class="admin-metrics-row">
            <article class="card admin-metric-card admin-metric-card--accent">
              <span class="admin-metric-card__label">Churn rate (approx.)</span>
              <strong class="admin-metric-card__value">{{ number_format($ch['churn_rate_pct'], 2) }}%</strong>
              <span class="admin-metric-card__hint">{{ number_format($ch['churned_count']) }} canceled / past_due in period · {{ $ch['denominator_note'] }}</span>
            </article>
            <article class="card admin-metric-card">
              <span class="admin-metric-card__label">Active paying subs</span>
              <strong class="admin-metric-card__value">{{ number_format($ch['active_paying_now']) }}</strong>
              <span class="admin-metric-card__hint">Snapshot: active/trialing, non-free, unexpired period</span>
            </article>
            <article class="card admin-metric-card">
              <span class="admin-metric-card__label">ARPPU</span>
              <strong class="admin-metric-card__value">{{ $rev['arpu_display'] }}</strong>
              <span class="admin-metric-card__hint">{{ number_format($rev['distinct_payers']) }} distinct payers · {{ $rev['arpu_explanation'] }}</span>
            </article>
            <article class="card admin-metric-card">
              <span class="admin-metric-card__label">Revenue (completed)</span>
              <strong class="admin-metric-card__value">{{ $rev['total_display'] }}</strong>
              <span class="admin-metric-card__hint">{{ number_format($rev['tx_count']) }} payments in window</span>
            </article>
            <article class="card admin-metric-card">
              <span class="admin-metric-card__label">New paid subs (period)</span>
              <strong class="admin-metric-card__value">{{ number_format($ch['new_paying_subs_period']) }}</strong>
              <span class="admin-metric-card__hint">Subscriptions created in range (non-free plan)</span>
            </article>
          </div>

          <div class="admin-charts-grid">
            <div class="card admin-chart-card">
              <div class="card__head">New account signups</div>
              <div class="card__body">
                <div class="admin-chart-wrap">
                  <canvas id="admin-analytics-signups" height="240" aria-label="Signups chart"></canvas>
                </div>
              </div>
            </div>
            <div class="card admin-chart-card">
              <div class="card__head">Cohort retention (still active or trialing)</div>
              <div class="card__body">
                <div class="admin-chart-wrap">
                  <canvas id="admin-analytics-retention" height="240" aria-label="Retention chart"></canvas>
                </div>
                <p class="prose-muted admin-analytics-footnote">Twelve monthly cohorts ending {{ $p['to']->format('M Y') }}: share of subscriptions created that month that are still active/trialing with a valid period.</p>
              </div>
            </div>
          </div>

          <div class="admin-two-col">
            <div class="card">
              <div class="card__head">Revenue by currency (same filters)</div>
              <div class="card__body admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Currency</th>
                      <th>Volume</th>
                      <th>Payments</th>
                      <th>Distinct payers</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($rev['by_currency'] as $row)
                    <tr>
                      <td>{{ $row['currency'] }}</td>
                      <td>{{ $row['currency'] }} {{ number_format($row['cents'] / 100, 2) }}</td>
                      <td>{{ number_format($row['tx_count']) }}</td>
                      <td>{{ number_format($row['payers']) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="prose-muted">No completed payments in this window with current filters.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card">
              <div class="card__head">Cohort detail</div>
              <div class="card__body admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Month</th>
                      <th>Cohort size</th>
                      <th>Still active</th>
                      <th>Retention</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($cohorts as $c)
                    <tr>
                      <td>{{ $c['label'] }}</td>
                      <td>{{ number_format($c['cohort_size']) }}</td>
                      <td>{{ number_format($c['still_active']) }}</td>
                      <td>{{ number_format($c['retention_pct'], 1) }}%</td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card__head">Recent churn (canceled / past_due) in period</div>
            <div class="card__body admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Updated</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($report['churned_sample'] as $sub)
                  <tr>
                    <td>
                      @if($sub->user)
                        <span title="{{ $sub->user->email }}">{{ $sub->user->name }}</span>
                        <div class="prose-muted admin-table__sub">{{ $sub->user->email }}</div>
                      @else
                        —
                      @endif
                    </td>
                    <td>{{ $sub->planModel?->name ?? $sub->plan }}</td>
                    <td>{{ $sub->status }}</td>
                    <td>{{ $sub->updated_at?->format('M j, Y H:i') }}</td>
                  </tr>
                  @empty
                  <tr><td colspan="4" class="prose-muted">No matching churn events in this window.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </main>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    <script>
      (function () {
        var signupLabels = @json(array_column($signups, 'label'));
        var signupData = @json(array_column($signups, 'signups'));
        var retLabels = @json(array_column($cohorts, 'label'));
        var retData = @json(array_column($cohorts, 'retention_pct'));
        var cs = getComputedStyle(document.documentElement);
        var muted = cs.getPropertyValue('--text-muted').trim() || '#888';
        var accent = cs.getPropertyValue('--accent-orange').trim() || '#f97316';
        var border = cs.getPropertyValue('--border').trim() || '#333';
        var text = cs.getPropertyValue('--text').trim() || '#fff';
        var green = cs.getPropertyValue('--accent-green').trim() || '#22c55e';

        function barChart(id, labels, data, label, color) {
          var el = document.getElementById(id);
          if (!el || typeof Chart === 'undefined') return;
          new Chart(el, {
            type: 'bar',
            data: {
              labels: labels,
              datasets: [{ label: label, data: data, backgroundColor: color, borderRadius: 4 }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: false } },
              scales: {
                x: { ticks: { color: muted, maxRotation: 45, autoSkip: true, maxTicksLimit: 12 }, grid: { color: border } },
                y: { ticks: { color: muted }, grid: { color: border }, beginAtZero: true }
              }
            }
          });
        }

        barChart('admin-analytics-signups', signupLabels, signupData, 'Signups', accent);
        barChart('admin-analytics-retention', retLabels, retData, 'Retention %', green);

        var preset = document.getElementById('analytics-preset');
        var customWrap = document.querySelector('[data-analytics-custom-wrap]');
        function syncCustomVisibility() {
          if (!preset || !customWrap) return;
          var isCustom = preset.value === 'custom';
          customWrap.classList.toggle('admin-analytics-custom-dates--hidden', !isCustom);
        }
        if (preset) {
          preset.addEventListener('change', syncCustomVisibility);
          syncCustomVisibility();
        }
      })();
    </script>
@endpush
