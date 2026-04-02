@extends('app')

@section('title', 'Payment gateways — ' . config('app.name'))
@section('page-id', 'admin-payment-gateways')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-building-columns"></i></div>
                <div>
                  <h1>Payment gateways</h1>
                  <p>Connect processors for subscriptions. More gateways can be added here over time.</p>
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

          <form method="POST" action="{{ route('admin.payment-gateways.update') }}" class="admin-gateway-stack">
            @csrf

            <section class="card admin-gateway-card" aria-labelledby="gw-paynow-heading">
              <div class="card__head admin-gateway-card__head">
                <div>
                  <h2 id="gw-paynow-heading" class="admin-gateway-card__title">Paynow</h2>
                  <p class="admin-gateway-card__sub">Zimbabwe — hosted checkout for cards &amp; wallets (SDK bundled in <code class="admin-code-tag">app/logic/gateways/Paynow</code>).</p>
                </div>
                <label class="admin-toggle">
                  <input type="checkbox" name="paynow_enabled" value="1" {{ !empty($gateways['paynow']['enabled']) ? 'checked' : '' }} />
                  <span class="admin-toggle__slider" aria-hidden="true"></span>
                  <span>Enabled</span>
                </label>
              </div>
              <div class="card__body admin-form-grid">
                <div class="field field--full">
                  <label class="field__label" for="paynow_integration_id">Integration ID</label>
                  <input class="input" id="paynow_integration_id" name="paynow_integration_id" value="{{ $gateways['paynow']['integration_id'] ?? '' }}" autocomplete="off" />
                </div>
                <div class="field field--full">
                  <label class="field__label" for="paynow_integration_key">Integration key</label>
                  <input class="input" id="paynow_integration_key" name="paynow_integration_key" type="password" placeholder="{{ ($gateways['paynow']['integration_key'] ?? '') !== '' ? 'Leave blank to keep current key' : 'Paste key from Paynow dashboard' }}" autocomplete="new-password" />
                  @if(!empty($gateways['paynow']['integration_key_masked']))
                    <p class="field__hint">Current key: {{ $gateways['paynow']['integration_key_masked'] }}</p>
                  @endif
                </div>
                <div class="field field--full">
                  <p class="field__hint admin-gateway-hint">
                    <strong>Webhook URL</strong> (set in Paynow as “Result URL”): <span class="admin-mono">{{ url('/paynow/result') }}</span><br />
                    <strong>Return URL</strong> is generated for each checkout (customer returns to Plans after paying).
                  </p>
                </div>
              </div>
            </section>

            <section class="card admin-gateway-card admin-gateway-card--muted" aria-labelledby="gw-stripe-heading">
              <div class="card__head admin-gateway-card__head">
                <div>
                  <h2 id="gw-stripe-heading" class="admin-gateway-card__title">Stripe <span class="admin-badge-soon">Coming soon</span></h2>
                  <p class="admin-gateway-card__sub">Fields are saved for when Stripe checkout is wired up — no charges run yet.</p>
                </div>
                <label class="admin-toggle">
                  <input type="checkbox" name="stripe_enabled" value="1" {{ !empty($gateways['stripe']['enabled']) ? 'checked' : '' }} />
                  <span class="admin-toggle__slider" aria-hidden="true"></span>
                  <span>Enabled</span>
                </label>
              </div>
              <div class="card__body admin-form-grid">
                <div class="field field--full">
                  <label class="field__label" for="stripe_publishable_key">Publishable key</label>
                  <input class="input" id="stripe_publishable_key" name="stripe_publishable_key" value="{{ $gateways['stripe']['publishable_key'] ?? '' }}" autocomplete="off" />
                </div>
                <div class="field field--full">
                  <label class="field__label" for="stripe_secret_key">Secret key</label>
                  <input class="input" id="stripe_secret_key" name="stripe_secret_key" type="password" placeholder="pk_live_… / sk_live_…" autocomplete="new-password" />
                </div>
                <div class="field field--full">
                  <label class="field__label" for="stripe_webhook_secret">Webhook signing secret</label>
                  <input class="input" id="stripe_webhook_secret" name="stripe_webhook_secret" type="password" autocomplete="new-password" />
                </div>
              </div>
            </section>

            <div class="admin-form-footer">
              <button type="submit" class="btn btn--primary">Save gateway settings</button>
            </div>
          </form>
        </main>
@endsection
