@extends('app')

@section('title', 'Create post — ' . config('app.name'))
@section('page-id', 'composer')

@php
    use App\Services\Platform\Platform;
@endphp

@section('topbar')
        <header class="app-topbar app-topbar--composer" role="banner" aria-label="Page toolbar">
          <button type="button" class="menu-btn" data-app-drawer-open aria-label="Open menu">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
          </button>
          <div class="composer-topbar__lead">
            <span class="composer-topbar__hello">Hello,</span>
            <strong class="composer-topbar__name">{{ $currentUser->name ?? 'User' }}</strong>
          </div>
          <div class="composer-topbar__spacer" aria-hidden="true"></div>
          <div class="app-topbar__cluster">
            <time class="app-topbar__date" data-app-topbar-date datetime=""></time>
            <a class="btn btn--topbar-upgrade" href="{{ route('plans') }}">Upgrade plan</a>
            <button type="button" class="icon-btn app-topbar__notif" data-app-modal-open="modal-notifications" aria-label="Notifications">
              <i class="fa-solid fa-bell" aria-hidden="true"></i>
            </button>
            <div class="app-topbar__timezone" data-app-timezone-wrap>
              <button type="button" class="timezone-trigger" data-app-timezone-trigger aria-haspopup="listbox" aria-expanded="false" aria-label="Choose display timezone for schedules and reports">
                <i class="fa-solid fa-globe" aria-hidden="true"></i>
                <span data-app-timezone-label>UTC</span>
                <i class="fa-solid fa-chevron-down fa-xs timezone-trigger__chev" aria-hidden="true"></i>
              </button>
              <ul class="timezone-menu" data-app-timezone-menu role="listbox" hidden>
                <li role="presentation">
                  <button type="button" class="timezone-menu__option" role="option" data-app-timezone-option data-value="utc" aria-selected="true">
                    <span class="timezone-menu__abbr">UTC</span>
                    <span class="timezone-menu__label">Coordinated Universal Time</span>
                  </button>
                </li>
                <li role="presentation">
                  <button type="button" class="timezone-menu__option" role="option" data-app-timezone-option data-value="est" aria-selected="false">
                    <span class="timezone-menu__abbr">EST</span>
                    <span class="timezone-menu__label">Eastern (US)</span>
                  </button>
                </li>
                <li role="presentation">
                  <button type="button" class="timezone-menu__option" role="option" data-app-timezone-option data-value="cet" aria-selected="false">
                    <span class="timezone-menu__abbr">CET</span>
                    <span class="timezone-menu__label">Central European</span>
                  </button>
                </li>
              </ul>
            </div>
            <button type="button" class="app-theme-toggle" data-app-theme-toggle aria-label="Toggle theme">
              <i class="fa-solid fa-moon" data-app-theme-icon aria-hidden="true"></i>
              <span class="app-theme-toggle__label" data-app-theme-label>Dark</span>
            </button>
            <button type="button" class="app-topbar__account" aria-label="Account menu">
              <span class="app-topbar__avatar" aria-hidden="true"></span>
              <i class="fa-solid fa-chevron-down fa-xs" aria-hidden="true"></i>
            </button>
          </div>
        </header>
@endsection

