<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ config('app.name') }} — Your agentic social media scheduling tool</title>
    <meta name="description" content="Plan, publish, and grow across every channel — composer, calendar, AI assist, and previews in one workspace." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="{{ asset('assets/css/landing.css') }}" />
  </head>
  <body class="lp">
    <header id="lp-header">
      <div class="lp-header__inner">
        <a class="lp-logo" href="{{ route('landing') }}" aria-label="{{ config('app.name') }} home">
          <img src="{{ asset('assets/images/logo.svg') }}" width="112" height="28" alt="{{ config('app.name') }}" />
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
          <p class="lp-hero__eyebrow" data-lp-reveal><i class="fa-solid fa-sparkles" aria-hidden="true"></i> Social OS</p>
          <h1 data-lp-reveal>Your agentic social media scheduling tool</h1>
          <p class="lp-hero__sub" data-lp-reveal>
            One workspace to compose, preview every network, schedule with drag-and-drop, and ship with confidence — powered by the same polished app UI you already use.
          </p>
          <div class="lp-hero__icons" data-lp-reveal>
            @foreach($enabledPlatforms as $platform)
            <span class="lp-float"><i class="{{ $platform->icon() }}" aria-hidden="true"></i></span>
            @endforeach
          </div>
          <div class="lp-hero__cta" data-lp-reveal>
            <a class="lp-btn lp-btn--primary lp-btn--lg" href="{{ route('signup') }}">Get started</a>
            <a class="lp-btn lp-btn--ghost lp-btn--lg" href="{{ route('login') }}">Sign in</a>
          </div>

          <div class="lp-mockup" data-lp-mockup data-lp-reveal>
            <div class="lp-mockup__glow" aria-hidden="true"></div>
            <div class="lp-mockup__tilt">
              <div class="lp-mockup__chrome">
                <span class="lp-mockup__dot"></span>
                <span class="lp-mockup__dot"></span>
                <span class="lp-mockup__dot"></span>
                <span class="lp-mockup__url">app.postai.local / dashboard</span>
              </div>
              <div class="lp-mockup__body">
                <aside class="lp-mockup__sidebar" aria-hidden="true">
                  <div class="lp-mockup__nav-item is-active"><i class="fa-solid fa-house"></i> Home</div>
                  <div class="lp-mockup__nav-item">Composer</div>
                  <div class="lp-mockup__nav-item">Calendar</div>
                  <div class="lp-mockup__nav-item">Insights</div>
                </aside>
                <div class="lp-mockup__main">
                  <div class="lp-mockup__toolbar"></div>
                  <div class="lp-mockup__cards">
                    <div class="lp-mockup__card"></div>
                    <div class="lp-mockup__card"></div>
                    <div class="lp-mockup__card"></div>
                    <div class="lp-mockup__card"></div>
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
          <span class="lp-badge"><i class="fa-solid fa-trophy" aria-hidden="true"></i> Product of the Day</span>
          <span class="lp-badge"><i class="fa-solid fa-star" aria-hidden="true"></i> Top rated</span>
          <span class="lp-badge"><i class="fa-solid fa-bolt" aria-hidden="true"></i> Editor's choice</span>
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
              <p class="lp-video__sub">Demo video — connect your player here</p>
            </div>
          </div>
        </div>
      </section>

      <section class="lp-section" id="features-deep">
        <div class="lp-wrap">
          <div class="lp-split" data-lp-reveal>
            <div class="lp-split__text">
              <h3>Everything for growth in one place</h3>
              <p>Dashboard, composer, calendar, and accounts — same shell, dark or light, with a theme toggle that persists.</p>
              <a class="lp-btn lp-btn--primary" href="{{ route('signup') }}">Explore dashboard</a>
            </div>
            <div class="lp-split__visual">
              <div class="lp-glass">
                <p class="lp-glass__eyebrow">Omnichannel</p>
                <p class="lp-glass__body">Reach, engagement, and queue — without leaving the page.</p>
              </div>
            </div>
          </div>
          <div class="lp-split lp-split--reverse" data-lp-reveal>
            <div class="lp-split__text">
              <h3>AI content assistant</h3>
              <p>Cursor-style chat beside your draft; suggested edits appear inline in the feed preview.</p>
              <a class="lp-btn lp-btn--primary" href="{{ route('signup') }}">Try composer</a>
            </div>
            <div class="lp-split__visual">
              <div class="lp-glass">
                <p class="lp-glass__mono">+ One workspace. Every channel.</p>
              </div>
            </div>
          </div>
          <div class="lp-split" data-lp-reveal>
            <div class="lp-split__text">
              <h3>Post preview across networks</h3>
              <p>Stacked previews for X, LinkedIn, YouTube, and more — edit once or per tab.</p>
            </div>
            <div class="lp-split__visual">
              <div class="lp-glass lp-glass--icons">
                <i class="fa-brands fa-x-twitter fa-2x"></i>
                <i class="fa-brands fa-linkedin fa-2x"></i>
                <i class="fa-brands fa-instagram fa-2x"></i>
              </div>
            </div>
          </div>
          <div class="lp-split lp-split--reverse" data-lp-reveal>
            <div class="lp-split__text">
              <h3>Drag-and-drop scheduling</h3>
              <p>Move posts between days or back to the unscheduled queue — persistence is yours to wire.</p>
              <a class="lp-btn lp-btn--primary" href="{{ route('signup') }}">Open calendar</a>
            </div>
            <div class="lp-split__visual">
              <div class="lp-glass lp-glass--grid"></div>
            </div>
          </div>
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
          <p>Same tiers as in the app. Switch billing period to compare monthly vs annual (save about 20% on paid plans).</p>
        </div>
        <div class="lp-wrap">
          <div class="lp-billing-toggle-wrap" data-lp-reveal>
            <div class="lp-billing-toggle" data-lp-billing-toggle role="group" aria-label="Billing period">
              <button type="button" class="lp-billing-toggle__btn is-active" data-lp-billing="monthly" aria-pressed="true">Monthly</button>
              <button type="button" class="lp-billing-toggle__btn" data-lp-billing="yearly" aria-pressed="false">Yearly <span class="lp-billing-toggle__save">Save ~20%</span></button>
            </div>
          </div>
          <div class="lp-pricing__grid">
            @forelse($plans as $plan)
            @php
              $isFeatured = strtolower($plan->slug) === 'growth' || strtolower($plan->name) === 'growth';
              $monthly = $plan->monthly_price_cents !== null ? intdiv($plan->monthly_price_cents, 100) : 0;
              $yearlyTotal = $plan->yearly_price_cents !== null ? intdiv($plan->yearly_price_cents, 100) : ($monthly * 12);
            @endphp
            <article class="lp-pricing-card{{ $isFeatured ? ' lp-pricing-card--featured' : '' }}" data-lp-pricing-card data-monthly="{{ $monthly }}" data-yearly-total="{{ $yearlyTotal }}" data-lp-reveal>
              @if($isFeatured)
              <span class="lp-pricing-card__badge">Popular</span>
              @endif
              <h3 class="lp-pricing-card__name">{{ $plan->name }}</h3>
              <p class="lp-pricing-card__price">
                <span class="lp-pricing-card__amount">
                  <span class="lp-pricing-card__currency">$</span><span data-lp-price-amount>{{ $monthly }}</span>
                </span>
                <span class="lp-pricing-card__suffix" data-lp-price-suffix>/ month</span>
              </p>
              <p class="lp-pricing-card__billing" data-lp-price-billing hidden></p>
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
              <a class="lp-btn {{ $isFeatured ? 'lp-btn--primary' : 'lp-btn--outline' }} lp-pricing-card__cta" href="{{ route('signup') }}">Choose {{ $plan->name }}</a>
            </article>
            @empty
            <article class="lp-pricing-card lp-pricing-card--enterprise" data-lp-reveal>
              <h3 class="lp-pricing-card__name">No plans configured</h3>
              <p class="lp-pricing-card__price lp-pricing-card__price--static"><span class="lp-pricing-card__amount">Contact admin</span></p>
              <ul class="lp-pricing-card__list"><li>Ask an admin to create active plans from the dashboard.</li></ul>
              <a class="lp-btn lp-btn--outline lp-pricing-card__cta" href="{{ route('signup') }}">Create account</a>
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
            <img src="{{ asset('assets/images/logo.svg') }}" width="112" height="28" alt="{{ config('app.name') }}" />
          </a>
          <p>Social scheduling and analytics UI — bring your own backend.</p>
          <div class="lp-footer__social">
            <a href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin"></i></a>
            <a href="#" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a>
            <a href="#" aria-label="GitHub"><i class="fa-brands fa-github"></i></a>
          </div>
        </div>
        <div>
          <h4>Product</h4>
          <ul>
            <li><a href="{{ route('login') }}">Dashboard</a></li>
            <li><a href="{{ route('login') }}">Create post</a></li>
            <li><a href="{{ route('login') }}">Calendar</a></li>
            <li><a href="{{ route('login') }}">Media library</a></li>
          </ul>
        </div>
        <div>
          <h4>Resources</h4>
          <ul>
            <li><a href="{{ route('login') }}">Connect accounts</a></li>
            <li><a href="{{ route('login') }}">Insights</a></li>
            <li><a href="#pricing">Plans</a></li>
          </ul>
        </div>
        <div>
          <h4>Company</h4>
          <ul>
            <li><a href="#pricing">Pricing</a></li>
            <li><a href="{{ route('login') }}">Log in</a></li>
            <li><a href="#faq">FAQ</a></li>
          </ul>
        </div>
      </div>
      <div class="lp-footer__bottom">
        <span>&copy; {{ date('Y') }} {{ config('app.name') }}</span>
      </div>
    </footer>

    <script src="{{ asset('assets/js/landing.js') }}" defer></script>
  </body>
</html>
