@extends('app')

@section('title', 'Profile — ' . config('app.name'))
@section('page-id', 'profile')

@section('content')
        <main class="app-content app-content--profile">
          @if(session('info'))
          <div class="alert alert--info alert--spaced" role="alert">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>{{ session('info') }}</span>
          </div>
          @endif

          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true">
                  <i class="fa-solid fa-user" aria-hidden="true"></i>
                </div>
                <div>
                  <h1>Profile</h1>
                  <p>Your personal details, sign-in security, and preferences. Connect an API to persist changes.</p>
                </div>
              </div>
            </div>
            <div class="head-actions">
              <a class="btn btn--outline" href="{{ route('settings') }}"><i class="fa-solid fa-gear" aria-hidden="true"></i> Workspace settings</a>
            </div>
          </div>

          <div class="grid-balance">
            <div>
              <div class="card profile-card profile-card--stack">
                <div class="card__head">Public profile</div>
                <div class="profile-card__body">
                  <div class="profile-avatar-row">
                    <div class="profile-avatar-lg" aria-hidden="true">
                      <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="profile-avatar-actions">
                      <button type="button" class="btn btn--outline btn--compact">Change photo</button>
                      <p class="field__hint field__hint--tight">JPG or PNG, up to 5&nbsp;MB. Shown on posts and mentions.</p>
                    </div>
                  </div>
                  <div class="field">
                    <label class="field__label" for="profile-display-name">Display name</label>
                    <input class="input" id="profile-display-name" type="text" value="{{ $currentUser->name ?? '' }}" autocomplete="name" />
                  </div>
                  <div class="field">
                    <label class="field__label" for="profile-email">Email</label>
                    <input class="input" id="profile-email" type="email" value="{{ $currentUser->email ?? '' }}" autocomplete="email" />
                    <p class="field__hint">Used for sign-in and billing receipts.</p>
                  </div>
                  <div class="field">
                    <label class="field__label" for="profile-phone">Phone <span class="prose-muted">(optional)</span></label>
                    <input class="input" id="profile-phone" type="tel" value="{{ $currentUser->phone ?? '' }}" placeholder="+1 …" autocomplete="tel" />
                  </div>
                  <div class="field">
                    <label class="field__label" for="profile-bio">Bio</label>
                    <textarea class="textarea" id="profile-bio" rows="4" placeholder="Short line for your author byline…">{{ $currentUser->bio ?? '' }}</textarea>
                  </div>
                  <button type="button" class="btn btn--primary" data-app-modal-open="modal-profile-saved">Save profile</button>
                </div>
              </div>
            </div>
            <div>
              <div class="card profile-card--stack">
                <div class="card__head">Security</div>
                <div class="profile-card__body">
                  <div class="field">
                    <label class="field__label" for="profile-password-current">Current password</label>
                    <input class="input" id="profile-password-current" type="password" autocomplete="current-password" placeholder="••••••••" disabled aria-disabled="true" />
                  </div>
                  <div class="field">
                    <label class="field__label" for="profile-password-new">New password</label>
                    <input class="input" id="profile-password-new" type="password" autocomplete="new-password" placeholder="At least 12 characters" disabled aria-disabled="true" />
                  </div>
                  <div class="field">
                    <label class="field__label" for="profile-password-confirm">Confirm new password</label>
                    <input class="input" id="profile-password-confirm" type="password" autocomplete="new-password" disabled aria-disabled="true" />
                  </div>
                  <p class="prose-muted profile-note--spaced">Password must be at least 12 characters.</p>
                  <button type="button" class="btn btn--outline" disabled aria-disabled="true">Update password</button>
                </div>
              </div>
              <div class="card">
                <div class="card__head">Preferences</div>
                <div class="profile-card__body">
                  <div class="field">
                    <label class="field__label" for="profile-locale">Language</label>
                    <select class="select" id="profile-locale" name="locale">
                      <option value="en" {{ ($currentUser->locale ?? 'en') === 'en' ? 'selected' : '' }}>English (US)</option>
                      <option value="en-gb" {{ ($currentUser->locale ?? '') === 'en-gb' ? 'selected' : '' }}>English (UK)</option>
                      <option value="es" {{ ($currentUser->locale ?? '') === 'es' ? 'selected' : '' }}>Español</option>
                      <option value="fr" {{ ($currentUser->locale ?? '') === 'fr' ? 'selected' : '' }}>Français</option>
                    </select>
                  </div>
                  <p class="prose-muted profile-note">
                    <i class="fa-solid fa-globe" aria-hidden="true"></i>
                    Display timezone for schedules is set in the <strong>top bar</strong> (<span data-app-timezone-label>UTC</span>).
                  </p>
                </div>
              </div>
            </div>
          </div>
        </main>
@endsection

@push('modals')
    <div class="app-modal app-modal--cool" id="modal-profile-saved" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-profile-saved-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel">
        <div class="modal-success-hero">
          <span class="modal-success-hero__icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
          <h2 id="modal-profile-saved-title">Profile updated</h2>
          <p class="app-modal__lede">Your profile changes have been saved.</p>
        </div>
        <div class="app-modal__foot">
          <button type="button" class="btn btn--primary" data-app-modal-close>Done</button>
        </div>
      </div>
    </div>
@endpush
