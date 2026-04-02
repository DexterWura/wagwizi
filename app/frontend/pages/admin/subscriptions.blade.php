@extends('app')

@section('title', 'Subscriptions & revenue — ' . config('app.name'))
@section('page-id', 'admin-subscriptions')

@php
  $chartLabels = [];
  $chartNewSubs = [];
  $chartRevenue = [];
  for ($i = 29; $i >= 0; $i--) {
      $d = now()->subDays($i)->format('Y-m-d');
      $chartLabels[] = now()->subDays($i)->format('M j');
      $rowNew = collect($stats['new_subs_by_day'])->firstWhere('date', $d);
      $chartNewSubs[] = $rowNew ? (int) $rowNew['count'] : 0;
      $rowRev = collect($stats['revenue_by_day'])->firstWhere('date', $d);
      $chartRevenue[] = round(($rowRev ? (int) $rowRev['cents'] : 0) / 100, 2);
  }
@endphp

@section('content')
        <main class="app-content app-content--admin-subs">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-chart-pie"></i></div>
                <div>
                  <h1>Subscriptions &amp; revenue</h1>
                  <p>Active plans, estimated MRR, and recent payment activity.</p>
                </div>
              </div>
            </div>
          </div>

          <div class="admin-metrics-row">
            <article class="card admin-metric-card">
              <span class="admin-metric-card__label">Active subscriptions</span>
              <strong class="admin-metric-card__value">{{ number_format($stats['active_count']) }}</strong>
              <span class="admin-metric-card__hint">Excludes expired periods</span>
            </article>
            <article class="card admin-metric-card admin-metric-card--accent">
              <span class="admin-metric-card__label">Est. MRR</span>
              <strong class="admin-metric-card__value">{{ $stats['mrr_display'] }}</strong>
              <span class="admin-metric-card__hint">From plan list prices (monthly or yearly ÷ 12)</span>
            </article>
            <article class="card admin-metric-card">
              <span class="admin-metric-card__label">Trialing</span>
              <strong class="admin-metric-card__value">{{ number_format($stats['trialing_count']) }}</strong>
            </article>
            <article class="card admin-metric-card">
              <span class="admin-metric-card__label">Payments (30d)</span>
              <strong class="admin-metric-card__value">{{ number_format($stats['completed_payments_30d']) }}</strong>
              <span class="admin-metric-card__hint">{{ $stats['pending_payments'] }} pending</span>
            </article>
          </div>

          <div class="admin-charts-grid">
            <div class="card admin-chart-card">
              <div class="card__head">New subscriptions (30 days)</div>
              <div class="card__body">
                <div class="admin-chart-wrap">
                  <canvas id="admin-chart-new-subs" height="220" aria-label="New subscriptions chart"></canvas>
                </div>
              </div>
            </div>
            <div class="card admin-chart-card">
              <div class="card__head">Completed payment volume (30 days)</div>
              <div class="card__body">
                <div class="admin-chart-wrap">
                  <canvas id="admin-chart-revenue" height="220" aria-label="Payment volume chart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <div class="admin-charts-grid admin-charts-grid--split">
            <div class="card admin-chart-card">
              <div class="card__head">Active subscribers by plan</div>
              <div class="card__body">
                <div class="admin-chart-wrap admin-chart-wrap--donut">
                  <canvas id="admin-chart-plans" height="260" aria-label="Plan distribution"></canvas>
                </div>
              </div>
            </div>
            <div class="card">
              <div class="card__head">Gateway (active subs)</div>
              <div class="card__body">
                @forelse($stats['gateway_breakdown'] as $row)
                  <div class="admin-mini-stat">
                    <span class="admin-mini-stat__label">{{ $row['gateway'] ?: '—' }}</span>
                    <strong class="admin-mini-stat__value">{{ number_format($row['count']) }}</strong>
                  </div>
                @empty
                  <p class="prose-muted">No gateway recorded on active subscriptions yet.</p>
                @endforelse
              </div>
            </div>
          </div>

          <div class="admin-two-col">
            <div class="card">
              <div class="card__head">Recent subscription updates</div>
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
                    @foreach($recentSubs as $sub)
                    <tr>
                      <td>{{ $sub->user?->email ?? '—' }}</td>
                      <td>{{ $sub->planModel?->name ?? $sub->plan }}</td>
                      <td>{{ $sub->status }}</td>
                      <td>{{ $sub->updated_at?->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card">
              <div class="card__head">Recent payment attempts</div>
              <div class="card__body admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>User</th>
                      <th>Plan</th>
                      <th>Amount</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($recentPayments as $tx)
                    <tr>
                      <td>{{ $tx->user?->email ?? '—' }}</td>
                      <td>{{ $tx->plan?->name ?? '—' }}</td>
                      <td>${{ number_format($tx->amount_cents / 100, 2) }}</td>
                      <td>{{ $tx->status }}</td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </main>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    <script>
      (function () {
        var labels = @json($chartLabels);
        var newSubs = @json($chartNewSubs);
        var revenue = @json($chartRevenue);
        var planLabels = @json(array_column($stats['plan_distribution'], 'label'));
        var planCounts = @json(array_column($stats['plan_distribution'], 'count'));
        var cs = getComputedStyle(document.documentElement);
        var muted = cs.getPropertyValue('--text-muted').trim() || '#888';
        var accent = cs.getPropertyValue('--accent-orange').trim() || '#f97316';
        var border = cs.getPropertyValue('--border').trim() || '#333';
        var text = cs.getPropertyValue('--text').trim() || '#fff';

        function barChart(id, data, label, color) {
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
                x: { ticks: { color: muted, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 }, grid: { color: border } },
                y: { ticks: { color: muted }, grid: { color: border }, beginAtZero: true }
              }
            }
          });
        }

        barChart('admin-chart-new-subs', newSubs, 'New subs', accent);
        barChart('admin-chart-revenue', revenue, 'USD', '#6366f1');

        var donut = document.getElementById('admin-chart-plans');
        if (donut && typeof Chart !== 'undefined' && planLabels.length) {
          var colors = ['#6366f1', '#22c55e', '#f59e0b', '#ec4899', '#14b8a6', '#a855f7'];
          new Chart(donut, {
            type: 'doughnut',
            data: {
              labels: planLabels,
              datasets: [{
                data: planCounts,
                backgroundColor: planLabels.map(function (_, i) { return colors[i % colors.length]; }),
                borderWidth: 0
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { position: 'bottom', labels: { color: text, boxWidth: 12 } }
              }
            }
          });
        }
      })();
    </script>
@endpush
