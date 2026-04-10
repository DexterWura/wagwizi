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

          <div class="admin-settings-nav" data-admin-settings-nav role="tablist" aria-label="Settings sections">
            <button type="button" class="admin-settings-nav__btn" data-admin-settings-target="general" role="tab" aria-selected="true">General</button>
            <button type="button" class="admin-settings-nav__btn" data-admin-settings-target="seo" role="tab" aria-selected="false">SEO</button>
            <button type="button" class="admin-settings-nav__btn" data-admin-settings-target="landing" role="tab" aria-selected="false">Landing page</button>
            <button type="button" class="admin-settings-nav__btn" data-admin-settings-target="maintenance" role="tab" aria-selected="false">Maintenance</button>
          </div>

          <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="return_section" value="general" data-admin-return-section />
            <div class="grid-balance" data-admin-settings-grid>
              <div data-admin-settings-column>
                <div class="card" data-admin-settings-pane="general">
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
                <div class="card" data-admin-settings-pane="general">
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
                <div class="card" data-admin-settings-pane="seo">
                  <div class="card__head">SEO &amp; Social sharing</div>
                  <div class="card__body">
                    <div class="field">
                      <label class="field__label" for="seo_meta_title">Meta title</label>
                      <input class="input" id="seo_meta_title" name="seo_meta_title" value="{{ $settings['seo_meta_title'] }}" maxlength="120" placeholder="{{ config('app.name') }}" data-seo-title-input />
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_meta_description">Meta description</label>
                      <textarea class="input" id="seo_meta_description" name="seo_meta_description" rows="3" maxlength="320" placeholder="Short, clear summary for search engines." data-seo-description-input>{{ $settings['seo_meta_description'] }}</textarea>
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_social_description">Social description</label>
                      <textarea class="input" id="seo_social_description" name="seo_social_description" rows="3" maxlength="320" placeholder="How your page should read when shared on socials." data-seo-social-description-input>{{ $settings['seo_social_description'] }}</textarea>
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_keywords">Keywords (comma separated)</label>
                      <input class="input" id="seo_keywords" name="seo_keywords" value="{{ $settings['seo_keywords'] }}" maxlength="500" placeholder="social media scheduler, content calendar, ..." data-seo-keywords-input />
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_twitter_site">X/Twitter handle (optional)</label>
                      <input class="input" id="seo_twitter_site" name="seo_twitter_site" value="{{ $settings['seo_twitter_site'] }}" maxlength="50" placeholder="@wagwizi" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_local_business_name">Local SEO business name</label>
                      <input class="input" id="seo_local_business_name" name="seo_local_business_name" value="{{ $settings['seo_local_business_name'] ?? '' }}" maxlength="120" placeholder="Your business or branch name" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_local_phone">Business phone</label>
                      <input class="input" id="seo_local_phone" name="seo_local_phone" value="{{ $settings['seo_local_phone'] ?? '' }}" maxlength="64" placeholder="+1-555-555-5555" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_local_email">Business email</label>
                      <input class="input" id="seo_local_email" name="seo_local_email" type="email" value="{{ $settings['seo_local_email'] ?? '' }}" maxlength="255" placeholder="hello@example.com" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_local_address">Street address</label>
                      <input class="input" id="seo_local_address" name="seo_local_address" value="{{ $settings['seo_local_address'] ?? '' }}" maxlength="255" placeholder="123 Main St" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_local_city">City</label>
                      <input class="input" id="seo_local_city" name="seo_local_city" value="{{ $settings['seo_local_city'] ?? '' }}" maxlength="120" placeholder="Harare" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_local_region">Region / state</label>
                      <input class="input" id="seo_local_region" name="seo_local_region" value="{{ $settings['seo_local_region'] ?? '' }}" maxlength="120" placeholder="Harare Province" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_local_postal_code">Postal code</label>
                      <input class="input" id="seo_local_postal_code" name="seo_local_postal_code" value="{{ $settings['seo_local_postal_code'] ?? '' }}" maxlength="32" placeholder="00000" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_local_country_code">Country code (ISO-2)</label>
                      <input class="input" id="seo_local_country_code" name="seo_local_country_code" value="{{ $settings['seo_local_country_code'] ?? '' }}" maxlength="2" placeholder="ZW" />
                      <p class="field__hint">These fields power LocalBusiness structured data for local SEO.</p>
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_image">SEO social image</label>
                      <input class="input" type="file" id="seo_image" name="seo_image" accept="image/jpeg,image/png,image/webp" data-seo-image-input />
                      <input type="hidden" name="seo_image_existing" value="{{ $settings['seo_image_path'] }}" />
                      <label class="check-line check-line--spaced">
                        <input type="hidden" name="seo_image_remove" value="0" />
                        <input type="checkbox" name="seo_image_remove" value="1" />
                        <span>Remove current SEO image</span>
                      </label>
                      <p class="field__hint">Recommended: 1200x630 image (JPG/PNG/WebP).</p>
                    </div>
                    <div class="field">
                      <label class="field__label" for="seo_favicon">Favicon</label>
                      <input class="input" type="file" id="seo_favicon" name="seo_favicon" accept=".ico,image/png,image/jpeg,image/webp,image/svg+xml" />
                      <input type="hidden" name="seo_favicon_existing" value="{{ $settings['seo_favicon_path'] }}" />
                      <label class="check-line check-line--spaced">
                        <input type="hidden" name="seo_favicon_remove" value="0" />
                        <input type="checkbox" name="seo_favicon_remove" value="1" />
                        <span>Remove current favicon</span>
                      </label>
                      @if(!empty($settings['seo_favicon_path']))
                      <div class="admin-seo-favicon-preview">
                        <img src="{{ asset($settings['seo_favicon_path']) }}" alt="Current favicon preview" loading="lazy" decoding="async" width="32" height="32" />
                        <a href="{{ asset($settings['seo_favicon_path']) }}" target="_blank" rel="noopener noreferrer">{{ $settings['seo_favicon_path'] }}</a>
                      </div>
                      @endif
                      <p class="field__hint">Best results: square icon (32x32, 48x48, or 64x64).</p>
                    </div>
                    <div class="admin-seo-preview" data-seo-preview>
                      <p class="admin-seo-preview__label">Search preview</p>
                      <div class="admin-seo-preview__serp">
                        <strong data-seo-preview-title>{{ $settings['seo_meta_title'] ?: config('app.name') }}</strong>
                        <span class="admin-seo-preview__url">{{ rtrim(config('app.url'), '/') }}</span>
                        <p data-seo-preview-description>{{ $settings['seo_meta_description'] ?: ($settings['app_tagline'] ?: 'Describe your product for better search visibility.') }}</p>
                      </div>
                      <p class="admin-seo-preview__label">Social preview</p>
                      <div class="admin-seo-preview__social">
                        <div class="admin-seo-preview__thumb" data-seo-preview-image-wrap>
                          @if(!empty($settings['seo_image_path']))
                          <img src="{{ asset($settings['seo_image_path']) }}" alt="SEO preview image" data-seo-preview-image loading="lazy" decoding="async" width="1200" height="630" />
                          @else
                          <span data-seo-preview-image-placeholder>No image set</span>
                          @endif
                        </div>
                        <div class="admin-seo-preview__social-meta">
                          <strong data-seo-preview-social-title>{{ $settings['seo_meta_title'] ?: config('app.name') }}</strong>
                          <p data-seo-preview-social-description>{{ $settings['seo_social_description'] ?: ($settings['seo_meta_description'] ?: 'Social description preview') }}</p>
                          <span data-seo-preview-keywords>{{ $settings['seo_keywords'] }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div data-admin-settings-column>
                <div class="card" data-admin-settings-pane="general">
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
                <div class="card" data-admin-settings-pane="general">
                  <div class="card__head">Affiliate Marketing</div>
                  <div class="card__body">
                    <label class="check-line check-line--spaced">
                      <input type="hidden" name="affiliate_program_enabled" value="0" />
                      <input type="checkbox" name="affiliate_program_enabled" value="1" {{ ($settings['affiliate_program_enabled'] ?? '0') === '1' ? 'checked' : '' }} />
                      <span>Enable affiliate program</span>
                    </label>
                    <div class="field">
                      <label class="field__label" for="affiliate_first_subscription_percent">First subscription payout (%)</label>
                      <input
                        class="input"
                        id="affiliate_first_subscription_percent"
                        name="affiliate_first_subscription_percent"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        value="{{ $settings['affiliate_first_subscription_percent'] ?? '10.00' }}"
                      />
                    </div>
                    <p class="field__hint">Commission is awarded once, on the referred user's first successful paid subscription transaction.</p>
                  </div>
                </div>
                @if($socialGoogleConfigured || $socialLinkedinConfigured)
                <div class="card" data-admin-settings-pane="general">
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
                <div class="card" data-admin-settings-pane="general">
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
              <div class="card" data-admin-settings-pane="maintenance">
                <div class="card__head">Under construction mode</div>
                <div class="card__body">
                  <label class="check-line check-line--spaced">
                    <input type="hidden" name="under_construction" value="0" />
                    <input type="checkbox" name="under_construction" value="1" {{ ($settings['under_construction'] ?? '0') === '1' ? 'checked' : '' }} />
                    <span>Enable under construction mode</span>
                  </label>
                  <p class="field__hint">When enabled, only active super administrators can use the app. Other visitors and signed-in users see a maintenance page. Email login, password reset, logout, leaving &ldquo;login as user&rdquo;, payment webhooks, <code>/cron</code>, and <code>/status</code> still work. Social sign-in is disabled until this is turned off.</p>
                </div>
              </div>
            </div>
            <div class="admin-form-footer" data-admin-settings-pane="general seo maintenance">
              <button class="btn btn--primary" type="submit">Save settings</button>
            </div>
          </form>

          <form method="POST" action="{{ route('admin.settings.landing-features-deep') }}" enctype="multipart/form-data" class="card admin-landing-features-form" data-admin-settings-pane="landing">
            @csrf
            <input type="hidden" name="return_section" value="landing" />
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

          <div class="card" data-admin-settings-pane="seo">
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
                  <input type="hidden" name="return_section" value="seo" />
                  <button type="submit" class="btn btn--outline">
                    <i class="fa-solid fa-sitemap" aria-hidden="true"></i> Create sitemap
                  </button>
                </form>
                <form method="POST" action="{{ route('admin.settings.generate-robots') }}" class="inline-form">
                  @csrf
                  <input type="hidden" name="return_section" value="seo" />
                  <button type="submit" class="btn btn--outline">
                    <i class="fa-solid fa-robot" aria-hidden="true"></i> Create robots.txt
                  </button>
                </form>
              </div>
            </div>
          </div>

          <div class="card admin-settings-cache-card" data-admin-settings-pane="maintenance">
            <div class="card__head">Cache</div>
            <div class="card__body">
              <p class="field__hint">Runs <code>php artisan optimize:clear</code> — clears application cache, compiled views, route and config cache, and related caches. Use after deploys or if the app feels stale.</p>
              <form method="POST" action="{{ route('admin.settings.clear-cache') }}" class="inline-form">
                @csrf
                <input type="hidden" name="return_section" value="maintenance" />
                <button type="submit" class="btn btn--outline" onclick="return confirm('Clear all application caches?');">
                  <i class="fa-solid fa-broom" aria-hidden="true"></i> Clear site cache
                </button>
              </form>
            </div>
          </div>
        </main>
@endsection

@push('scripts')
<script>
  (function () {
    var nav = document.querySelector('[data-admin-settings-nav]');
    if (!nav) return;

    var paneEls = Array.prototype.slice.call(document.querySelectorAll('[data-admin-settings-pane]'));
    var btns = Array.prototype.slice.call(nav.querySelectorAll('[data-admin-settings-target]'));
    var returnSectionInputs = Array.prototype.slice.call(document.querySelectorAll('[data-admin-return-section]'));
    var columns = Array.prototype.slice.call(document.querySelectorAll('[data-admin-settings-column]'));
    var grid = document.querySelector('[data-admin-settings-grid]');
    var storageKey = 'admin-settings:last-pane';
    var valid = ['general', 'seo', 'landing', 'maintenance'];

    function paneMatch(el, pane) {
      var raw = (el.getAttribute('data-admin-settings-pane') || '').trim();
      if (!raw) return false;
      return raw.split(/\s+/).indexOf(pane) !== -1;
    }

    function applyPane(pane) {
      if (valid.indexOf(pane) === -1) pane = 'general';

      btns.forEach(function (btn) {
        var on = btn.getAttribute('data-admin-settings-target') === pane;
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
      });

      returnSectionInputs.forEach(function (input) {
        input.value = pane;
      });

      paneEls.forEach(function (el) {
        el.hidden = !paneMatch(el, pane);
      });

      if (columns.length) {
        columns.forEach(function (col) {
          var cards = Array.prototype.slice.call(col.children).filter(function (child) {
            return child.hasAttribute('data-admin-settings-pane');
          });
          var visible = cards.some(function (card) { return !card.hidden; });
          col.hidden = !visible;
        });
      }

      if (grid) {
        var visibleCols = columns.filter(function (col) { return !col.hidden; }).length;
        grid.setAttribute('data-admin-single-column', visibleCols <= 1 ? '1' : '0');
      }

      try {
        window.localStorage.setItem(storageKey, pane);
      } catch (e) {}
    }

    btns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var pane = btn.getAttribute('data-admin-settings-target') || 'general';
        applyPane(pane);
      });
    });

    var initialPane = 'general';
    try {
      var qs = new URLSearchParams(window.location.search || '');
      var fromQuery = qs.get('section');
      if (fromQuery && valid.indexOf(fromQuery) !== -1) {
        initialPane = fromQuery;
      } else {
        var fromStorage = window.localStorage.getItem(storageKey);
        if (fromStorage && valid.indexOf(fromStorage) !== -1) {
          initialPane = fromStorage;
        }
      }
    } catch (e) {}

    applyPane(initialPane);
  })();

  (function () {
    var titleInput = document.querySelector('[data-seo-title-input]');
    var descInput = document.querySelector('[data-seo-description-input]');
    var socialDescInput = document.querySelector('[data-seo-social-description-input]');
    var keywordsInput = document.querySelector('[data-seo-keywords-input]');
    var imageInput = document.querySelector('[data-seo-image-input]');
    var preview = document.querySelector('[data-seo-preview]');
    if (!preview) return;

    var titleOut = preview.querySelector('[data-seo-preview-title]');
    var socialTitleOut = preview.querySelector('[data-seo-preview-social-title]');
    var descOut = preview.querySelector('[data-seo-preview-description]');
    var socialDescOut = preview.querySelector('[data-seo-preview-social-description]');
    var keywordsOut = preview.querySelector('[data-seo-preview-keywords]');
    var imageWrap = preview.querySelector('[data-seo-preview-image-wrap]');

    function ensurePreviewImageEl() {
      var existing = imageWrap.querySelector('[data-seo-preview-image]');
      if (existing) return existing;
      var img = document.createElement('img');
      img.setAttribute('alt', 'SEO preview image');
      img.setAttribute('data-seo-preview-image', '');
      imageWrap.innerHTML = '';
      imageWrap.appendChild(img);
      return img;
    }

    function setImagePlaceholder() {
      imageWrap.innerHTML = '<span data-seo-preview-image-placeholder>No image set</span>';
    }

    function syncPreview() {
      var title = (titleInput && titleInput.value.trim()) || '{{ addslashes(config('app.name')) }}';
      var desc = (descInput && descInput.value.trim())
        || '{{ addslashes($settings['app_tagline'] ?: 'Describe your product for better search visibility.') }}';
      var socialDesc = (socialDescInput && socialDescInput.value.trim()) || desc;
      var keywords = (keywordsInput && keywordsInput.value.trim());

      if (titleOut) titleOut.textContent = title;
      if (socialTitleOut) socialTitleOut.textContent = title;
      if (descOut) descOut.textContent = desc;
      if (socialDescOut) socialDescOut.textContent = socialDesc;
      if (keywordsOut) keywordsOut.textContent = keywords;
    }

    [titleInput, descInput, socialDescInput, keywordsInput].forEach(function (input) {
      if (!input) return;
      input.addEventListener('input', syncPreview);
    });

    if (imageInput) {
      imageInput.addEventListener('change', function () {
        var file = imageInput.files && imageInput.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
          var img = ensurePreviewImageEl();
          img.src = e.target && e.target.result ? String(e.target.result) : '';
        };
        reader.readAsDataURL(file);
      });
    }

    syncPreview();
    if (!imageWrap.querySelector('[data-seo-preview-image]')) {
      setImagePlaceholder();
    }
  })();
</script>
@endpush
