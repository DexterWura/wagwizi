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
          <div class="app-topbar__lead">
            <span class="app-topbar__hello">Hello,</span>
            <strong class="app-topbar__name">{{ $currentUser->name ?? 'User' }}</strong>
          </div>
          <div class="app-topbar__spacer" aria-hidden="true"></div>
          <div class="app-topbar__cluster">
            <time class="app-topbar__date" data-app-topbar-date datetime=""></time>
            @if($currentUser && $currentUser->shouldShowUpgradePlan())
            <a class="btn btn--topbar-upgrade" href="{{ route('plans') }}">Upgrade plan</a>
            @endif
            @include('notifications-bell')
@include('timezone-topbar')
            <button type="button" class="app-theme-toggle" data-app-theme-toggle aria-label="Toggle theme">
              <i class="fa-solid fa-moon" data-app-theme-icon aria-hidden="true"></i>
              <span class="app-theme-toggle__label" data-app-theme-label>Dark</span>
            </button>
            <div class="app-topbar__account-wrap" data-app-account-wrap>
              <div class="app-topbar__account">
                <span class="app-topbar__avatar" aria-hidden="true">@include('user-avatar-img', ['user' => $currentUser, 'size' => 'sm'])</span>
                <button type="button" class="app-topbar__account-toggle" data-app-account-trigger id="app-topbar-account-trigger" aria-label="Account menu" aria-haspopup="menu" aria-expanded="false" aria-controls="app-topbar-account-menu">
                  <i class="fa-solid fa-chevron-down fa-xs app-topbar__account-chev" aria-hidden="true"></i>
                </button>
              </div>
              <nav class="app-topbar-account-menu" data-app-account-menu id="app-topbar-account-menu" role="menu" aria-labelledby="app-topbar-account-trigger" hidden>
                <a class="app-topbar-account-menu__link" role="menuitem" href="{{ route('profile') }}">
                  <i class="fa-solid fa-user fa-fw" aria-hidden="true"></i>
                  Manage profile
                </a>
                <a class="app-topbar-account-menu__link" role="menuitem" href="{{ route('settings') }}">
                  <i class="fa-solid fa-gear fa-fw" aria-hidden="true"></i>
                  Settings
                </a>
                <button type="button" class="app-topbar-account-menu__link" role="menuitem" data-app-logout>
                  <i class="fa-solid fa-right-from-bracket fa-fw" aria-hidden="true"></i>
                  Log out
                </button>
              </nav>
            </div>
          </div>
        </header>
@endsection

