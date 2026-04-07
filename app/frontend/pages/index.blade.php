<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    @include('seo-meta', [
      'seoCanonicalOverride' => route('landing'),
      'seoTypeOverride' => 'website',
      'seoRobotsOverride' => 'index,follow',
    ])
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    @php
      $lpFontCss = 'https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap';
      $lpFaCss = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css';
    @endphp
    <link rel="preload" href="{{ $lpFontCss }}" as="style" />
    <link href="{{ $lpFontCss }}" rel="stylesheet" media="print" onload="this.media='all'" />
    <noscript><link href="{{ $lpFontCss }}" rel="stylesheet" /></noscript>
    <link rel="preload" href="{{ $lpFaCss }}" as="style" crossorigin="anonymous" />
    <link href="{{ $lpFaCss }}" rel="stylesheet" media="print" onload="this.media='all'" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <noscript><link href="{{ $lpFaCss }}" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer" /></noscript>
    <link rel="stylesheet" href="{{ asset('assets/css/landing.css') }}" />
  </head>
  <body class="lp">
    <header id="lp-header">
      <div class="lp-header__inner">
        <a class="lp-logo" href="{{ route('landing') }}" aria-label="{{ config('app.name') }} home">
          @include('brand-logo')
        </a>
        <nav class="lp-nav" aria-label="Primary">
          <a href="#product">Product</a>
          <a href="#resources">Resources</a>
          <a href="#pricing">Pricing</a>
        </nav>
        <div class="lp-header__actions">
          @auth
            <a class="lp-btn lp-btn--primary" href="{{ route('dashboard') }}">Dashboard</a>
          @else
            <a class="lp-btn lp-btn--ghost" href="{{ route('login') }}">Sign in</a>
            <a class="lp-btn lp-btn--primary" href="{{ route('signup') }}">Get started</a>
          @endauth
          <button type="button" class="lp-nav-toggle" data-lp-nav-toggle aria-expanded="false" aria-controls="lp-nav-panel" aria-label="Open menu">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
          </button>
        </div>
      </div>
      <div id="lp-nav-panel" role="dialog" aria-label="Mobile menu">
        <a href="#product">Product</a>
        <a href="#resources">Resources</a>
        <a href="#pricing">Pricing</a>
        @auth
          <a href="{{ route('dashboard') }}">Dashboard</a>
        @else
          <a href="{{ route('login') }}">Sign in</a>
          <a href="{{ route('signup') }}">Get started</a>
        @endauth
      </div>
    </header>

    <main>
      <section class="lp-hero" id="top">
        <div class="lp-hero__bg" aria-hidden="true"></div>
        <div class="lp-hero__inner">
          <p class="lp-hero__eyebrow" data-lp-reveal><i class="fa-solid fa-sparkles" aria-hidden="true"></i> {{ $heroEyebrow }}</p>
          <h1 data-lp-reveal>{{ $heroHeading }}</h1>
          <p class="lp-hero__sub" data-lp-reveal>{{ $heroSubheading }}</p>
          <div class="lp-hero__icons" data-lp-reveal>
            @foreach($enabledPlatforms as $platform)
            <span class="lp-float"><i class="{{ $platform->icon() }}" aria-hidden="true"></i></span>
            @endforeach
          </div>
          <div class="lp-hero__cta" data-lp-reveal>
            @auth
            <a class="lp-btn lp-btn--primary lp-btn--lg" href="{{ route('composer') }}">Create a post</a>
            @else
            <a class="lp-btn lp-btn--primary lp-btn--lg" href="{{ route('signup') }}">Start free trial</a>
            @endauth
          </div>

          @php
            $lpMockHost = parse_url(config('app.url'), PHP_URL_HOST);
            if (!is_string($lpMockHost) || $lpMockHost === '') {
              $lpMockHost = 'app';
            }
            $lpMockPlatforms = collect($enabledPlatforms)->take(4);
          @endphp
          <div class="lp-mockup" data-lp-mockup data-lp-reveal>
            <div class="lp-mockup__glow" aria-hidden="true"></div>
            <div class="lp-mockup__tilt">
              <div class="lp-mockup__chrome">
                <span class="lp-mockup__dot"></span>
                <span class="lp-mockup__dot"></span>
                <span class="lp-mockup__dot"></span>
                <span class="lp-mockup__url">{{ $lpMockHost }} / composer</span>
              </div>
              <div class="lp-mockup__body lp-mockup-app" data-lp-mockup-demo-root aria-hidden="true">
                <div class="lp-mockup-app__drawer-backdrop" data-lp-mockup-drawer-backdrop aria-hidden="true"></div>
                <aside class="lp-mockup-app__sidebar" id="lp-mockup-app-sidebar">
                  <div class="lp-mockup-app__brand">
                    <span class="lp-mockup-app__collapse" title="Navigation"><i class="fa-solid fa-angles-left" aria-hidden="true"></i></span>
                    @include('brand-logo')
                    <button type="button" class="lp-mockup-app__drawer-close" data-lp-mockup-drawer-close aria-label="Close menu">
                      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                  </div>
                  <div class="lp-mockup-app__search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <span>Search pages…</span>
                    <kbd>⌘K</kbd>
                  </div>
                  <nav class="lp-mockup-app__nav">
                    <span class="lp-mockup-app__nav-link is-active" data-lp-mockup-nav-home><i class="fa-solid fa-house fa-fw" aria-hidden="true"></i>Home</span>
                    <div class="lp-mockup-app__nav-group">
                      <div class="lp-mockup-app__nav-label"><span class="lp-mockup-app__dot lp-mockup-app__dot--pub"></span>Publishing</div>
                      <span class="lp-mockup-app__nav-link lp-mockup-app__nav-link--sub" data-lp-mockup-nav-composer><i class="fa-solid fa-pen-to-square fa-fw" aria-hidden="true"></i>Create post</span>
                      <span class="lp-mockup-app__nav-link lp-mockup-app__nav-link--sub"><i class="fa-solid fa-calendar-days fa-fw" aria-hidden="true"></i>Calendar</span>
                      <span class="lp-mockup-app__nav-link lp-mockup-app__nav-link--sub"><i class="fa-solid fa-photo-film fa-fw" aria-hidden="true"></i>Media library</span>
                    </div>
                    <div class="lp-mockup-app__nav-group">
                      <div class="lp-mockup-app__nav-label"><span class="lp-mockup-app__dot lp-mockup-app__dot--acc"></span>Accounts</div>
                      <span class="lp-mockup-app__nav-link lp-mockup-app__nav-link--sub"><i class="fa-solid fa-link fa-fw" aria-hidden="true"></i>Connect accounts</span>
                    </div>
                    <div class="lp-mockup-app__nav-group">
                      <div class="lp-mockup-app__nav-label"><span class="lp-mockup-app__dot lp-mockup-app__dot--ana"></span>Analytics</div>
                      <span class="lp-mockup-app__nav-link lp-mockup-app__nav-link--sub"><i class="fa-solid fa-chart-line fa-fw" aria-hidden="true"></i>Insights</span>
                    </div>
                    <div class="lp-mockup-app__nav-group">
                      <div class="lp-mockup-app__nav-label"><span class="lp-mockup-app__dot lp-mockup-app__dot--bill"></span>Billing</div>
                      <span class="lp-mockup-app__nav-link lp-mockup-app__nav-link--sub"><i class="fa-solid fa-layer-group fa-fw" aria-hidden="true"></i>Plans</span>
                      <span class="lp-mockup-app__nav-link lp-mockup-app__nav-link--sub"><i class="fa-solid fa-clock-rotate-left fa-fw" aria-hidden="true"></i>Plan history</span>
                    </div>
                    <div class="lp-mockup-app__nav-group">
                      <div class="lp-mockup-app__nav-label"><span class="lp-mockup-app__dot lp-mockup-app__dot--user"></span>Account</div>
                      <span class="lp-mockup-app__nav-link lp-mockup-app__nav-link--sub"><i class="fa-solid fa-user fa-fw" aria-hidden="true"></i>Profile</span>
                      <span class="lp-mockup-app__nav-link lp-mockup-app__nav-link--sub"><i class="fa-solid fa-gear fa-fw" aria-hidden="true"></i>Settings</span>
                      <span class="lp-mockup-app__nav-link lp-mockup-app__nav-link--sub"><i class="fa-solid fa-ticket fa-fw" aria-hidden="true"></i>Support tickets</span>
                    </div>
                  </nav>
                  <div class="lp-mockup-app__sidebar-foot">
                    <div class="lp-mockup-app__userchip">
                      <span class="lp-mockup-app__u-av" aria-hidden="true"></span>
                      <div class="lp-mockup-app__u-meta">
                        <strong>Alex Morgan</strong>
                        <span>alex@studio.test</span>
                      </div>
                    </div>
                  </div>
                </aside>
                <div class="lp-mockup-app__workspace">
                  <header class="lp-mockup-app__topbar">
                    <button type="button" class="lp-mockup-app__menu-btn" data-lp-mockup-drawer-open aria-label="Open menu" aria-expanded="false" aria-controls="lp-mockup-app-sidebar">
                      <i class="fa-solid fa-bars" aria-hidden="true"></i>
                    </button>
                    <div class="lp-mockup-app__lead">
                      <span class="lp-mockup-app__hello">Hello,</span>
                      <strong>Alex</strong>
                    </div>
                    <div class="lp-mockup-app__spacer" aria-hidden="true"></div>
                    <div class="lp-mockup-app__cluster">
                      <time class="lp-mockup-app__date" data-lp-mockup-date datetime=""></time>
                      <span class="lp-mockup-app__tb-btn" title="Notifications"><i class="fa-solid fa-bell" aria-hidden="true"></i></span>
                      <span class="lp-mockup-app__tz"><abbr title="Display timezone">UTC</abbr><i class="fa-solid fa-chevron-down fa-2xs" aria-hidden="true"></i></span>
                      <span class="lp-mockup-app__theme"><i class="fa-solid fa-moon" aria-hidden="true"></i><span>Dark</span></span>
                      <span class="lp-mockup-app__acct"><span class="lp-mockup-app__acct-av" aria-hidden="true"></span><i class="fa-solid fa-chevron-down fa-2xs" aria-hidden="true"></i></span>
                    </div>
                  </header>
                  <div class="lp-mockup-app__main">
                    <div class="lp-mockup-app__page-head">
                      <div class="lp-mockup-app__ph-icon" aria-hidden="true"><i class="fa-solid fa-pen-to-square"></i></div>
                      <div>
                        <h2 class="lp-mockup-app__ph-title">Create post</h2>
                        <p class="lp-mockup-app__ph-sub">Draft once, tailor per network, preview the feed.</p>
                      </div>
                    </div>
                    <div class="lp-mockup-app__composer-grid">
                      <div class="lp-mockup-app__card">
                        <div class="lp-mockup-app__sect">
                          <span class="lp-mockup-app__sect-label">Post to</span>
                          <div class="lp-mockup-app__platforms">
                            @forelse($lpMockPlatforms as $platform)
                            <span class="lp-mockup-app__platform" data-lp-mockup-platform>
                              <span class="lp-mockup-app__p-box" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                              <i class="{{ $platform->icon() }}" aria-hidden="true"></i>
                              {{ $platform->label() }}
                            </span>
                            @empty
                            <span class="lp-mockup-app__platform" data-lp-mockup-platform>
                              <span class="lp-mockup-app__p-box" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                              <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>X
                            </span>
                            <span class="lp-mockup-app__platform" data-lp-mockup-platform>
                              <span class="lp-mockup-app__p-box" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                              <i class="fa-brands fa-linkedin" aria-hidden="true"></i>LinkedIn
                            </span>
                            @endforelse
                          </div>
                        </div>
                        <div class="lp-mockup-app__sect">
                          <div class="lp-mockup-app__draft-shell">
                            <div class="lp-mockup-app__draft" data-lp-mockup-draft></div>
                            <span class="lp-mockup-app__caret" data-lp-mockup-caret aria-hidden="true"></span>
                          </div>
                          <div class="lp-mockup-app__toolbar">
                            <span class="lp-mockup-app__pill"><i class="fa-solid fa-hashtag" aria-hidden="true"></i>Hashtags</span>
                            <span class="lp-mockup-app__pill lp-mockup-app__pill--ai"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>AI Assist</span>
                            <span class="lp-mockup-app__pill lp-mockup-app__pill--icon"><i class="fa-regular fa-face-smile" aria-hidden="true"></i></span>
                          </div>
                        </div>
                        <div class="lp-mockup-app__sect lp-mockup-app__sect--tight">
                          <div class="lp-mockup-app__pills">
                            <span class="lp-mockup-app__tab is-on">Master</span>
                            @foreach($lpMockPlatforms->take(2) as $platform)
                            <span class="lp-mockup-app__tab">{{ $platform->label() }}</span>
                            @endforeach
                            @if($lpMockPlatforms->count() === 0)
                            <span class="lp-mockup-app__tab">X</span>
                            <span class="lp-mockup-app__tab">LinkedIn</span>
                            @endif
                          </div>
                        </div>
                        <div class="lp-mockup-app__actions">
                          <span class="lp-mockup-app__btn-schedule" data-lp-mockup-schedule-btn><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>Schedule</span>
                          <span class="lp-mockup-app__btn-ghost">Save draft</span>
                        </div>
                      </div>
                      <aside class="lp-mockup-app__aside">
                        <div class="lp-mockup-app__preview-head">Feed preview</div>
                        <div class="lp-mockup-app__preview-card" data-lp-mockup-preview-card>
                          <div class="lp-mockup-app__pv-head">
                            <span class="lp-mockup-app__pv-av" aria-hidden="true"></span>
                            <div>
                              <strong>Alex Morgan</strong>
                              <span>@alexstudio · now</span>
                            </div>
                          </div>
                          <p class="lp-mockup-app__pv-body" data-lp-mockup-preview-text></p>
                          <div class="lp-mockup-app__pv-bar">
                            <span><i class="fa-regular fa-comment" aria-hidden="true"></i></span>
                            <span><i class="fa-solid fa-retweet" aria-hidden="true"></i></span>
                            <span><i class="fa-regular fa-heart" aria-hidden="true"></i></span>
                          </div>
                        </div>
                        <div class="lp-mockup-app__preview-card lp-mockup-app__preview-card--dim">
                          <div class="lp-mockup-app__pv-head">
                            <span class="lp-mockup-app__pv-av lp-mockup-app__pv-av--in" aria-hidden="true"></span>
                            <div>
                              <strong>Studio</strong>
                              <span>LinkedIn · now</span>
                            </div>
                          </div>
                          <p class="lp-mockup-app__pv-body lp-mockup-app__pv-body--muted">Same draft, tuned for professional tone…</p>
                        </div>
                      </aside>
                    </div>
                    <div class="lp-mockup-app__toast" data-lp-mockup-toast role="status">
                      <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                      <span><strong>Scheduled</strong> · Mon 9:00 AM · 3 networks</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="lp-section lp-trust" data-lp-reveal>
        <p class="lp-trust__line">Trusted by 500+ creators and brands.</p>
        <div class="lp-trust__badges">
          <a class="lp-badge" href="https://eden.co.zw/startup/wagwizi" target="_blank" rel="noopener noreferrer">
            <i class="fa-solid fa-trophy" aria-hidden="true"></i> Eden Product of the Day
          </a>
          <a class="lp-badge" href="https://eden.co.zw/startup/wagwizi" target="_blank" rel="noopener noreferrer">
            <i class="fa-solid fa-star" aria-hidden="true"></i> Top rated
          </a>
          <a class="lp-badge" href="https://eden.co.zw/startup/wagwizi" target="_blank" rel="noopener noreferrer">
            <i class="fa-solid fa-bolt" aria-hidden="true"></i> Editor's choice
          </a>
        </div>
      </section>

      <section class="lp-section" id="product">
        <div class="lp-section__head" data-lp-reveal>
          <h2>Your unique flow on every channel</h2>
          <p>Tailor copy per network, see real previews, and keep tone consistent — without tab sprawl.</p>
        </div>
        <div class="lp-wrap">
          <div class="lp-channels">
            @foreach($enabledPlatforms as $platform)
            <article class="lp-channel-card" data-lp-reveal>
              <div class="lp-channel-card__icon"><i class="{{ $platform->icon() }}" aria-hidden="true"></i></div>
              <div class="lp-channel-card__meta"><strong>{{ $platform->label() }}</strong></div>
            </article>
            @endforeach
          </div>
        </div>
      </section>

      <section class="lp-section" id="why">
        <div class="lp-section__head" data-lp-reveal>
          <h2>Why {{ config('app.name') }}?</h2>
          <p>Built for teams who outgrew spreadsheets and five browser tabs.</p>
        </div>
        <div class="lp-wrap lp-why">
          <article class="lp-why-card" data-lp-reveal>
            <div class="lp-why-card__icon"><i class="fa-solid fa-pen-nib" aria-hidden="true"></i></div>
            <h3>Compose faster</h3>
            <p>Master draft plus per-platform overrides, with AI assist when you want a second pass.</p>
          </article>
          <article class="lp-why-card" data-lp-reveal>
            <div class="lp-why-card__icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></div>
            <h3>Schedule visually</h3>
            <p>Drag posts on the calendar or pull from an unscheduled queue — times respect your timezone.</p>
          </article>
          <article class="lp-why-card" data-lp-reveal>
            <div class="lp-why-card__icon"><i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i></div>
            <h3>Measure clearly</h3>
            <p>Insights roll up by workspace so you see what actually moved the needle.</p>
          </article>
        </div>
      </section>

      <section class="lp-section" id="video">
        <div class="lp-section__head" data-lp-reveal>
          <h2>For people in action</h2>
          <p>See how teams plan a week of content in one sitting.</p>
        </div>
        <div class="lp-wrap">
          <div class="lp-video" data-lp-reveal>
            <div class="lp-video__placeholder">
              <div class="lp-video__play" role="button" tabindex="0" aria-label="Play video demo">
                <i class="fa-solid fa-play" aria-hidden="true"></i>
              </div>
              <p class="lp-video__title">GROW YOUR SOCIALS FAST</p>
              <p class="lp-video__sub">Demo video</p>
            </div>
          </div>
        </div>
      </section>

      <section class="lp-section" id="features-deep">
        <div class="lp-wrap">
          @foreach($landingFeaturesDeep as $feature)
          <div class="lp-split @if(!empty($feature['reverse'])) lp-split--reverse @endif" data-lp-reveal>
            <div class="lp-split__text">
              <h3>{{ $feature['title'] }}</h3>
              <p>{{ $feature['body'] }}</p>
              @if(!empty(trim((string) ($feature['cta_label'] ?? ''))))
              <a class="lp-btn lp-btn--primary" href="{{ $feature['cta_url'] }}">{{ $feature['cta_label'] }}</a>
              @endif
            </div>
            <div class="lp-split__visual">
              @if(($feature['visual'] ?? '') === 'glass_card')
              <div class="lp-glass">
                @if(!empty($feature['glass_eyebrow']))
                <p class="lp-glass__eyebrow">{{ $feature['glass_eyebrow'] }}</p>
                @endif
                @if(!empty($feature['glass_body']))
                <p class="lp-glass__body">{{ $feature['glass_body'] }}</p>
                @endif
              </div>
              @elseif(($feature['visual'] ?? '') === 'glass_mono')
              <div class="lp-glass">
                <p class="lp-glass__mono">{{ $feature['glass_mono'] }}</p>
              </div>
              @elseif(($feature['visual'] ?? '') === 'icons')
              <div class="lp-glass lp-glass--icons">
                @if(count($feature['icon_class_list'] ?? []) > 0)
                  @foreach($feature['icon_class_list'] as $ic)
                  <i class="{{ $ic }}" aria-hidden="true"></i>
                  @endforeach
                @elseif(count($enabledPlatforms) > 0)
                  @foreach($enabledPlatforms as $platform)
                  <i class="{{ $platform->icon() }} fa-2x" aria-hidden="true"></i>
                  @endforeach
                @else
                  <p class="lp-glass__body lp-glass__body--hint">Add platform icons in admin or enable networks.</p>
                @endif
              </div>
              @elseif(($feature['visual'] ?? '') === 'image' && !empty($feature['image']))
              <div class="lp-feature-visual-photo">
                <img src="{{ asset($feature['image']) }}" alt="" loading="lazy" decoding="async" width="800" height="600" />
              </div>
              @elseif(($feature['visual'] ?? '') === 'grid')
              <div class="lp-glass lp-glass--grid"></div>
              @endif
            </div>
          </div>
          @endforeach
        </div>
      </section>

      <section class="lp-section" id="resources">
        <div class="lp-section__head" data-lp-reveal>
          <h2>Active on all your favorite channels</h2>
          <p>Floating tiles — hover to feel the glow. Connect accounts when your backend is ready.</p>
        </div>
        <div class="lp-wrap">
          <div class="lp-int-grid">
            @foreach($enabledPlatforms as $platform)
            <div class="lp-int-cell" data-lp-reveal><i class="{{ $platform->icon() }}"></i></div>
            @endforeach
          </div>
        </div>
      </section>

      <section class="lp-section lp-pricing" id="pricing">
        <div class="lp-section__head" data-lp-reveal>
          <h2>Pricing</h2>
          <p>Find the right plan for your needs(select monthly or yearly)</p>
        </div>
        <div class="lp-wrap">
          <div class="lp-billing-toggle-wrap" data-lp-reveal data-lp-currency-symbol="{{ $currencyDisplay->symbol($currencyDisplay->defaultCurrency()) }}">
            <div class="lp-billing-toggle" data-lp-billing-toggle role="group" aria-label="Billing period">
              <button type="button" class="lp-billing-toggle__btn is-active" data-lp-billing="monthly" aria-pressed="true">Monthly</button>
              <button type="button" class="lp-billing-toggle__btn" data-lp-billing="yearly" aria-pressed="false">Yearly <span class="lp-billing-toggle__save">Save ~20%</span></button>
            </div>
          </div>
          <div class="lp-pricing__grid">
            @forelse($plans as $plan)
            @php
              $isFeatured = strtolower($plan->slug) === 'growth' || strtolower($plan->name) === 'growth';
              $lp = $currencyDisplay->landingPricingMajors($plan);
              $monthly = (int) round($lp['monthly']);
              $yearlyTotal = (int) round($lp['yearly_total']);
            @endphp
            <article class="lp-pricing-card{{ $isFeatured ? ' lp-pricing-card--featured' : '' }}" data-lp-pricing-card data-monthly="{{ $monthly }}" data-yearly-total="{{ $yearlyTotal }}" data-lp-reveal>
              @if($isFeatured)
              <span class="lp-pricing-card__badge">Popular</span>
              @endif
              <h3 class="lp-pricing-card__name">{{ $plan->name }}</h3>
              <p class="lp-pricing-card__price">
                <span class="lp-pricing-card__amount">
                  <span class="lp-pricing-card__currency">{{ $currencyDisplay->symbol($currencyDisplay->defaultCurrency()) }}</span><span data-lp-price-amount>{{ $monthly }}</span>
                </span>
                <span class="lp-pricing-card__suffix" data-lp-price-suffix>/ month</span>
              </p>
              <p class="lp-pricing-card__billing" data-lp-price-billing hidden></p>
              @if($plan->freeTrialSummary())
              <p class="lp-pricing-card__trial"><i class="fa-solid fa-gift" aria-hidden="true"></i> {{ $plan->freeTrialSummary() }}</p>
              @endif
              <ul class="lp-pricing-card__list">
                @if($plan->max_social_profiles === null)
                <li>Unlimited social profiles</li>
                @else
                <li>{{ $plan->max_social_profiles }} social profiles</li>
                @endif

                @if($plan->max_scheduled_posts_per_month === null)
                <li>Unlimited scheduled posts</li>
                @else
                <li>{{ $plan->max_scheduled_posts_per_month }} scheduled posts / month</li>
                @endif

                @foreach(array_slice($plan->features ?? [], 0, 4) as $feature)
                <li>{{ $feature }}</li>
                @endforeach
              </ul>
              @auth
              <a class="lp-btn {{ $isFeatured ? 'lp-btn--primary' : 'lp-btn--outline' }} lp-pricing-card__cta" href="{{ route('plans') }}">Choose {{ $plan->name }}</a>
              @else
              <a class="lp-btn {{ $isFeatured ? 'lp-btn--primary' : 'lp-btn--outline' }} lp-pricing-card__cta" href="{{ route('signup', ['redirect' => '/plans']) }}">Choose {{ $plan->name }}</a>
              @endauth
            </article>
            @empty
            <article class="lp-pricing-card lp-pricing-card--enterprise" data-lp-reveal>
              <h3 class="lp-pricing-card__name">No plans configured</h3>
              <p class="lp-pricing-card__price lp-pricing-card__price--static"><span class="lp-pricing-card__amount">Contact admin</span></p>
              <ul class="lp-pricing-card__list"><li>Ask an admin to create active plans from the dashboard.</li></ul>
              @auth
              <a class="lp-btn lp-btn--outline lp-pricing-card__cta" href="{{ route('plans') }}">Create account</a>
              @else
              <a class="lp-btn lp-btn--outline lp-pricing-card__cta" href="{{ route('signup', ['redirect' => '/plans']) }}">Create account</a>
              @endauth
            </article>
            @endforelse
          </div>
        </div>
      </section>

      <section class="lp-cta-band" data-lp-reveal>
        <h2>Ready to get started?</h2>
        <a class="lp-btn lp-btn--light lp-btn--lg" href="{{ route('signup') }}">Join now</a>
      </section>

      <section class="lp-section" id="love">
        <div class="lp-section__head" data-lp-reveal>
          <h2>Wall of love</h2>
          <p>Teams who switched from duct-tape workflows.</p>
        </div>
        <div class="lp-wrap">
          @forelse($testimonials as $testimonial)
          <article class="lp-tweet" data-lp-reveal>
            <div class="lp-tweet__head">
              @if($testimonial->author_avatar)
              <img class="lp-tweet__avatar" src="{{ $testimonial->author_avatar }}" alt="{{ $testimonial->author_name }}" />
              @else
              <div class="lp-tweet__avatar" aria-hidden="true"></div>
              @endif
              <div class="lp-tweet__meta">
                <strong>{{ $testimonial->author_name }}</strong>
                @if($testimonial->author_title)<span>{{ $testimonial->author_title }}</span>@endif
              </div>
            </div>
            <p>{{ $testimonial->body }}</p>
            @if($testimonial->rating)
            <div class="lp-tweet__rating" aria-label="{{ $testimonial->rating }} out of 5 stars">
              @for($i = 1; $i <= 5; $i++)
                <i class="fa-{{ $i <= $testimonial->rating ? 'solid' : 'regular' }} fa-star" aria-hidden="true"></i>
              @endfor
            </div>
            @endif
          </article>
          @empty
          <article class="lp-tweet" data-lp-reveal>
            <div class="lp-tweet__head">
              <div class="lp-tweet__avatar" aria-hidden="true"></div>
              <div class="lp-tweet__meta"><strong>Happy User</strong><span>Early adopter</span></div>
            </div>
            <p>Be among the first to share your experience with {{ config('app.name') }}!</p>
          </article>
          @endforelse
        </div>
      </section>

      @if($faqs->isNotEmpty())
      <section class="lp-section" id="faq">
        <div class="lp-section__head" data-lp-reveal>
          <h2>Frequently asked questions</h2>
        </div>
        <div class="lp-faq" data-lp-faq data-lp-reveal>
          @foreach($faqs as $faq)
          <div class="lp-faq__item">
            <button type="button" class="lp-faq__question" aria-expanded="false">{{ $faq->question }}<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
            <div class="lp-faq__answer">{!! nl2br(e($faq->answer)) !!}</div>
          </div>
          @endforeach
        </div>
      </section>
      @endif
    </main>

    <footer class="lp-footer">
      <div class="lp-footer__top">
        <a class="lp-btn lp-btn--primary lp-btn--lg" href="{{ route('signup') }}">Ready to get started?</a>
      </div>
      <div class="lp-footer__grid">
        <div class="lp-footer__brand">
          <a class="lp-logo" href="{{ route('landing') }}" aria-label="{{ config('app.name') }} home">
            @include('brand-logo')
          </a>
          <p>{{ $heroSubheading }}</p>
          <div class="lp-footer__social">
            <a href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin"></i></a>
            <a href="#" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a>
            <a href="#" aria-label="GitHub"><i class="fa-brands fa-github"></i></a>
          </div>
        </div>
        <div>
          <h4>Product</h4>
          <ul>
            <li><a href="{{ route('login', ['redirect' => '/dashboard']) }}">Dashboard</a></li>
            <li><a href="{{ route('login', ['redirect' => '/composer']) }}">Create post</a></li>
            <li><a href="{{ route('login', ['redirect' => '/calendar']) }}">Calendar</a></li>
            <li><a href="{{ route('login', ['redirect' => '/media-library']) }}">Media library</a></li>
          </ul>
        </div>
        <div>
          <h4>Resources</h4>
          <ul>
            <li><a href="{{ route('login', ['redirect' => '/accounts']) }}">Connect accounts</a></li>
            <li><a href="{{ route('login', ['redirect' => '/insights']) }}">Insights</a></li>
            <li><a href="#pricing">Plans</a></li>
          </ul>
        </div>
        <div>
          <h4>Company</h4>
          <ul>
            <li><a href="#pricing">Pricing</a></li>
            <li><a href="{{ route('login', ['redirect' => '/dashboard']) }}">Log in</a></li>
            <li><a href="#faq">FAQ</a></li>
          </ul>
        </div>
      </div>
      <div class="lp-footer__bottom">
        <span class="lp-footer__copy">&copy; {{ date('Y') }} {{ config('app.name') }}</span>
        <span class="lp-footer__bottom-sep" aria-hidden="true">·</span>
        <span class="lp-footer__legal">
          <a href="{{ route('terms') }}">Terms of Service</a>
          <span class="lp-footer__bottom-sep" aria-hidden="true">·</span>
          <a href="{{ route('privacy') }}">Privacy Policy</a>
        </span>
        <span class="lp-footer__bottom-sep" aria-hidden="true">·</span>
        <span class="lp-footer__credit">Developed by <a href="https://dextersoft.com" target="_blank" rel="noopener noreferrer">Dextersoft</a></span>
      </div>
    </footer>

    <script src="{{ asset('assets/js/landing.js') }}" defer></script>
  </body>
</html>
