@extends('app')

@section('title', 'Settings — ' . config('app.name'))
@section('page-id', 'settings')

@section('content')
        <main class="app-content app-content--settings">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true">
                  <i class="fa-solid fa-gear" aria-hidden="true"></i>
                </div>
                <div>
                  <h1>Settings</h1>
                  <p>Workspace defaults, AI assistant, notifications, and billing when you connect a backend.</p>
                </div>
              </div>
            </div>
          </div>

          <div class="grid-balance">
            <div>
              <div class="card card--settings-workspace">
                <div class="card__head">Workspace</div>
                <div class="card__body">
                  <div class="field">
                    <label class="field__label" for="ws-name">Display name</label>
                    <input class="input" id="ws-name" type="text" value="{{ $workspaceName }}" autocomplete="organization" />
                  </div>
                  <div class="field">
                    <label class="field__label" for="ws-slug">URL slug</label>
                    <input class="input" id="ws-slug" type="text" value="{{ $workspaceSlug }}" autocomplete="off" />
                    <p class="field__hint">Used in shared approval links and API webhooks.</p>
                  </div>
                  <button type="button" class="btn btn--primary" data-app-modal-open="modal-settings-saved">Save workspace</button>
                </div>
              </div>
              <div class="card card--settings-notifications">
                <div class="card__head">Notifications</div>
                <div class="card__body">
                  <label class="check-line check-line--spaced">
                    <input type="checkbox" {{ ($notifPreferences['email_on_failure'] ?? true) ? 'checked' : '' }} />
                    <span>Email when a scheduled post fails</span>
                  </label>
                  <label class="check-line check-line--spaced">
                    <input type="checkbox" {{ ($notifPreferences['weekly_digest'] ?? true) ? 'checked' : '' }} />
                    <span>Weekly digest of reach and engagement</span>
                  </label>
                  <label class="check-line check-line--spaced">
                    <input type="checkbox" {{ ($notifPreferences['product_updates'] ?? false) ? 'checked' : '' }} />
                    <span>Product updates and tips</span>
                  </label>
                  <label class="check-line">
                    <input type="checkbox" {{ $marketingEmailOptIn ? 'checked' : '' }} />
                    <span>Marketing emails from {{ config('app.name') }} (offers, announcements)</span>
                  </label>
                </div>
              </div>
            </div>
            <div>
              <div class="card about-card card--settings-time">
                <div class="card__head">Default posting time</div>
                <div class="card__body">
                  <p class="prose-muted">When you pick a day without a time, drafts land at this slot in <strong data-app-timezone-label>UTC</strong>.</p>
                  <div class="field">
                    <label class="field__label" for="default-time">Time</label>
                    <input class="input" id="default-time" type="time" value="{{ $defaultPostingTime }}" />
                  </div>
                  <button type="button" class="btn btn--primary" data-app-modal-open="modal-settings-saved">Save</button>
                </div>
              </div>
              <div class="card">
                <div class="card__head">Billing</div>
                <div class="card__body">
                  <p class="prose-muted">View tiers, upgrade or downgrade, and see what is included on each plan.</p>
                  <div class="settings-billing-actions">
                    <a class="btn btn--primary" href="{{ route('plans') }}">Manage subscription</a>
                    <a class="btn btn--outline" href="{{ route('plan-history') }}">Plan history</a>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card settings-ai-card" data-app-settings-ai>
            <div class="card__head">AI &amp; assistant</div>
            <div class="profile-card__body">
              <p class="prose-muted profile-note settings-ai-lede">
                Use {{ config('app.name') }}'s built-in models (included with your plan) or connect your own provider. In production, API keys should live only on your server — this demo stores preferences in the browser.
              </p>
              <div class="field">
                <span class="field__label" id="ai-source-label">AI source</span>
                <div class="segmented" data-app-ai-source-group role="group" aria-labelledby="ai-source-label">
                  <button type="button" data-ai-source="platform" aria-selected="true">{{ config('app.name') }} (platform)</button>
                  <button type="button" data-ai-source="byok">Your API key</button>
                </div>
              </div>
              <div class="app-ai-byok" data-app-ai-byok hidden>
                <div class="field">
                  <label class="field__label" for="ai-provider">Provider</label>
                  <select class="select" id="ai-provider" data-app-ai-provider>
                    <option value="openai">OpenAI-compatible API</option>
                    <option value="anthropic">Anthropic (Messages API)</option>
                    <option value="custom">Custom base URL</option>
                  </select>
                </div>
                <div class="field" data-app-ai-base-url-wrap hidden>
                  <label class="field__label" for="ai-base-url">API base URL</label>
                  <input class="input" id="ai-base-url" type="url" data-app-ai-base-url placeholder="https://api.example.com/v1" autocomplete="off" />
                </div>
                <div class="field">
                  <label class="field__label" for="ai-api-key">API key</label>
                  <input class="input" id="ai-api-key" type="password" data-app-ai-key autocomplete="off" />
                  <p class="field__hint" data-app-ai-key-hint></p>
                </div>
                <button type="button" class="btn btn--ghost btn--compact" data-app-ai-clear-key hidden>Remove saved key</button>
              </div>
              <div class="settings-ai-actions">
                <button type="button" class="btn btn--primary" data-app-ai-save>Save AI settings</button>
                <p class="prose-muted profile-note settings-ai-status" data-app-ai-status></p>
              </div>
            </div>
          </div>
        </main>
@endsection

@push('modals')
    <div class="app-modal app-modal--cool" id="modal-settings-saved" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-settings-saved-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel">
        <div class="modal-success-hero">
          <span class="modal-success-hero__icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
          <h2 id="modal-settings-saved-title">Changes saved</h2>
          <p class="app-modal__lede">Your workspace settings have been saved.</p>
        </div>
        <div class="app-modal__foot">
          <button type="button" class="btn btn--primary" data-app-modal-close>Done</button>
        </div>
      </div>
    </div>
@endpush
