@extends('app')

@section('title', 'Site Settings — ' . config('app.name'))
@section('page-id', 'admin-settings')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-sliders"></i></div>
                <div>
                  <h1>Site Settings</h1>
                  <p>Manage global application settings.</p>
                </div>
              </div>
            </div>
          </div>

          @if(session('success'))
            <div class="alert alert--success">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert--danger">{{ session('error') }}</div>
          @endif

          <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <div class="grid-balance">
              <div>
                <div class="card">
                  <div class="card__head">Branding</div>
                  <div class="card__body">
                    <div class="field">
                      <label class="field__label" for="app_name">App name</label>
                      <input class="input" id="app_name" name="app_name" value="{{ $settings['app_name'] }}" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="app_tagline">Tagline</label>
                      <input class="input" id="app_tagline" name="app_tagline" value="{{ $settings['app_tagline'] }}" />
                    </div>
                  </div>
                </div>
                <div class="card">
                  <div class="card__head">Landing Page Hero</div>
                  <div class="card__body">
                    <div class="field">
                      <label class="field__label" for="hero_eyebrow">Eyebrow</label>
                      <input class="input" id="hero_eyebrow" name="hero_eyebrow" value="{{ $settings['hero_eyebrow'] }}" maxlength="80" placeholder="e.g. Social OS" />
                      <p class="field__hint">Short label above the main headline (styled in uppercase on the landing page).</p>
                    </div>
                    <div class="field">
                      <label class="field__label" for="hero_heading">Heading</label>
                      <input class="input" id="hero_heading" name="hero_heading" value="{{ $settings['hero_heading'] }}" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="hero_subheading">Subheading</label>
                      <textarea class="input" id="hero_subheading" name="hero_subheading" rows="3">{{ $settings['hero_subheading'] }}</textarea>
                    </div>
                  </div>
                </div>
              </div>
              <div>
                <div class="card">
                  <div class="card__head">Access Control</div>
                  <div class="card__body">
                    <label class="check-line check-line--spaced">
                      <input type="hidden" name="registration_open" value="0" />
                      <input type="checkbox" name="registration_open" value="1" {{ $settings['registration_open'] ? 'checked' : '' }} />
                      <span>Registration open</span>
                    </label>
                    <p class="field__hint">When disabled, new users cannot sign up.</p>
                    <label class="check-line check-line--spaced">
                      <input type="hidden" name="show_floating_help" value="0" />
                      <input type="checkbox" name="show_floating_help" value="1" {{ ($settings['show_floating_help'] ?? '1') === '1' ? 'checked' : '' }} />
                      <span>Show floating Get Help button</span>
                    </label>
                    <p class="field__hint">Controls the bottom-right support shortcut in the main app (signed-in pages).</p>
                  </div>
                </div>
                @if($socialGoogleConfigured || $socialLinkedinConfigured)
                <div class="card">
                  <div class="card__head">Social login</div>
                  <div class="card__body">
                    <p class="field__hint">OAuth keys come from your environment (<code>GOOGLE_*</code> and <code>LINKEDIN_*</code>). Only providers with credentials can be toggled.</p>
                    @if($socialGoogleConfigured)
                    <label class="check-line check-line--spaced">
                      <input type="hidden" name="social_login_google" value="0" />
                      <input type="checkbox" name="social_login_google" value="1" {{ ($settings['social_login_google'] ?? '1') === '1' ? 'checked' : '' }} />
                      <span>Google (sign in / sign up)</span>
                    </label>
                    @endif
                    @if($socialLinkedinConfigured)
                    <label class="check-line check-line--spaced">
                      <input type="hidden" name="social_login_linkedin" value="0" />
                      <input type="checkbox" name="social_login_linkedin" value="1" {{ ($settings['social_login_linkedin'] ?? '1') === '1' ? 'checked' : '' }} />
                      <span>LinkedIn (sign in / sign up)</span>
                    </label>
                    @endif
                  </div>
                </div>
                @endif
                @if($timezonesForSelect->isNotEmpty())
                <div class="card">
                  <div class="card__head">Schedules &amp; reports</div>
                  <div class="card__body">
                    <div class="field">
                      <label class="field__label" for="default_display_timezone">Default display timezone</label>
                      <select class="input" id="default_display_timezone" name="default_display_timezone" required>
                        @foreach($timezonesForSelect as $tz)
                          <option value="{{ $tz->identifier }}" {{ ($settings['default_display_timezone'] ?? 'UTC') === $tz->identifier ? 'selected' : '' }}>{{ $tz->identifier }}</option>
                        @endforeach
                      </select>
                      <p class="field__hint">Initial choice for the top-bar timezone picker when no preference is stored in the browser.</p>
                    </div>
                  </div>
                </div>
                @endif
              </div>
            </div>
            <div class="admin-form-footer">
              <button class="btn btn--primary" type="submit">Save settings</button>
            </div>
          </form>

          <form method="POST" action="{{ route('admin.settings.landing-features-deep') }}" enctype="multipart/form-data" class="card admin-landing-features-form">
            @csrf
            <div class="card__head">Landing page — Features deep</div>
            <div class="card__body">
              <p class="field__hint">The four rows in <code>#features-deep</code> on the home page. For <strong>Icon row</strong>, leave classes empty to use enabled platform icons, or enter comma-separated Font Awesome classes (e.g. <code>fa-brands fa-x-twitter fa-2x,fa-brands fa-linkedin fa-2x</code>).</p>
              <p class="field__hint">CTA URL: leave blank for sign-up, or use a path (<code>/dashboard</code>) or full <code>https://</code> URL.</p>
              @for($i = 0; $i < 4; $i++)
              @php $lf = $landingFeaturesDeep[$i]; @endphp
              <fieldset class="admin-landing-feature-block">
                <legend class="admin-landing-feature-block__title">Block {{ $i + 1 }}</legend>
                <div class="field">
                  <label class="check-line check-line--spaced">
                    <input type="checkbox" name="features[{{ $i }}][reverse]" value="1" {{ !empty($lf['reverse']) ? 'checked' : '' }} />
                    <span>Reverse layout (visual on the left)</span>
                  </label>
                </div>
                <div class="field">
                  <label class="field__label" for="lf-{{ $i }}-title">Title</label>
                  <input class="input" id="lf-{{ $i }}-title" name="features[{{ $i }}][title]" value="{{ $lf['title'] }}" />
                </div>
                <div class="field">
                  <label class="field__label" for="lf-{{ $i }}-body">Body</label>
                  <textarea class="input" id="lf-{{ $i }}-body" name="features[{{ $i }}][body]" rows="3">{{ $lf['body'] }}</textarea>
                </div>
                <div class="field">
                  <label class="field__label" for="lf-{{ $i }}-cta_label">CTA label</label>
                  <input class="input" id="lf-{{ $i }}-cta_label" name="features[{{ $i }}][cta_label]" value="{{ $lf['cta_label'] }}" placeholder="Optional" />
                </div>
                <div class="field">
                  <label class="field__label" for="lf-{{ $i }}-cta_href">CTA URL</label>
                  <input class="input" id="lf-{{ $i }}-cta_href" name="features[{{ $i }}][cta_href]" value="{{ $lf['cta_href'] }}" placeholder="/dashboard or https://…" />
                </div>
                <div class="field">
                  <label class="field__label" for="lf-{{ $i }}-visual">Visual style</label>
                  <select class="input" id="lf-{{ $i }}-visual" name="features[{{ $i }}][visual]">
                    @foreach($landingFeaturesVisualLabels as $val => $label)
                    <option value="{{ $val }}" {{ ($lf['visual'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="field">
                  <label class="field__label" for="lf-{{ $i }}-glass_eyebrow">Glass eyebrow</label>
                  <input class="input" id="lf-{{ $i }}-glass_eyebrow" name="features[{{ $i }}][glass_eyebrow]" value="{{ $lf['glass_eyebrow'] }}" placeholder="For “Glass card” style" />
                </div>
                <div class="field">
                  <label class="field__label" for="lf-{{ $i }}-glass_body">Glass body</label>
                  <textarea class="input" id="lf-{{ $i }}-glass_body" name="features[{{ $i }}][glass_body]" rows="2" placeholder="For “Glass card” style">{{ $lf['glass_body'] }}</textarea>
                </div>
                <div class="field">
                  <label class="field__label" for="lf-{{ $i }}-glass_mono">Glass single line</label>
                  <input class="input" id="lf-{{ $i }}-glass_mono" name="features[{{ $i }}][glass_mono]" value="{{ $lf['glass_mono'] }}" placeholder="For “Glass — single line”" />
                </div>
                <div class="field">
                  <label class="field__label" for="lf-{{ $i }}-icon_classes">Icon classes</label>
                  <input class="input" id="lf-{{ $i }}-icon_classes" name="features[{{ $i }}][icon_classes]" value="{{ $lf['icon_classes'] }}" placeholder="Comma-separated; empty = platform icons" />
                </div>
                <div class="field">
                  <label class="field__label" for="lf-{{ $i }}-image">Image (photo style)</label>
                  <input class="input" type="file" id="lf-{{ $i }}-image" name="features[{{ $i }}][image]" accept="image/jpeg,image/png,image/gif,image/webp" />
                  <input type="hidden" name="features[{{ $i }}][image_existing]" value="{{ $lf['image'] ?? '' }}" />
                  @if(!empty($lf['image']))
                  <p class="field__hint">Current: <a href="{{ asset($lf['image']) }}" target="_blank" rel="noopener noreferrer">{{ $lf['image'] }}</a> — upload a new file to replace.</p>
                  @endif
                </div>
              </fieldset>
              @endfor
            </div>
            <div class="admin-form-footer">
              <button type="submit" class="btn btn--primary">Save features section</button>
            </div>
          </form>

          <div class="card">
            <div class="card__head">SEO files</div>
            <div class="card__body">
              <p class="field__hint">Writes static files to the site document root so crawlers can load <code>/sitemap.xml</code> and <code>/robots.txt</code> without hitting PHP. URLs use <code>APP_URL</code> from your environment.</p>
              <p class="field__hint">
                sitemap.xml: {{ $sitemapExists ? 'on disk' : 'not created yet' }} ·
                robots.txt: {{ $robotsExists ? 'on disk' : 'not created yet' }}
              </p>
              <div class="admin-seo-files-actions">
                <form method="POST" action="{{ route('admin.settings.generate-sitemap') }}" class="inline-form">
                  @csrf
                  <button type="submit" class="btn btn--outline">
                    <i class="fa-solid fa-sitemap" aria-hidden="true"></i> Create sitemap
                  </button>
                </form>
                <form method="POST" action="{{ route('admin.settings.generate-robots') }}" class="inline-form">
                  @csrf
                  <button type="submit" class="btn btn--outline">
                    <i class="fa-solid fa-robot" aria-hidden="true"></i> Create robots.txt
                  </button>
                </form>
              </div>
            </div>
          </div>

          <div class="card admin-settings-cache-card">
            <div class="card__head">Cache</div>
            <div class="card__body">
              <p class="field__hint">Runs <code>php artisan optimize:clear</code> — clears application cache, compiled views, route and config cache, and related caches. Use after deploys or if the app feels stale.</p>
              <form method="POST" action="{{ route('admin.settings.clear-cache') }}" class="inline-form">
                @csrf
                <button type="submit" class="btn btn--outline" onclick="return confirm('Clear all application caches?');">
                  <i class="fa-solid fa-broom" aria-hidden="true"></i> Clear site cache
                </button>
              </form>
            </div>
          </div>
        </main>
@endsection
