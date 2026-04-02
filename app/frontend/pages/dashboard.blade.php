@extends('app')

@section('title', 'Dashboard — ' . config('app.name'))
@section('page-id', 'dashboard-home')

@section('content')
        <main class="app-content">
          <div class="filter-row">
            <div class="pills" data-app-radio-group role="tablist">
              <button type="button" class="pill" aria-selected="false">Today</button>
              <button type="button" class="pill" aria-selected="false">This Week</button>
              <button type="button" class="pill" aria-selected="true">30 Days</button>
              <button type="button" class="pill" aria-selected="false">90 Days</button>
            </div>
            <div class="filter-scope" data-app-radio-group>
              <button type="button" aria-selected="true"><span class="filter-scope__dot filter-scope__dot--primary"></span> All accounts</button>
              <button type="button" aria-selected="false"><span class="filter-scope__dot filter-scope__dot--muted"></span> Per platform</button>
            </div>
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
              <div class="metric-card__sub">Across networks</div>
            </div>
            <div class="metric-card">
              <div class="metric-card__label">
                Total audience
                <span class="metric-card__icons"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
              </div>
              <div class="metric-card__value">—</div>
              <div class="metric-card__sub">Followers &amp; subscribers</div>
            </div>
            <div class="metric-card">
              <div class="metric-card__label">
                Engagement rate
                <span class="metric-card__icons"><i class="fa-solid fa-heart" aria-hidden="true"></i></span>
              </div>
              <div class="metric-card__value">—</div>
              <div class="metric-card__sub">Connect accounts for analytics</div>
            </div>
            <div class="metric-card">
              <div class="metric-card__label">
                Posts published
                <span class="metric-card__icons"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></span>
              </div>
              <div class="metric-card__value">{{ $publishedPostsCount }}</div>
              <div class="metric-card__sub">All time</div>
            </div>
            <div class="metric-card">
              <div class="metric-card__label">
                Scheduled
                <span class="metric-card__icons"><i class="fa-solid fa-clock" aria-hidden="true"></i></span>
              </div>
              <div class="metric-card__value">{{ $scheduledPostsCount }}</div>
              <div class="metric-card__sub">Upcoming queue</div>
            </div>
          </div>

          <div class="grid-charts">
            <div class="card">
              <div class="card__head">
                <span>Audience &amp; reach</span>
                <div class="card__head-controls">
                  <select aria-label="Metric"><option>Reach</option><option>Impressions</option><option>Profile visits</option></select>
                  <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                </div>
              </div>
              <div class="empty-lg">
                <i class="fa-solid fa-chart-area" aria-hidden="true"></i>
                <strong>Connect live accounts</strong>
                <span>Historical charts populate when analytics sync is enabled.</span>
              </div>
            </div>
            <div class="card">
              <div class="card__head">
                <span><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Platform mix</span>
              </div>
              <div class="empty-lg">
                <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                <strong>Breakdown by network</strong>
                <span>See share of reach and engagement per channel on Insights.</span>
              </div>
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
              <div class="ai-disabled">Draft captions, adapt tone per platform, and review diffs in the composer.</div>
              <div class="ai-input">
                <input type="text" placeholder="Ask in Composer…" disabled aria-disabled="true" />
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
              </div>
            </div>
          </div>
        </main>
@endsection