@section('content')
        <main class="app-content app-content--composer" data-app-composer-replies-allowed="{{ $composerRepliesAllowed ? '1' : '0' }}">
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
            <div class="composer-editing-banner" data-app-composer-editing-banner hidden role="status">
              <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
              <span data-app-composer-editing-label></span>
              <a class="btn btn--ghost btn--sm" href="{{ route('composer') }}">Start fresh</a>
            </div>
          </div>

          <div class="composer-create">
            <script type="application/json" id="composer-platform-profiles">@json($composerPlatformProfiles ?? [])</script>
            <div class="composer-create__main">
              <div class="card card--composer composer-form-card">
                <div class="composer-form-card__section">
                  <span class="composer-form-card__label">Post to</span>
                  @if($socialAccounts->isEmpty())
                  <p class="prose-muted"><a href="{{ route('accounts') }}">Connect at least one account</a> to start posting.</p>
                  @else
                  <div class="platform-checklist platform-checklist--inline">
                    @foreach($socialAccounts as $account)
                    @php
                      $plat = Platform::tryFrom($account->platform);
                      $chkAvatar = $account->composerPreviewAvatarUrl();
                      $chkTitle = trim((string) ($account->display_name ?? ''));
                      if ($chkTitle === '') { $chkTitle = trim((string) ($account->username ?? '')); }
                      if ($chkTitle === '') { $chkTitle = $plat?->label() ?? ucfirst($account->platform); }
                      $chkTitle .= ' — ' . ($plat?->label() ?? ucfirst($account->platform));
                      $chkIcon = $plat?->icon() ?? 'fa-solid fa-globe';
                    @endphp
                    <label class="platform-checklist__item" title="{{ e($chkTitle) }}">
                      <input type="checkbox" name="platform_accounts[]" value="{{ $account->id }}" data-platform="{{ $account->platform }}" data-social-account-id="{{ $account->id }}" checked />
                      <span class="platform-checklist__avatar-wrap" aria-hidden="true">
                      @if($chkAvatar)
                      <span class="platform-checklist__avatar">
                        <img src="{{ $chkAvatar }}" alt="" width="34" height="34" loading="lazy" decoding="async" referrerpolicy="no-referrer" />
                      </span>
                      @else
                      <span class="platform-checklist__avatar platform-checklist__avatar--fallback">
                        <i class="{{ $chkIcon }}"></i>
                      </span>
                      @endif
                        <span class="platform-checklist__platform-badge">
                          <i class="{{ $chkIcon }}"></i>
                        </span>
                      </span>
                      <span class="sr-only">{{ $chkTitle }}</span>
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
                      <button type="button" class="composer-pill composer-pill--icon" data-app-composer-emoji aria-label="Insert emoji" aria-expanded="false" aria-controls="composer-emoji-dock">
                        <i class="fa-regular fa-face-smile" aria-hidden="true"></i>
                      </button>
                    </div>
                    <div class="field__hint" data-app-composer-charlimit-summary aria-live="polite"></div>
                    <ul class="preview-card__constraints" data-app-composer-charlimit-list hidden aria-live="polite"></ul>
                    <div class="composer-emoji-dock composer-ai-dock" id="composer-emoji-dock" data-app-composer-emoji-dock hidden aria-hidden="true" role="dialog" aria-modal="false" aria-label="Emoji picker">
                      <div class="composer-emoji-dock__panel card card--composer">
                        <div class="composer-assistant-card__head composer-assistant-card__head--row">
                          <div class="composer-assistant-card__head-text">
                            <span class="composer-assistant-card__title">Emoji picker</span>
                            <span class="composer-assistant-card__hint">Click an emoji to insert it into your draft.</span>
                          </div>
                          <button type="button" class="icon-btn composer-ai-dock__close" data-app-composer-emoji-close aria-label="Close emoji picker">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                          </button>
                        </div>
                        <div class="composer-emoji-picker" data-app-composer-emoji-picker aria-label="Emoji options">
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="😀" aria-label="Grinning face">😀</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="😂" aria-label="Face with tears of joy">😂</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="😍" aria-label="Smiling face with heart eyes">😍</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="🔥" aria-label="Fire">🔥</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="🚀" aria-label="Rocket">🚀</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="🎉" aria-label="Party popper">🎉</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="💯" aria-label="Hundred points">💯</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="👍" aria-label="Thumbs up">👍</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="🙏" aria-label="Folded hands">🙏</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="🙌" aria-label="Raising hands">🙌</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="✅" aria-label="Check mark">✅</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="✨" aria-label="Sparkles">✨</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="📈" aria-label="Chart increasing">📈</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="📣" aria-label="Megaphone">📣</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="💡" aria-label="Light bulb">💡</button>
                          <button type="button" class="composer-emoji-picker__item" data-composer-emoji="❤️" aria-label="Red heart">❤️</button>
                        </div>
                      </div>
                    </div>
                    <div class="composer-ai-dock" id="composer-ai-dock" data-app-composer-ai-dock data-composer-ai-locked="{{ $composerAiLocked ? '1' : '0' }}" hidden aria-hidden="true" role="dialog" aria-modal="false" aria-label="Writing assistant">
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
                        <div class="composer-ai-dock__stack @if($composerAiLocked) composer-ai-dock__stack--locked @endif">
                          <div class="composer-ai-dock__stack-inner" aria-hidden="{{ $composerAiLocked ? 'true' : 'false' }}">
                            <div class="ai-chat-panel__messages" id="composer-ai-messages">
                              <div class="ai-msg ai-msg--assistant">Ask for a shorter hook, a LinkedIn tone, or hashtags. Suggested edits appear in the feed preview.</div>
                            </div>
                            <form class="ai-chat-panel__input" data-app-composer-ai>
                              <input class="input" id="composer-ai-input" type="text" name="composer-ai-prompt" autocomplete="off" placeholder="e.g. Shorten for X with one CTA…" @if($composerAiLocked) disabled @endif />
                              <button type="submit" class="btn btn--primary" @if($composerAiLocked) disabled @endif>Send</button>
                            </form>
                          </div>
                          @if($composerAiLocked)
                          <div class="composer-ai-paywall" role="region" aria-label="How to unlock AI Assist">
                            <div class="composer-ai-paywall__card">
                              <span class="composer-ai-paywall__badge" aria-hidden="true"><i class="fa-solid fa-wand-magic-sparkles"></i> AI</span>
                              @if(!empty($composerAiQuotaExhausted))
                              <p class="composer-ai-paywall__title">Platform AI limit reached</p>
                              <p class="composer-ai-paywall__text">You have used all platform AI credits for this billing period. Wait until your plan renews to get a fresh allowance, or add your own API key under Settings → AI (any plan).</p>
                              @elseif(!empty($composerAiPlanNoPlatformAi))
                              <p class="composer-ai-paywall__title">No platform AI on this plan</p>
                              <p class="composer-ai-paywall__text">Your current plan does not include platform AI credits. Add your own API key under Settings → AI, or upgrade to a plan that includes credits.</p>
                              @else
                              <p class="composer-ai-paywall__title">Unlock AI Assist</p>
                              <p class="composer-ai-paywall__text">Use platform AI with a paid plan that includes credits, or add your own API key in Settings (any plan, billed by your provider).</p>
                              @endif
                              <div class="composer-ai-paywall__actions">
                                <a class="btn btn--primary" href="{{ route('settings') }}">AI settings</a>
                                <a class="btn btn--outline" href="{{ route('plans') }}">View plans</a>
                              </div>
                            </div>
                          </div>
                          @endif
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="composer-form-card__section composer-form-card__section--tight">
                  <div class="composer-tailor-cue" role="note" aria-label="Per-network tailoring hint">
                    <span class="composer-tailor-cue__title">Tailor per network</span>
                  </div>
                  <div class="pill-row" data-app-tabs role="tablist" aria-label="Edit target">
                    <button type="button" class="pill pill--account-tab" data-app-platform-tab="master" data-override-label="Master" aria-selected="true">Master</button>
                    @foreach($socialAccounts as $account)
                    @php
                      $plat = Platform::tryFrom($account->platform);
                      $pillAvatar = $account->composerPreviewAvatarUrl();
                      $pillIcon = $plat?->icon() ?? 'fa-solid fa-globe';
                      $pillName = trim((string) ($account->display_name ?? ''));
                      if ($pillName === '') { $pillName = trim((string) ($account->username ?? '')); }
                      $pillPlat = $plat?->label() ?? ucfirst($account->platform);
                      $pillTitle = $pillName !== '' ? $pillName.' — '.$pillPlat : $pillPlat;
                      $pillOverrideLabel = $pillName !== '' ? $pillName.' ('.$pillPlat.')' : $pillPlat;
                    @endphp
                    <button
                      type="button"
                      class="pill pill--account-tab"
                      data-app-platform-tab="{{ $account->id }}"
                      data-platform="{{ $account->platform }}"
                      data-social-account-id="{{ $account->id }}"
                      data-override-label="{{ e($pillOverrideLabel) }}"
                      title="{{ e($pillTitle) }}"
                      aria-selected="false"
                    >
                      @if($pillAvatar)
                      <span class="pill__avatar-wrap" aria-hidden="true">
                        <span class="pill__avatar-core">
                          <img class="pill__avatar" src="{{ $pillAvatar }}" alt="" width="20" height="20" loading="lazy" decoding="async" referrerpolicy="no-referrer" />
                        </span>
                        <span class="pill__platform-badge">
                          <i class="{{ $pillIcon }}"></i>
                        </span>
                      </span>
                      @else
                      <span class="pill__avatar-wrap pill__avatar-wrap--fallback" aria-hidden="true">
                        <span class="pill__avatar-core">
                          <i class="fa-solid fa-user"></i>
                        </span>
                        <span class="pill__platform-badge">
                          <i class="{{ $pillIcon }}"></i>
                        </span>
                      </span>
                      @endif
                      <span class="sr-only">{{ e($pillTitle) }}</span>
                    </button>
                    @endforeach
                  </div>
                  <div class="field field--flush" data-app-composer-override-settings hidden>
                    <label class="field__label" for="composer-override">Active selection</label>
                    <div class="composer-override-wrap">
                      <textarea class="textarea" id="composer-override" rows="4" placeholder="Overrides for the selected tab…" autocomplete="off"></textarea>
                      <ul class="composer-mention-suggestions" data-app-composer-mention-list hidden role="listbox" aria-label="Mention suggestions"></ul>
                    </div>
                    <p class="field__hint">Overrides are saved per platform and sent when publishing/scheduling. On a platform tab (not Master), type <strong>@</strong> to search that network and pick a username.</p>
                  </div>
                </div>

                <div class="composer-form-card__section composer-form-card__section--tight">
                  @if($composerRepliesAllowed)
                  <button type="button" class="btn btn--ghost btn--sm" data-app-composer-toggle-comment aria-expanded="false" aria-controls="composer-comment-settings">
                    Add first comment
                  </button>
                  <div id="composer-comment-settings" data-app-composer-comment-settings hidden>
                    <div class="field field--flush">
                      <label class="field__label" for="composer-first-comment">First comment <span class="prose-muted">(optional)</span></label>
                      <textarea class="textarea" id="composer-first-comment" rows="3" placeholder="Add a follow-up comment to post after publish…"></textarea>
                      <p class="field__hint">If platform comment publishing is supported, this comment will be posted automatically.</p>
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
                  @else
                  <p class="field__hint" data-app-composer-replies-locked>
                    <strong>First comments / replies</strong> are not included in your current plan.
                    @if($currentUser && $currentUser->shouldShowUpgradePlan())
                    <a href="{{ route('plans') }}">Upgrade</a> to unlock.
                    @else
                    Choose a plan that includes replies to use this feature.
                    @endif
                  </p>
                  @endif
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
                    <button type="button" class="composer-upload-zone" data-app-composer-upload aria-label="Upload or add media files">
                      <i class="fa-solid fa-plus" aria-hidden="true"></i>
                      <span class="composer-upload-zone__text">Add media</span>
                      <span class="composer-upload-zone__meta">Supports multiple files</span>
                    </button>
                    <input type="file" id="composer-media-input" accept="image/*,video/*" multiple hidden />
                  </div>
                </div>
                <p class="field__hint composer-media-hint">
                  <a href="{{ route('media-library') }}"><i class="fa-solid fa-photo-film" aria-hidden="true"></i> Media library — uploads &amp; admin premium assets</a>
                </p>
                <p class="field__hint" data-app-composer-media-selected>No media selected.</p>
                <div class="composer-selected-media" data-app-composer-media-list-wrap hidden>
                  <div class="composer-selected-media__head">
                    <span class="composer-selected-media__title">Selected media</span>
                    <button type="button" class="btn btn--ghost btn--sm composer-selected-media__clear" data-app-composer-clear-media hidden>Clear all</button>
                  </div>
                  <div class="composer-selected-media__grid" data-app-composer-media-list></div>
                </div>

                <div class="field">
                  <label class="field__label" for="composer-audience">Who can see this</label>
                  <select class="select" id="composer-audience" name="audience">
                    <option value="everyone" selected>Everyone</option>
                    <option value="followers">Followers</option>
                    <option value="connections">Connections only</option>
                    <option value="private">Only me</option>
                  </select>
                </div>

                <div class="composer-form-footer">
                  <div class="composer-form-footer__schedule" data-app-composer-schedule-settings hidden>
                    <div class="composer-schedule-field">
                      <label class="field__label" for="composer-date">Schedule date</label>
                      <input class="input" type="date" id="composer-date" name="schedule-date" />
                    </div>
                    <div class="composer-schedule-field">
                      <label class="field__label" for="composer-time">Time</label>
                      <input class="input" type="time" id="composer-time" name="schedule-time" />
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
                    <button type="button" class="btn btn--outline" data-app-composer-action="publish">Post now</button>
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
                    <div
                      class="composer-feed-preview__head"
                      data-app-composer-feed-head
                      data-feed-user-name="{{ e($currentUser->name ?? 'User') }}"
                      data-feed-user-avatar="{{ e($currentUser->avatarUrl(80)) }}"
                    >
                      <span class="composer-feed-preview__avatar" aria-hidden="true">
                        <img
                          class="composer-feed-preview__avatar-img app-user-avatar app-user-avatar--md"
                          data-app-composer-feed-avatar
                          data-app-user-avatar="1"
                          src="{{ $currentUser->avatarUrl(80) }}"
                          alt=""
                          width="40"
                          height="40"
                          loading="lazy"
                          decoding="async"
                          referrerpolicy="no-referrer"
                        />
                      </span>
                      <div class="composer-feed-preview__meta">
                        <strong data-app-composer-feed-display-name>{{ $currentUser->name ?? 'User' }}</strong>
                        <span class="composer-feed-preview__when">Just now</span>
                      </div>
                    </div>
                    <div class="composer-feed-preview__body">
                      <div class="composer-feed-preview__media" data-app-composer-feed-media hidden></div>
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
                  @foreach($socialAccounts as $account)
                  @php
                    $plat = Platform::tryFrom($account->platform);
                    $pvName = trim((string) ($account->display_name ?? ''));
                    if ($pvName === '') { $pvName = trim((string) ($account->username ?? '')); }
                    $pvSub = $plat?->label() ?? ucfirst($account->platform);
                  @endphp
                  <div class="preview-card" data-preview-social-account-id="{{ $account->id }}">
                    <div class="preview-card__bar">
                      @if($url = $account->composerPreviewAvatarUrl())
                      <img
                        class="preview-card__bar-avatar preview-card__bar-avatar--photo"
                        src="{{ $url }}"
                        alt=""
                        width="22"
                        height="22"
                        loading="lazy"
                        decoding="async"
                        referrerpolicy="no-referrer"
                      />
                      @else
                      <span class="preview-card__bar-avatar preview-card__bar-avatar--fallback" aria-hidden="true">
                        <i class="{{ $plat?->icon() ?? 'fa-solid fa-globe' }}"></i>
                      </span>
                      @endif
                      <span class="preview-card__bar-label">
                        @if($pvName !== '')
                        <span class="preview-card__bar-name">{{ $pvName }}</span>
                        <span class="preview-card__bar-platform prose-muted">{{ $pvSub }}</span>
                        @else
                        {{ $pvSub }}
                        @endif
                      </span>
                    </div>
                    <div class="preview-card__body" data-app-composer-preview data-platform="{{ $account->platform }}" data-social-account-id="{{ $account->id }}">
                      <div class="composer-preview-card__media" data-app-composer-preview-media hidden></div>
                      <div class="composer-preview-card__text" data-app-composer-preview-text>Your post will appear here.</div>
                      <ul class="preview-card__constraints" data-app-composer-platform-warnings="{{ $account->platform }}" hidden></ul>
                    </div>
                  </div>
                  @endforeach
                </div>
              </div>

              <div class="card card--composer composer-media-constraints-alert" data-app-composer-media-alert hidden role="region" aria-label="Media size checks">
                <div class="card__head composer-media-constraints-alert__head">
                  <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                  Media may not fit every destination
                </div>
                <div class="card__body composer-media-constraints-alert__body">
                  <p class="composer-media-constraints-alert__intro" data-app-composer-media-alert-intro></p>
                  <ul class="composer-media-constraints-alert__list" data-app-composer-media-alert-list></ul>
                </div>
              </div>
            </aside>
          </div>
        </main>
