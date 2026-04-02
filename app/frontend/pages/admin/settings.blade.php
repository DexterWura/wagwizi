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
                  </div>
                </div>
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
        </main>
@endsection
