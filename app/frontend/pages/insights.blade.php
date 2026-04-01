@extends('app')

@section('title', 'Insights — ' . config('app.name'))
@section('page-id', 'insights')

@section('content')
        <main class="app-content app-content--insights">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true">
                  <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                </div>
                <div>
                  <h1>Insights</h1>
                  <p>Smart timing from your publish history and engagement signals. {{ $totalPublished }} published, {{ $totalScheduled }} scheduled.</p>
                </div>
              </div>
              <div class="head-actions">
                <button type="button" class="btn btn--outline" data-app-modal-open="modal-insights-range">
                  <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                  Date range
                </button>
              </div>
            </div>
          </div>

          <div class="filter-row">
            <div class="pills" data-app-radio-group role="tablist">
              <button type="button" class="pill" aria-selected="true">Overview</button>
              <button type="button" class="pill" aria-selected="false">Audience</button>
              <button type="button" class="pill" aria-selected="false">Content</button>
            </div>
          </div>

          <p class="app-context-banner" role="note">
            <i class="fa-solid fa-globe" aria-hidden="true"></i>
            <span>Reports respect your display timezone (<strong data-app-timezone-label>UTC</strong>). Connect accounts for live metrics.</span>
          </p>

          <div class="stat-grid stat-grid--spaced">
            <div class="stat-card">
              <div class="stat-card__label">Posts published</div>
              <div class="stat-card__value">{{ $totalPublished }}</div>
              <p class="field__hint stat-card__hint">{{ $audienceInsights->sampleSize }} platform rows in selected window for timing model</p>
            </div>
            <div class="stat-card">
              <div class="stat-card__label">Engagement rate</div>
              <div class="stat-card__value">@if($audienceInsights->hasEngagementMetrics){{ $audienceInsights->blendedEngagementRateEstimate }}%@else—@endif</div>
              <p class="field__hint stat-card__hint">@if($audienceInsights->hasEngagementMetrics)Interactions ÷ reach (likes, reposts, comments)@else Sync metrics on published posts to unlock@endif</p>
            </div>
            <div class="stat-card">
              <div class="stat-card__label">Best day</div>
              <div class="stat-card__value">
                @if($audienceInsights->bestWeekday !== null)
                  {{ \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::MONDAY)->addDays($audienceInsights->bestWeekday)->format('D') }}
                @else
                  —
                @endif
              </div>
              <p class="field__hint stat-card__hint">{{ $audienceInsights->hasEngagementMetrics ? 'Weighted by engagement' : 'By publish volume' }}</p>
            </div>
            <div class="stat-card">
              <div class="stat-card__label">Top channel</div>
              <div class="stat-card__value">{{ $audienceInsights->leadingPlatform['label'] ?? '—' }}</div>
              <p class="field__hint stat-card__hint">Strongest signal in range</p>
            </div>
          </div>

          <div class="card card--insights-smart">
            <div class="card__head"><span><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Smart scheduling intelligence</span></div>
            <div class="card__body insights-smart-body">
              <p class="insights-smart-lede">Peak posting windows (your timezone) and actionable suggestions for composer and calendar.</p>
              <ul class="insights-smart-list">
                @foreach($audienceInsights->schedulingSuggestions as $line)
                <li>{{ $line }}</li>
                @endforeach
              </ul>
              @if($audienceInsights->topHourSlots !== [])
              <p class="insights-smart-slots"><strong>Suggested times:</strong> {{ implode(' · ', array_column($audienceInsights->topHourSlots, 'label')) }}</p>
              @endif
            </div>
          </div>

          <div class="grid-charts">
            <div class="card">
              <div class="card__head">
                <span>Reach &amp; impressions</span>
                <button type="button" class="btn btn--ghost btn--compact" data-app-modal-open="modal-insights-range">Adjust range</button>
              </div>
              <div class="insights-chart-area">
                <svg class="insights-chart-area__svg" viewBox="0 0 480 168" role="img" aria-label="Reach and impressions trend (sample data)">
                  <defs>
                    <linearGradient id="insights-reach-fill" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#6366f1" stop-opacity="0.45" />
                      <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="insights-imp-stroke" x1="0" y1="0" x2="1" y2="0">
                      <stop offset="0%" stop-color="#a855f7" />
                      <stop offset="100%" stop-color="#ec4899" />
                    </linearGradient>
                  </defs>
                  <line class="insights-chart-area__baseline" x1="0" y1="140" x2="480" y2="140" stroke-width="1" />
                  <path fill="url(#insights-reach-fill)" d="M0,140 L0,102 L40,88 L80,96 L120,72 L160,86 L200,58 L240,68 L280,52 L320,62 L360,44 L400,50 L440,38 L480,42 L480,140 Z" />
                  <polyline fill="none" stroke="url(#insights-imp-stroke)" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" points="0,102 40,88 80,96 120,72 160,86 200,58 240,68 280,52 320,62 360,44 400,50 440,38 480,42" />
                </svg>
                <div class="insights-chart-area__axis">
                  <span>Week 1</span><span>Week 2</span><span>Week 3</span><span>Week 4</span>
                </div>
                <p class="insights-chart-footnote">Sample trend — connect accounts for live series.</p>
              </div>
            </div>
            <div class="card">
              <div class="card__head"><span>Top posts {{ $audienceInsights->hasEngagementMetrics ? 'by engagement signal' : 'by recent performance' }}</span></div>
              <div class="insights-bar-rank">
                @php
                  $topPostsList = $audienceInsights->topPosts;
                  $maxTopScore = $topPostsList === [] ? 1 : max(array_column($topPostsList, 'score'));
                  $maxTopScore = $maxTopScore > 0 ? $maxTopScore : 1;
                @endphp
                @forelse($topPostsList as $tp)
                @php $w = (int) round(100 * $tp['score'] / $maxTopScore); @endphp
                <div class="insights-bar-rank__row">
                  <span class="insights-bar-rank__label" title="{{ $tp['platforms'] }}">#{{ $tp['id'] }} · {{ $tp['excerpt'] }}</span>
                  <div class="insights-bar-rank__track" title="Relative score"><span class="insights-bar-rank__fill" style="width: {{ $w }}%"></span></div>
                </div>
                @empty
                <p class="prose-muted insights-bar-rank__empty">No published posts in this range yet.</p>
                @endforelse
              </div>
            </div>
          </div>

          <div class="card insights-hour-card">
            <div class="card__head"><span>Audience activity by hour (local time)</span></div>
            <div class="insights-hour-heat" role="img" aria-label="Relative activity by hour of day">
              @foreach($audienceInsights->hourlyScores as $h => $pct)
              <div class="insights-hour-heat__col" title="{{ sprintf('%02d:00 — %d%%', $h, $pct) }}">
                <span class="insights-hour-heat__bar" style="height: {{ max(3, $pct * 0.72) }}px"></span>
                <span class="insights-hour-heat__label">{{ $h % 6 === 0 ? $h : '' }}</span>
              </div>
              @endforeach
            </div>
            <p class="field__hint insights-hour-footnote">Taller bars = stronger signal (engagement when synced, otherwise publish frequency).</p>
          </div>

          <div class="grid-charts grid-charts--thirds">
            @php
              $mix = $audienceInsights->platformMix;
              $donutColors = ['#6366f1','#0a66c2','#e4405f','#f97316','#22c55e','#ec4899','#14b8a6','#a855f7'];
              $deg = 0;
              $conicParts = [];
              foreach ($mix as $i => $row) {
                $slice = 3.6 * $row['pct'];
                $c = $donutColors[$i % count($donutColors)];
                $conicParts[] = "{$c} {$deg}deg " . ($deg + $slice) . 'deg';
                $deg += $slice;
              }
              $conicCss = count($conicParts) ? implode(',', $conicParts) : '#475569 0deg 360deg';
            @endphp
            <div class="card">
              <div class="card__head"><span><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Signal mix by platform</span></div>
              <div class="insights-donut-block">
                <div class="insights-donut insights-donut--dynamic" style="background: conic-gradient({{ $conicCss }})" role="img" aria-label="Platform mix from your data"></div>
                <ul class="insights-donut-legend">
                  @forelse($mix as $i => $row)
                  <li><span class="insights-donut-legend__swatch" style="background: {{ $donutColors[$i % count($donutColors)] }}"></span> {{ $row['label'] }} · {{ $row['pct'] }}%</li>
                  @empty
                  <li class="prose-muted">No platform data in range</li>
                  @endforelse
                </ul>
              </div>
            </div>
            <div class="card">
              <div class="card__head"><span>Activity by weekday</span></div>
              <div class="insights-week-bars" role="img" aria-label="Relative activity Monday through Sunday">
                @php $dows = ['M','T','W','T','F','S','S']; $peak = max($audienceInsights->weekdayScores) ?: 1; @endphp
                @foreach($dows as $i => $d)
                @php $pct = $audienceInsights->weekdayScores[$i] ?? 0; $isPeak = $pct >= $peak && $pct > 0; $h = max(6, round($pct * 0.88)); @endphp
                <div class="insights-week-bars__col">
                  <span class="insights-week-bars__bar{{ $isPeak ? ' insights-week-bars__bar--peak' : '' }}" style="height: {{ $h }}px"></span>
                  <span class="insights-week-bars__dow">{{ $d }}</span>
                </div>
                @endforeach
              </div>
            </div>
            <div class="card">
              <div class="card__head"><span>Content format mix</span></div>
              <div class="insights-format-chart">
                <div class="insights-format-chart__row">
                  <span>Video</span>
                  <div class="insights-format-chart__track"><span class="insights-format-chart__fill insights-format-chart__fill--video" data-w="62"></span></div>
                  <span class="insights-format-chart__pct">62%</span>
                </div>
                <div class="insights-format-chart__row">
                  <span>Carousel</span>
                  <div class="insights-format-chart__track"><span class="insights-format-chart__fill insights-format-chart__fill--carousel" data-w="24"></span></div>
                  <span class="insights-format-chart__pct">24%</span>
                </div>
                <div class="insights-format-chart__row">
                  <span>Single image</span>
                  <div class="insights-format-chart__track"><span class="insights-format-chart__fill insights-format-chart__fill--image" data-w="14"></span></div>
                  <span class="insights-format-chart__pct">14%</span>
                </div>
              </div>
            </div>
          </div>

          <div class="grid-charts grid-charts--split">
            <div class="card">
              <div class="card__head"><span>Audience — new vs returning</span></div>
              <div class="insights-stacked-preview">
                <div class="insights-stacked-preview__bar">
                  <span class="insights-stacked-preview__seg insights-stacked-preview__seg--new" data-w="38">New</span>
                  <span class="insights-stacked-preview__seg insights-stacked-preview__seg--ret" data-w="62">Returning</span>
                </div>
                <div class="insights-stacked-preview__key">
                  <span><i class="insights-stacked-preview__dot insights-stacked-preview__dot--new"></i> New followers 38%</span>
                  <span><i class="insights-stacked-preview__dot insights-stacked-preview__dot--ret"></i> Returning 62%</span>
                </div>
              </div>
            </div>
            <div class="card">
              <div class="card__head"><span>Response time (median)</span></div>
              <div class="insights-spark-compare">
                <div class="insights-spark-compare__metric">
                  <span class="insights-spark-compare__label">Comments</span>
                  <strong>1.4h</strong>
                  <div class="insights-mini-spark" aria-hidden="true">
                    <span data-h="35"></span><span data-h="55"></span><span data-h="42"></span><span data-h="70"></span><span data-h="48"></span>
                  </div>
                </div>
                <div class="insights-spark-compare__metric">
                  <span class="insights-spark-compare__label">DMs</span>
                  <strong>3.2h</strong>
                  <div class="insights-mini-spark" aria-hidden="true">
                    <span data-h="50"></span><span data-h="40"></span><span data-h="65"></span><span data-h="45"></span><span data-h="58"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </main>