@endsection

@push('modals')
    <div class="app-modal app-modal--cool app-modal--composer-media" id="modal-composer-media" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-composer-media-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel app-modal__panel--composer-media">
        <div class="composer-media-modal" data-composer-media-step="source">
          <h2 id="modal-composer-media-title" class="composer-media-modal__title">Add media</h2>
          <p class="composer-media-modal__hint" data-composer-media-type-hint>Choose where to get your file from.</p>
          <div class="composer-media-modal__actions">
            <button type="button" class="btn btn--primary composer-media-modal__choice" data-composer-media-from-device>
              <i class="fa-solid fa-upload" aria-hidden="true"></i>
              From device
            </button>
            <button type="button" class="btn btn--outline composer-media-modal__choice" data-composer-media-from-library>
              <i class="fa-solid fa-photo-film" aria-hidden="true"></i>
              From library
            </button>
          </div>
          <button type="button" class="btn btn--ghost composer-media-modal__cancel" data-app-modal-close>Cancel</button>
        </div>
        <div class="composer-media-modal composer-media-modal--library" data-composer-media-step="library" hidden>
          <div class="composer-media-modal__library-head">
            <button type="button" class="btn btn--ghost composer-media-modal__back" data-composer-media-back>
              <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
              Back
            </button>
            <span class="composer-media-modal__library-title">Your library</span>
          </div>
          <p class="composer-media-modal__loading" data-composer-media-library-loading hidden>Loading…</p>
          <div class="composer-media-picker__grid" data-composer-media-grid role="listbox" aria-label="Media in your library"></div>
          <p class="composer-media-modal__empty" data-composer-media-empty hidden>Nothing to show here.</p>
        </div>
      </div>
    </div>
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
          <button type="button" class="btn btn--danger" data-feedback-retry hidden>Retry</button>
          <button type="button" class="btn btn--primary" data-feedback-got-it data-app-modal-close>Got it</button>
        </div>
      </div>
    </div>
@endpush

@push('scripts')
    <script>
      window.__composerMediaCounts = @json($composerMediaCounts ?? ['image' => 0, 'video' => 0]);
      window.__composerPlatformMediaCaps = @json($composerPlatformMediaCaps ?? []);
      window.__composerPlatformMediaRules = @json($composerPlatformMediaRules ?? []);
      window.__composerPlatformLabels = @json($composerPlatformLabels ?? []);
    </script>
    @php
      $socialAppAsset = 'assets/js/social-app.js';
      $socialAppVersion = file_exists(public_path($socialAppAsset)) ? filemtime(public_path($socialAppAsset)) : time();
    @endphp
    <script src="{{ asset($socialAppAsset) }}?v={{ $socialAppVersion }}"></script>
@endpush
