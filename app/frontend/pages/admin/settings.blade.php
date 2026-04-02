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
