@extends('app')

@section('title', 'Calendar — ' . config('app.name'))
@section('page-id', 'calendar')

@php
    use Carbon\Carbon;

    $monthStart = $today->copy()->startOfMonth();
    $monthEnd   = $today->copy()->endOfMonth();
    $gridStart  = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
    $gridEnd    = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);

    $postsByDate = $scheduledPosts->groupBy(function ($post) {
        return $post->scheduled_at ? $post->scheduled_at->format('Y-m-d') : 'queue';
    });

    $platformClasses = collect(\App\Services\Platform\Platform::cases())->mapWithKeys(fn($p) => [
        $p->value => 'calendar-post-pill--' . substr($p->value, 0, 2),
    ])->toArray();
@endphp

@section('content')
        <main class="app-content app-content--calendar">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true">
                  <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                </div>
                <div>
                  <h1>Schedule</h1>
                  <p>Drag posts between days or from the queue. Times use your display timezone.</p>
                </div>
              </div>
            </div>
            <div class="head-actions">
              <button type="button" class="btn btn--primary" data-app-modal-open="modal-calendar-quick">
                <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                Quick add
              </button>
              <a class="btn btn--outline" href="{{ route('composer') }}">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                Composer
              </a>
            </div>
          </div>

          <div class="card card--app-section card--context-note">
            <div class="card__body card__body--dense">
              <p class="app-context-banner" role="note">
                <i class="fa-solid fa-globe" aria-hidden="true"></i>
                <span>Calendar grid uses <strong data-app-timezone-label>UTC</strong> for labels. Drag posts to reschedule them.</span>
              </p>
            </div>
          </div>

          <div class="card card--app-section calendar-shell">
            <div class="card__head">
              <span class="calendar-shell__head-title"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Month view</span>
            </div>
            <div class="card__body card__body--calendar">
          <div class="calendar-layout" data-app-calendar>
            <div class="calendar-month">
              <div class="calendar-month__head">
                <button type="button" class="btn btn--ghost" aria-label="Previous month"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                <span>{{ $currentMonth }}</span>
                <button type="button" class="btn btn--ghost" aria-label="Next month"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
              </div>
              <div class="calendar-dow" aria-hidden="true">
                <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
              </div>
              <div class="calendar-cells">
                @php $cursor = $gridStart->copy(); @endphp
                @while($cursor->lte($gridEnd))
                @php
                    $isMuted = $cursor->month !== $monthStart->month;
                    $dayKey = $cursor->format('Y-m-d');
                    $dayLabel = $cursor->format('M j, Y');
                    $dayPosts = $postsByDate->get($dayKey, collect());
                @endphp
                <div class="calendar-cell{{ $isMuted ? ' calendar-cell--muted' : '' }}" data-calendar-day="{{ $dayLabel }}">
                  <div class="calendar-cell__num">{{ $cursor->day }}</div>
                  @foreach($dayPosts as $post)
                  @php
                      $platform = is_array($post->platforms) ? ($post->platforms[0] ?? '') : '';
                      $pillClass = $platformClasses[$platform] ?? '';
                  @endphp
                  <div class="calendar-post-pill {{ $pillClass }}" data-post-id="{{ $post->id }}" draggable="true">
                    <span class="calendar-post-pill__when">{{ $post->scheduled_at ? $post->scheduled_at->format('H:i') : '—' }}</span>
                    {{ \Illuminate\Support\Str::limit($post->content, 30) }}
                  </div>
                  @endforeach
                </div>
                @php $cursor->addDay(); @endphp
                @endwhile
              </div>
            </div>

            <div class="calendar-queue">
              <div class="calendar-queue__head">Unscheduled</div>
              <div class="calendar-queue__list" data-calendar-day="Queue">
                @foreach($draftPosts as $post)
                @php
                    $platform = is_array($post->platforms) ? ($post->platforms[0] ?? '') : '';
                    $pillClass = $platformClasses[$platform] ?? '';
                @endphp
                <div class="calendar-post-pill {{ $pillClass }}" data-post-id="{{ $post->id }}" draggable="true">
                  <span class="calendar-post-pill__when">— draft</span>
                  {{ \Illuminate\Support\Str::limit($post->content, 30) }}
                </div>
                @endforeach
                @if($draftPosts->isEmpty())
                <p class="prose-muted">No unscheduled drafts. <a href="{{ route('composer') }}">Create a post</a>.</p>
                @endif
              </div>
            </div>
          </div>
            </div>
          </div>
        </main>
@endsection

@push('modals')
    <div class="app-modal app-modal--cool app-modal--calendar-quick" id="modal-calendar-quick" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-calendar-quick-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel">
        <div class="app-modal__head">
          <div>
            <h2 id="modal-calendar-quick-title">Add to calendar</h2>
            <p class="app-modal__lede">Create a draft, drop it on a day, or pull from the queue.</p>
          </div>
          <button type="button" class="icon-btn" data-app-modal-close aria-label="Close dialog">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
        <div class="app-modal__body">
          <p class="prose-muted">Drag posts between days or from <strong>Unscheduled</strong>. Moves are saved automatically.</p>
          @if($audienceInsights->sampleSize > 0)
          <div class="calendar-smart-hint">
            <strong><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Scheduling tip</strong>
            <p>{{ $audienceInsights->composerSummary }}</p>
            @if($audienceInsights->topHourSlots !== [])
            <p class="calendar-smart-hint__times">Favor these windows: {{ implode(', ', array_column($audienceInsights->topHourSlots, 'label')) }}</p>
            @endif
          </div>
          @endif
          <div class="modal-spark">
            <a class="btn btn--primary" href="{{ route('composer') }}"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> New post</a>
            <button type="button" class="btn btn--outline" data-app-modal-close>Stay on calendar</button>
          </div>
        </div>
      </div>
    </div>
@endpush

@push('scripts')
    @php
      $socialAppAsset = 'assets/js/social-app.js';
      $socialAppVersion = file_exists(public_path($socialAppAsset)) ? filemtime(public_path($socialAppAsset)) : time();
    @endphp
    <script src="{{ asset($socialAppAsset) }}?v={{ $socialAppVersion }}"></script>
@endpush