@endsection

@push('modals')
    <div class="app-modal" id="modal-insights-range" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-range-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel">
        <div class="app-modal__head">
          <div>
            <h2 id="modal-range-title">Date range</h2>
            <p class="app-modal__lede">Applies to all insight cards on this page.</p>
          </div>
          <button type="button" class="icon-btn" data-app-modal-close aria-label="Close dialog">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
        <div class="app-modal__body">
          <div class="form-grid form-grid--2">
            <div class="field">
              <label class="field__label" for="ins-from">From</label>
              <input class="input" id="ins-from" type="date" />
            </div>
            <div class="field">
              <label class="field__label" for="ins-to">To</label>
              <input class="input" id="ins-to" type="date" />
            </div>
          </div>
          <div class="pill-row" data-app-radio-group role="tablist" aria-label="Presets">
            <button type="button" class="pill" aria-selected="true">30 days</button>
            <button type="button" class="pill" aria-selected="false">90 days</button>
            <button type="button" class="pill" aria-selected="false">YTD</button>
          </div>
        </div>
        <div class="app-modal__foot">
          <button type="button" class="btn btn--ghost" data-app-modal-close>Cancel</button>
          <button type="button" class="btn btn--primary" data-app-modal-close>Apply</button>
        </div>
      </div>
    </div>
@endpush
