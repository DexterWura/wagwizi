@extends('app')

@section('title', 'Dashboard — ' . config('app.name'))
@section('page-id', 'dashboard-home')

@section('content')
        <main class="app-content">
          <div class="filter-row">
            <div class="pills" role="tablist" aria-label="Date range">
              <a href="{{ $dashUrl(['range' => 'today']) }}" class="pill @if($range === 'today') pill--active @endif" @if($range === 'today') aria-current="page" @endif>Today</a>
              <a href="{{ $dashUrl(['range' => 'week']) }}" class="pill @if($range === 'week') pill--active @endif" @if($range === 'week') aria-current="page" @endif>This week</a>
              <a href="{{ $dashUrl(['range' => '30d']) }}" class="pill @if($range === '30d') pill--active @endif" @if($range === '30d') aria-current="page" @endif>30 days</a>
              <a href="{{ $dashUrl(['range' => '90d']) }}" class="pill @if($range === '90d') pill--active @endif" @if($range === '90d') aria-current="page" @endif>90 days</a>
            </div>
            <div class="filter-scope" role="tablist" aria-label="Scope">
              <a href="{{ $dashUrl(['scope' => 'all']) }}" class="filter-scope__link @if($scope === 'all') filter-scope__link--active @endif" @if($scope === 'all') aria-current="page" @endif><span class="filter-scope__dot filter-scope__dot--primary"></span> All accounts</a>
              <a href="{{ $dashUrl(['scope' => 'platform']) }}" class="filter-scope__link @if($scope === 'platform') filter-scope__link--active @endif" @if($scope === 'platform') aria-current="page" @endif><span class="filter-scope__dot filter-scope__dot--muted"></span> Per platform</a>
            </div>
            @if($scope === 'platform' && count($platformFilterOptions ?? []) > 0)
            <form method="get" action="{{ route('dashboard') }}" class="filter-platform-form">
              <input type="hidden" name="range" value="{{ $range }}" />
              <input type="hidden" name="scope" value="platform" />
              <label class="filter-platform-form__label" for="dashboard-platform">Platform</label>
              <select class="select select--sm" id="dashboard-platform" name="platform" onchange="this.form.submit()" aria-label="Network for per-platform metrics">
                @foreach($platformFilterOptions as $opt)
                  <option value="{{ $opt['slug'] }}" {{ ($platform ?? '') === $opt['slug'] ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                @endforeach
              </select>
            </form>
            @endif
          </div>

          @if($connectedAccountsCount === 0)
          <p class="app-context-banner" role="note">
            <i class="fa-solid fa-plug" aria-hidden="true"></i>
            <span>You have no social accounts connected. <a href="{{ route('accounts') }}">Connect an account</a> to publish and see analytics here.</span>
          </p>
          @else
          <p class="app-context-banner" role="note">
            <i class="fa-solid fa-globe" aria-hidden="true"></i>
            <span>Scheduled times and analytics use your display timezone (<strong data-app-timezone-label>UTC</strong>) from the top bar.</span>
          </p>
          @endif

          <div class="grid-metrics">
            <div class="metric-card">
              <div class="metric-card__label">
                Connected profiles
                <span class="metric-card__icons">
                  <i class="fa-solid fa-link" aria-hidden="true"></i>
                </span>
              </div>
              <div class="metric-card__value">{{ $connectedAccountsCount }}</div>
              <div class="metric-card__sub">{{ $scope === 'platform' ? 'This network' : 'Across networks' }}</div>
            </div>
            <div class="metric-card">
              <div class="metric-card__label">
                Total audience
                <span class="metric-card__icons"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
              </div>
              <div class="metric-card__value">{{ $totalAudienceCount !== null ? number_format($totalAudienceCount) : '—' }}</div>
              <div class="metric-card__sub">{{ $totalAudienceCount !== null ? 'Followers & subscribers across connected accounts' : 'Connect accounts/permissions to load follower counts' }}</div>
            </div>
            <div class="metric-card">
              <div class="metric-card__label">
                Engagement rate
                <span class="metric-card__icons"><i class="fa-solid fa-heart" aria-hidden="true"></i></span>
              </div>
              <div class="metric-card__value">{{ $engagementRateDisplay ?? '—' }}</div>
              <div class="metric-card__sub">{{ $engagementRateSubLabel ?? 'Connect accounts for analytics' }}</div>
            </div>
            <div class="metric-card">
              <div class="metric-card__label">
                Posts published
                <span class="metric-card__icons"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></span>
              </div>
              <div class="metric-card__value">{{ $publishedPostsCount }}</div>
              <div class="metric-card__sub">{{ $publishedSubLabel }}</div>
            </div>
            <div class="metric-card">
              <div class="metric-card__label">
                Scheduled
                <span class="metric-card__icons"><i class="fa-solid fa-clock" aria-hidden="true"></i></span>
              </div>
              <div class="metric-card__value">{{ $scheduledPostsCount }}</div>
              <div class="metric-card__sub">{{ $scheduledSubLabel }}</div>
            </div>
          </div>

          <div class="grid-charts">
            <div class="card">
              <div class="card__head">
                <span>Audience &amp; reach</span>
                <div class="card__head-controls">
                  <select class="dashboard-chart__filter" data-dashboard-chart-filter aria-label="Chart metric">
                    <option value="all" selected>All metrics</option>
                    <option value="posts">Posts published</option>
                    <option value="impressions">Impressions</option>
                    <option value="engagement">Engagement</option>
                  </select>
                  <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                </div>
              </div>
              <div class="dashboard-chart" data-dashboard-chart>
                <figure class="dashboard-chart__figure" aria-label="Activity over the selected date range">
                  <div class="dashboard-chart__plot" data-dashboard-chart-plot></div>
                </figure>
                <ul class="dashboard-chart__legend" data-dashboard-chart-legend></ul>
                <p class="dashboard-chart__note">
                  Posts use publish dates in your display timezone. Impressions and engagement come from synced network metrics on each post.
                  <span data-dashboard-chart-scale-note> With <strong>All metrics</strong>, each line is scaled to its own peak so you can compare timing.</span>
                </p>
                <script type="application/json" data-dashboard-chart-json>@json($activityChart)</script>
              </div>
            </div>
            <div class="card">
              <div class="card__head">
                <span><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Platform mix</span>
                <a class="card__head-link" href="{{ route('insights') }}">Insights <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
              </div>
              @if(count($platformMix ?? []) > 0)
                @php
                  $mix = $platformMix;
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
                <div class="insights-donut-block dashboard-platform-mix">
                  <div class="insights-donut insights-donut--dynamic" data-insights-conic="{{ $conicCss }}" role="img" aria-label="Platform mix for the selected range"></div>
                  <ul class="insights-donut-legend">
                    @foreach($mix as $i => $row)
                    <li><span class="insights-donut-legend__swatch" data-insights-swatch="{{ $donutColors[$i % count($donutColors)] }}"></span> {{ $row['label'] }} · {{ $row['pct'] }}%</li>
                    @endforeach
                  </ul>
                </div>
                <p class="dashboard-platform-mix__note">Weighted by engagement when synced; otherwise by posts published in this range.</p>
              @else
              <div class="empty-lg">
                <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                <strong>No platform data yet</strong>
                <span>Publish to this window or open <a href="{{ route('insights') }}">Insights</a> for the full report.</span>
              </div>
              @endif
            </div>
          </div>

          <div class="action-row">
            <a class="action-tile" href="{{ route('composer') }}">
              <span class="action-tile__left">
                <span class="action-tile__icon action-tile__icon--purple"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></span>
                New post
              </span>
              <span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </a>
            <a class="action-tile" href="{{ route('calendar') }}">
              <span class="action-tile__left">
                <span class="action-tile__icon action-tile__icon--orange"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                Schedule
              </span>
              <span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </a>
            <a class="action-tile" href="{{ route('insights') }}">
              <span class="action-tile__left">
                <span class="action-tile__icon action-tile__icon--green"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                Deep insights
              </span>
              <span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </a>
          </div>

          <div class="card dashboard-tools-card">
            <div class="card__head">
              <span><i class="fa-solid fa-toolbox" aria-hidden="true"></i> Tools in your plan</span>
            </div>
            @if(!empty($dashboardTools))
            <div class="dashboard-tools-list">
              @foreach($dashboardTools as $tool)
              <div class="dashboard-tool-item{{ $tool['enabled'] ? ' dashboard-tool-item--enabled' : '' }}">
                <div class="dashboard-tool-item__meta">
                  <strong>{{ $tool['label'] }}</strong>
                  <span>{{ $tool['category'] }}</span>
                </div>
                <div class="dashboard-tool-item__status">
                  @if($tool['enabled'])
                    <span class="dashboard-tool-badge dashboard-tool-badge--enabled">Enabled</span>
                  @else
                    <span class="dashboard-tool-badge">Locked</span>
                  @endif
                </div>
              </div>
              @endforeach
            </div>
            <p class="dashboard-tools-note">
              Enabled tools are available to your account. AI Caption Generator runs in <a href="{{ route('composer') }}">Composer</a>.
            </p>
            @else
            <div class="empty-sm">
              <i class="fa-solid fa-toolbox" aria-hidden="true"></i>
              No tool access data available.
            </div>
            @endif
          </div>

          <div class="grid-bottom">
            <div class="card">
              <div class="card__head">
                <span>Recent posts</span>
                <a class="card__head-link" href="{{ route('calendar') }}">Calendar <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
              </div>
              @if($recentPosts->isEmpty())
              <div class="empty-sm">
                <i class="fa-solid fa-list" aria-hidden="true"></i>
                No posts yet — <a href="{{ route('composer') }}">create your first post</a>.
              </div>
              @else
              <ul class="recent-posts-list" role="list">
                @foreach($recentPosts as $post)
                <li class="recent-post-item">
                  <span class="recent-post-item__status recent-post-item__status--{{ $post->status }}">{{ ucfirst($post->status) }}</span>
                  <span class="recent-post-item__text">{{ \Illuminate\Support\Str::limit($post->content, 60) }}</span>
                  <time class="recent-post-item__when">{{ ($post->published_at ?? $post->updated_at)->diffForHumans() }}</time>
                </li>
                @endforeach
              </ul>
              @endif
            </div>
            <div class="card">
              <div class="card__head">
                <span>Next up</span>
                <a class="card__head-link" href="{{ route('calendar') }}">View all <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
              </div>
              @if($nextUp->isEmpty())
              <div class="empty-sm">
                <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                No scheduled posts. <a href="{{ route('composer') }}">Schedule one now</a>.
              </div>
              @else
              <ul class="recent-posts-list" role="list">
                @foreach($nextUp as $post)
                <li class="recent-post-item">
                  <span class="recent-post-item__status recent-post-item__status--scheduled">Scheduled</span>
                  <span class="recent-post-item__text">{{ \Illuminate\Support\Str::limit($post->content, 60) }}</span>
                  <time class="recent-post-item__when">{{ $post->scheduled_at->format('M j, g:i A') }}</time>
                </li>
                @endforeach
              </ul>
              @endif
            </div>
            <div class="card ai-panel">
              <div class="card__head">
                <span>{{ config('app.name') }} assistant</span>
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
              </div>
              <div class="ai-disabled">
                Draft captions, adapt tone per platform, and review diffs in the composer.
                @if($composerAiLocked)
                  @if(!empty($composerAiQuotaExhausted))
                  <span class="ai-panel__note">Platform AI credits are used up for this period — renew your plan or use your own API key in Settings.</span>
                  @elseif(!empty($composerAiPlanNoPlatformAi))
                  <span class="ai-panel__note">This plan has no platform AI credits — add your own API key in Settings or upgrade.</span>
                  @endif
                @endif
              </div>
              <div class="ai-input">
                <input type="text" placeholder="Ask in Composer…" disabled aria-disabled="true" />
                @if($composerAiLocked)
                  <a href="{{ route('settings') }}" class="ai-input__pro-link" aria-label="Open AI settings or upgrade plan">
                    <span class="composer-ai-paywall__badge"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> AI</span>
                  </a>
                @else
                  <a href="{{ route('composer') }}" class="ai-input__composer-link" aria-label="Open composer">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                  </a>
                @endif
              </div>
            </div>
          </div>
        </main>
@endsection