@section('content')
        <main class="app-content app-content--composer">
          <div class="page-head page-head--composer">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon page-icon--composer" aria-hidden="true">
                  <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                </div>
                <div>
                  <h1>Create post</h1>
                  <p>Draft once, tailor per network, preview the feed, and refine with the assistant.</p>
                </div>
              </div>
            </div>
          </div>

          <div class="composer-create">
            <div class="composer-create__main">
              <div class="card card--composer composer-form-card">
                <div class="composer-form-card__section">
                  <span class="composer-form-card__label">Post to</span>
                  @if($socialAccounts->isEmpty())
                  <p class="prose-muted"><a href="{{ route('accounts') }}">Connect at least one account</a> to start posting.</p>
                  @else
                  <div class="platform-checklist platform-checklist--inline">
                    @foreach($socialAccounts as $account)
                    @php $plat = Platform::tryFrom($account->platform); @endphp
                    <label>
                      <input type="checkbox" name="platform_accounts[]" value="{{ $account->id }}" data-platform="{{ $account->platform }}" checked />
                      <i class="{{ $plat?->icon() ?? 'fa-solid fa-globe' }}" aria-hidden="true"></i>
                      {{ $plat?->label() ?? ucfirst($account->platform) }}
                      @if($account->username)<span class="prose-muted">({{ $account->username }})</span>@endif
                    </label>
                    @endforeach
                  </div>
                  @endif
                </div>

                <div class="composer-form-card__section composer-draft-anchor">
                  <label class="field__label sr-only" for="composer-master">Post text</label>
                  <div class="composer-draft-stack">
                    <textarea class="textarea textarea--composer" id="composer-master" rows="9" placeholder="Write something here…"></textarea>
                    <div class="composer-toolbar">
                      <button type="button" class="composer-pill" data-app-composer-hashtag aria-label="Insert hashtag">
                        <i class="fa-solid fa-hashtag" aria-hidden="true"></i>
                        Hashtags
                      </button>
                      <button type="button" class="composer-pill" id="composer-ai-toggle" data-app-composer-ai-focus aria-expanded="false" aria-controls="composer-ai-dock">
                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        AI Assist
                      </button>
                      <button type="button" class="composer-pill composer-pill--icon" data-app-composer-emoji aria-label="Insert emoji">
                        <i class="fa-regular fa-face-smile" aria-hidden="true"></i>
                      </button>
                    </div>
                    <div class="composer-ai-dock" id="composer-ai-dock" data-app-composer-ai-dock hidden aria-hidden="true" role="dialog" aria-modal="false" aria-label="Writing assistant">
                      <div class="composer-ai-dock__panel card card--composer composer-assistant-card ai-chat-panel">
                        <div class="composer-assistant-card__head composer-assistant-card__head--row">
                          <div class="composer-assistant-card__head-text">
                            <span class="composer-assistant-card__title">Assistant</span>
                            <span class="composer-assistant-card__hint">Refine copy for the active draft. <span data-app-composer-ai-source-hint></span></span>
                          </div>
                          <button type="button" class="icon-btn composer-ai-dock__close" data-app-composer-ai-close aria-label="Close assistant">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                          </button>
                        </div>
                        <div class="ai-chat-panel__messages" id="composer-ai-messages">
                          <div class="ai-msg ai-msg--assistant">Ask for a shorter hook, a LinkedIn tone, or hashtags. Suggested edits appear in the feed preview.</div>
                        </div>
                        <form class="ai-chat-panel__input" data-app-composer-ai>
                          <input class="input" id="composer-ai-input" type="text" name="composer-ai-prompt" autocomplete="off" placeholder="e.g. Shorten for X with one CTA…" />
                          <button type="submit" class="btn btn--primary">Send</button>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="composer-form-card__section composer-form-card__section--tight">
                  <div class="pill-row" data-app-tabs role="tablist" aria-label="Edit target">
                    <button type="button" class="pill" data-app-platform-tab="master" aria-selected="true">Master</button>
                    @foreach($socialAccounts->unique('platform') as $account)
                    @php $plat = Platform::tryFrom($account->platform); @endphp
                    <button type="button" class="pill" data-app-platform-tab="{{ $account->platform }}" aria-selected="false">{{ $plat?->label() ?? ucfirst($account->platform) }}</button>
                    @endforeach
                  </div>
                  <div class="field field--flush">
                    <label class="field__label" for="composer-override">Active selection</label>
                    <textarea class="textarea" id="composer-override" rows="4" placeholder="Overrides for the selected tab…"></textarea>
                    <p class="field__hint">Overrides are saved per platform and sent when publishing/scheduling.</p>
                  </div>
                </div>

                <div class="composer-form-card__section composer-form-card__section--tight">
                  <div class="field field--flush">
                    <label class="field__label" for="composer-first-comment">First comment <span class="prose-muted">(optional)</span></label>
                    <textarea class="textarea" id="composer-first-comment" rows="3" placeholder="Add a follow-up comment to post after publish…"></textarea>
                    <p class="field__hint">If platform comment publishing is supported, this comment will be posted automatically.</p>
                  </div>
                </div>

                <div class="composer-form-card__row">
                  <div class="field field--half">
                    <label class="field__label" for="composer-media-type">Media type</label>
                    <select class="select" id="composer-media-type" name="media-type">
                      <option value="image" selected>Image</option>
                      <option value="video">Video</option>
                      <option value="carousel">Carousel</option>
                      <option value="none">Text only</option>
                    </select>
                  </div>
                  <div class="field field--half composer-upload-field">
                    <span class="field__label">Add media</span>
                    <button type="button" class="composer-upload-zone" data-app-composer-upload aria-label="Upload or add media">
                      <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    </button>
                    <input type="file" id="composer-media-input" accept="image/*,video/*" hidden />
                  </div>
                </div>
                <p class="field__hint composer-media-hint">
                  <a href="{{ route('media-library') }}"><i class="fa-solid fa-photo-film" aria-hidden="true"></i> Media library — uploads &amp; admin premium assets</a>
                </p>

                <div class="field">
                  <label class="field__label" for="composer-audience">Who can see this</label>
                  <select class="select" id="composer-audience" name="audience">
                    <option value="everyone" selected>Everyone</option>
                    <option value="followers">Followers</option>
                    <option value="connections">Connections only</option>
                    <option value="private">Only me</option>
                  </select>
                </div>

                <fieldset class="composer-post-mode">
                  <legend class="composer-post-mode__legend">How would you like to post?</legend>
                  <label class="composer-post-mode__opt">
                    <input type="radio" name="composer-post-mode" value="auto" checked />
                    Post automatically
                  </label>
                  <label class="composer-post-mode__opt">
                    <input type="radio" name="composer-post-mode" value="reminder" />
                    Get a reminder
                  </label>
                </fieldset>

                <div class="composer-form-footer">
                  <div class="composer-form-footer__schedule">
                    <div class="composer-schedule-field">
                      <label class="field__label" for="composer-date">Schedule date</label>
                      <input class="input" type="date" id="composer-date" name="schedule-date" />
                    </div>
                    <div class="composer-schedule-field">
                      <label class="field__label" for="composer-time">Time</label>
                      <input class="input" type="time" id="composer-time" name="schedule-time" />
                    </div>
                    <div class="composer-schedule-field">
                      <label class="field__label" for="composer-delay-value">Delay post by</label>
                      <div class="composer-delay-row">
                        <input class="input" type="number" id="composer-delay-value" min="1" placeholder="e.g. 30" />
                        <select class="select" id="composer-delay-unit">
                          <option value="minutes">Minutes</option>
                          <option value="hours">Hours</option>
                        </select>
                      </div>
                    </div>
                    <div class="composer-schedule-field">
                      <label class="field__label" for="composer-comment-delay-value">Delay comment by</label>
                      <div class="composer-delay-row">
                        <input class="input" type="number" id="composer-comment-delay-value" min="1" placeholder="e.g. 15" />
                        <select class="select" id="composer-comment-delay-unit">
                          <option value="minutes">Minutes</option>
                          <option value="hours">Hours</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="composer-audience-hint" data-app-audience-hint>
                    <div class="composer-audience-hint__icon" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></div>
                    <div class="composer-audience-hint__body">
                      <strong>Smart schedule</strong>
                      <p>{{ $audienceInsights->composerSummary }}</p>
                      @if($audienceInsights->topHourSlots !== [])
                      <div class="composer-audience-hint__chips">
                        @foreach($audienceInsights->topHourSlots as $slot)
                        <button type="button" class="composer-slot-chip" data-app-apply-slot="{{ $slot['hour'] }}" title="Set schedule time near this hour">{{ $slot['label'] }}</button>
                        @endforeach
                      </div>
                      @endif
                    </div>
                  </div>
                  <div class="composer-form-footer__actions">
                    <button type="button" class="btn btn--ghost" data-app-composer-action="draft">Draft</button>
                    <button type="button" class="btn btn--outline" data-app-composer-action="publish">Publish</button>
                    <button type="button" class="btn btn--composer-schedule" data-app-composer-action="schedule">Schedule</button>
                  </div>
                </div>
              </div>
            </div>

            <aside class="composer-create__aside" aria-label="Post preview">
              <div class="card card--composer composer-feed-card">
                <div class="composer-feed-card__label">Feed preview</div>
                <div class="composer-feed-preview">
                  <div class="composer-feed-preview__post">
                    <div class="composer-feed-preview__head">
                      <span class="composer-feed-preview__avatar" aria-hidden="true"></span>
                      <div class="composer-feed-preview__meta">
                        <strong>{{ $currentUser->name ?? 'User' }}</strong>
                        <span class="composer-feed-preview__when">Just now</span>
                      </div>
                    </div>
                    <div class="composer-feed-preview__body">
                      <div class="composer-feed-preview__live" data-app-composer-feed-live>Your post will appear here.</div>
                      <div class="composer-feed-preview__agent-diff" data-app-composer-feed-diff hidden aria-live="polite">
                        <span class="composer-inline-diff">
                          <del class="diff-inline-del"></del>
                          <ins class="diff-inline-ins"></ins>
                        </span>
                      </div>
                    </div>
                    <div class="composer-feed-preview__bar" aria-hidden="true">
                      <span><i class="fa-regular fa-heart"></i></span>
                      <span><i class="fa-regular fa-comment"></i></span>
                      <span><i class="fa-regular fa-bookmark"></i></span>
                      <span><i class="fa-solid fa-share-nodes"></i></span>
                    </div>
                    <div class="composer-feed-preview__comment" aria-hidden="true">
                      <span class="composer-feed-preview__comment-ph">Write a comment…</span>
                      <i class="fa-solid fa-paper-plane"></i>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card card--composer composer-channel-card">
                <div class="card__head card__head--composer-channel">
                  <span>Channel previews</span>
                  <span class="composer-channel-card__sub">Read-only mockups</span>
                </div>
                <div class="preview-strip preview-strip--scroll">
                  @foreach($socialAccounts->unique('platform') as $account)
                  @php $plat = Platform::tryFrom($account->platform); @endphp
                  <div class="preview-card">
                    <div class="preview-card__bar"><i class="{{ $plat?->icon() ?? 'fa-solid fa-globe' }}" aria-hidden="true"></i> {{ $plat?->label() ?? ucfirst($account->platform) }}</div>
                    <div class="preview-card__body" data-app-composer-preview>Your post will appear here.</div>
                  </div>
                  @endforeach
                </div>
              </div>
            </aside>
          </div>
        </main>
@endsection

@push('modals')
    <div class="app-modal app-modal--cool app-modal--composer-feedback" id="modal-composer-feedback" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-feedback-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel">
        <div class="modal-success-hero">
          <span class="modal-success-hero__icon" aria-hidden="true"><i class="fa-solid fa-floppy-disk" data-feedback-icon></i></span>
          <h2 id="modal-feedback-title" data-feedback-title>Draft saved</h2>
          <p class="app-modal__lede" data-feedback-desc>Your copy is stored for this session.</p>
        </div>
        <div class="app-modal__foot">
          <a class="btn btn--composer-schedule" href="{{ route('calendar') }}" data-feedback-calendar-link hidden>Open calendar</a>
          <button type="button" class="btn btn--primary" data-app-modal-close>Got it</button>
        </div>
      </div>
    </div>
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/social-app.js') }}"></script>
@endpush
