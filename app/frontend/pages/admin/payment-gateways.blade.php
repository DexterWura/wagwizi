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

            @php
              $pricing = $gateways['pricing'] ?? [];
              $baseCur = strtoupper($pricing['base_currency'] ?? 'USD');
              $defCur = strtoupper($pricing['default_currency'] ?? 'USD');
              $rateRows = is_array($pricing['exchange_rates'] ?? null) ? $pricing['exchange_rates'] : ['USD' => 1];
            @endphp

            <section class="card admin-gateway-card" aria-labelledby="gw-currency-heading">
              <div class="card__head admin-gateway-card__head">
                <div>
                  <h2 id="gw-currency-heading" class="admin-gateway-card__title">Currency &amp; pricing display</h2>
                  <p class="admin-gateway-card__sub">Plan amounts in the database are stored in <strong>minor units</strong> (e.g. cents) of the <strong>base currency</strong>. The <strong>default currency</strong> is what customers see on the site; exchange rates convert from base to each listed currency.</p>
                </div>
              </div>
              <div class="card__body admin-form-grid">
                <div class="field">
                  <label class="field__label" for="pricing_base_currency">Base currency (plan prices)</label>
                  <input class="input" id="pricing_base_currency" name="pricing_base_currency" value="{{ $baseCur }}" maxlength="3" autocomplete="off" required />
                  <p class="field__hint">ISO 4217 code (e.g. USD). Admin “Plans” monthly/yearly fields use this currency.</p>
                </div>
                <div class="field">
                  <label class="field__label" for="pricing_default_currency">Default display currency</label>
                  <input class="input" id="pricing_default_currency" name="pricing_default_currency" value="{{ $defCur }}" maxlength="3" autocomplete="off" required />
                  <p class="field__hint">Used for pricing on the landing page and in-app Plans.</p>
                </div>
                <div class="field field--full">
                  <label class="field__label">Exchange rates</label>
                  <p class="field__hint">Each value is how many units of that currency equal <strong>one unit</strong> of the base currency (the base row is forced to <strong>1</strong>).</p>
                  <div class="admin-exchange-rates">
                    @foreach($rateRows as $code => $val)
                    <div class="admin-form-grid">
                      <div class="field">
                        <label class="field__label">Code</label>
                        <input class="input" name="exchange_rate_codes[]" value="{{ $code }}" maxlength="3" placeholder="USD" />
                      </div>
                      <div class="field">
                        <label class="field__label">Rate</label>
                        <input class="input" name="exchange_rate_values[]" type="number" step="any" min="0" value="{{ $val }}" />
                      </div>
                    </div>
                    @endforeach
                    <div class="admin-form-grid">
                      <div class="field">
                        <input class="input" name="exchange_rate_codes[]" value="" maxlength="3" placeholder="Add code" />
                      </div>
                      <div class="field">
                        <input class="input" name="exchange_rate_values[]" type="number" step="any" min="0" value="" placeholder="Rate" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <section class="card admin-gateway-card" aria-labelledby="gw-checkout-primary-heading">
              <div class="card__head admin-gateway-card__head">
                <div>
                  <h2 id="gw-checkout-primary-heading" class="admin-gateway-card__title">Primary subscription checkout</h2>
                  <p class="admin-gateway-card__sub">When a paid plan requires online payment, this gateway is used first if it is enabled and configured. The other gateway remains available as a fallback when the preferred one is not ready.</p>
                </div>
              </div>
              <div class="card__body admin-form-grid">
                <div class="field field--full">
                  @php
                    $gatewayLabels = [
                      'paynow' => 'Paynow',
                      'pesepay' => 'Pesepay',
                      'stripe' => 'Stripe',
                      'paypal' => 'PayPal',
                    ];
                    $activeGatewayOptions = is_array($activeCheckoutGateways ?? null) ? $activeCheckoutGateways : [];
                    $selectedPreferredGateway = old('checkout_gateway', $gateways['checkout_gateway'] ?? '');
                  @endphp
                  <label class="field__label" for="checkout_gateway">Preferred gateway</label>
                  @if($activeGatewayOptions !== [])
                    <select class="input" id="checkout_gateway" name="checkout_gateway" required>
                      @foreach($activeGatewayOptions as $gw)
                        <option value="{{ $gw }}" {{ $selectedPreferredGateway === $gw ? 'selected' : '' }}>
                          {{ $gatewayLabels[$gw] ?? ucfirst($gw) }}
                        </option>
                      @endforeach
                    </select>
                    <p class="field__hint">Only active and fully configured gateways appear here.</p>
                  @else
                    <select class="input" id="checkout_gateway" disabled>
                      <option selected>No active gateway yet</option>
                    </select>
                    <p class="field__hint">Enable and configure at least one gateway below, then save to choose a preferred method.</p>
                  @endif
                  @error('checkout_gateway')
                    <p class="field__hint" style="color:#b42318;">{{ $message }}</p>
                  @enderror
                </div>
              </div>
            </section>

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
                  <label class="field__label" for="paynow_checkout_currency">Paynow checkout currency (locked)</label>
                  <input class="input" id="paynow_checkout_currency" name="paynow_checkout_currency" value="{{ strtoupper($gateways['paynow']['checkout_currency'] ?? ($gateways['paynow']['accepted_currencies'][0] ?? 'USD')) }}" maxlength="3" placeholder="USD" required autocomplete="off" />
                  <p class="field__hint">Single ISO 4217 code (e.g. USD, ZWL, ZWG). The amount sent to Paynow is converted to this currency, and the initiate request includes a <code class="admin-code-tag">currency</code> field so the hosted page does not let customers choose a cheaper currency. If Paynow returns an error, set <code class="admin-code-tag">PAYNOW_SEND_CURRENCY_FIELD=false</code> in <code class="admin-code-tag">secrets/.env</code> and contact Paynow support.</p>
                </div>
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

            <section class="card admin-gateway-card" aria-labelledby="gw-pesepay-heading">
              <div class="card__head admin-gateway-card__head">
                <div>
                  <h2 id="gw-pesepay-heading" class="admin-gateway-card__title">Pesepay</h2>
                  <p class="admin-gateway-card__sub">Redirect checkout (SDK in <code class="admin-code-tag">app/logic/gateways/Pesepay</code>). Set a gateway-specific currency if your Pesepay account does not support the Paynow checkout currency.</p>
                </div>
                <label class="admin-toggle">
                  <input type="checkbox" name="pesepay_enabled" value="1" {{ !empty($gateways['pesepay']['enabled']) ? 'checked' : '' }} />
                  <span class="admin-toggle__slider" aria-hidden="true"></span>
                  <span>Enabled</span>
                </label>
              </div>
              <div class="card__body admin-form-grid">
                <div class="field field--full">
                  <label class="field__label" for="pesepay_mode">Pesepay mode</label>
                  <select class="input" id="pesepay_mode" name="pesepay_mode">
                    <option value="sandbox" {{ strtolower((string) ($gateways['pesepay']['mode'] ?? 'live')) === 'sandbox' ? 'selected' : '' }}>Sandbox (test credentials)</option>
                    <option value="live" {{ strtolower((string) ($gateways['pesepay']['mode'] ?? 'live')) === 'live' ? 'selected' : '' }}>Live (production credentials)</option>
                  </select>
                  <p class="field__hint">Use <strong>Sandbox</strong> when your Pesepay keys are test credentials.</p>
                </div>
                <div class="field field--full">
                  <label class="field__label" for="pesepay_checkout_currency">Pesepay checkout currency</label>
                  <input class="input" id="pesepay_checkout_currency" name="pesepay_checkout_currency" value="{{ strtoupper($gateways['pesepay']['checkout_currency'] ?? ($gateways['paynow']['checkout_currency'] ?? 'USD')) }}" maxlength="3" placeholder="USD" autocomplete="off" />
                  <p class="field__hint">ISO 4217 code used when initiating Pesepay transactions. If left unchanged, it falls back to the Paynow checkout currency.</p>
                </div>
                <div class="field field--full">
                  <label class="field__label" for="pesepay_integration_key">Integration key</label>
                  <input class="input" id="pesepay_integration_key" name="pesepay_integration_key" type="password" placeholder="{{ ($gateways['pesepay']['integration_key'] ?? '') !== '' ? 'Leave blank to keep current key' : 'From Pesepay dashboard' }}" autocomplete="new-password" />
                  @if(!empty($gateways['pesepay']['integration_key_masked']))
                    <p class="field__hint">Current key: {{ $gateways['pesepay']['integration_key_masked'] }}</p>
                  @endif
                </div>
                <div class="field field--full">
                  <label class="field__label" for="pesepay_encryption_key">Encryption key</label>
                  <input class="input" id="pesepay_encryption_key" name="pesepay_encryption_key" type="password" maxlength="32" placeholder="{{ ($gateways['pesepay']['encryption_key'] ?? '') !== '' ? 'Leave blank to keep current key' : '16, 24, or 32 characters' }}" autocomplete="new-password" />
                  @if(!empty($gateways['pesepay']['encryption_key_masked']))
                    <p class="field__hint">Current key ends with: {{ $gateways['pesepay']['encryption_key_masked'] }}</p>
                  @endif
                  <p class="field__hint">Must be 16, 24, or 32 characters (AES-256-CBC per Pesepay SDK).</p>
                </div>
                <div class="field field--full">
                  <p class="field__hint admin-gateway-hint">
                    <strong>Result URL</strong> (server callback in Pesepay dashboard): <span class="admin-mono">{{ url('/pesepay/result') }}</span><br />
                    <strong>Return URL</strong> is set per checkout (customer returns to Plans after paying).
                  </p>
                </div>
              </div>
            </section>

            <section class="card admin-gateway-card" aria-labelledby="gw-paypal-heading">
              <div class="card__head admin-gateway-card__head">
                <div>
                  <h2 id="gw-paypal-heading" class="admin-gateway-card__title">PayPal</h2>
                  <p class="admin-gateway-card__sub">Global checkout via PayPal account/cards (SDK in <code class="admin-code-tag">app/logic/gateways/Paypal</code>).</p>
                </div>
                <label class="admin-toggle">
                  <input type="checkbox" name="paypal_enabled" value="1" {{ !empty($gateways['paypal']['enabled']) ? 'checked' : '' }} />
                  <span class="admin-toggle__slider" aria-hidden="true"></span>
                  <span>Enabled</span>
                </label>
              </div>
              <div class="card__body admin-form-grid">
                <div class="field field--full">
                  <label class="field__label" for="paypal_mode">Mode</label>
                  <select class="input" id="paypal_mode" name="paypal_mode">
                    <option value="sandbox" {{ strtolower((string) ($gateways['paypal']['mode'] ?? 'sandbox')) === 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                    <option value="live" {{ strtolower((string) ($gateways['paypal']['mode'] ?? 'sandbox')) === 'live' ? 'selected' : '' }}>Live</option>
                  </select>
                </div>
                <div class="field field--full">
                  <label class="field__label" for="paypal_checkout_currency">PayPal charge currency (optional)</label>
                  <input class="input" id="paypal_checkout_currency" name="paypal_checkout_currency" value="{{ $gateways['paypal']['checkout_currency'] ?? '' }}" maxlength="3" placeholder="e.g. USD (leave empty to auto-pick a PayPal-supported code)" autocomplete="off" />
                  <p class="field__hint">PayPal cannot settle in every Paynow checkout currency (e.g. some local currencies). Leave blank to use the first supported option among Paynow lock → default → base, then <strong>USD</strong>. Amounts are converted using your exchange rates.</p>
                </div>
                <div class="field field--full">
                  <label class="field__label" for="paypal_client_id">Client ID</label>
                  <input class="input" id="paypal_client_id" name="paypal_client_id" value="{{ $gateways['paypal']['client_id'] ?? '' }}" autocomplete="off" />
                </div>
                <div class="field field--full">
                  <label class="field__label" for="paypal_client_secret">Client secret</label>
                  <input class="input" id="paypal_client_secret" name="paypal_client_secret" type="password" placeholder="{{ ($gateways['paypal']['client_secret'] ?? '') !== '' ? 'Leave blank to keep current secret' : 'PayPal app client secret' }}" autocomplete="new-password" />
                  @if(!empty($gateways['paypal']['client_secret_masked']))
                    <p class="field__hint">Current secret: {{ $gateways['paypal']['client_secret_masked'] }}</p>
                  @endif
                  <p class="field__hint">Return URL used by checkout: <span class="admin-mono">{{ route('plans.paypal.return') }}</span></p>
                </div>
                <div class="field field--full">
                  <label class="field__label" for="paypal_webhook_id">Webhook ID</label>
                  <input class="input" id="paypal_webhook_id" name="paypal_webhook_id" value="{{ $gateways['paypal']['webhook_id'] ?? '' }}" placeholder="WH-… (from PayPal Developer → Webhooks)" autocomplete="off" />
                  <p class="field__hint">
                    Listener URL: <span class="admin-mono">{{ url('/paypal/webhook') }}</span>.
                    Subscribe to <strong>PAYMENT.SALE.COMPLETED</strong> for signed, server-side confirmation of captured sales (retries, races with the return URL, or if the customer closes the tab after PayPal finishes but before your site responds).
                  </p>
                </div>
              </div>
            </section>

            <section class="card admin-gateway-card" aria-labelledby="gw-stripe-heading">
              <div class="card__head admin-gateway-card__head">
                <div>
                  <h2 id="gw-stripe-heading" class="admin-gateway-card__title">Stripe</h2>
                  <p class="admin-gateway-card__sub">Global checkout for cards and wallets (SDK in <code class="admin-code-tag">app/logic/gateways/stripe</code>).</p>
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
                  <input class="input" id="stripe_secret_key" name="stripe_secret_key" type="password" placeholder="{{ ($gateways['stripe']['secret_key'] ?? '') !== '' ? 'Leave blank to keep current key' : 'sk_live_…' }}" autocomplete="new-password" />
                  @if(!empty($gateways['stripe']['secret_key_masked']))
                    <p class="field__hint">Current key: {{ $gateways['stripe']['secret_key_masked'] }}</p>
                  @endif
                </div>
                <div class="field field--full">
                  <label class="field__label" for="stripe_webhook_secret">Webhook signing secret</label>
                  <input class="input" id="stripe_webhook_secret" name="stripe_webhook_secret" type="password" placeholder="{{ ($gateways['stripe']['webhook_secret'] ?? '') !== '' ? 'Leave blank to keep current secret' : 'whsec_… (recommended)' }}" autocomplete="new-password" />
                  @if(!empty($gateways['stripe']['webhook_secret_masked']))
                    <p class="field__hint">Current secret: {{ $gateways['stripe']['webhook_secret_masked'] }}</p>
                  @endif
                  <p class="field__hint">Webhook endpoint for Stripe dashboard: <span class="admin-mono">{{ url('/stripe/webhook') }}</span> (required for automatic payment confirmation).</p>
                </div>
              </div>
            </section>

            <div class="admin-form-footer">
              <button type="submit" class="btn btn--primary">Save gateway settings</button>
            </div>
          </form>
        </main>
@endsection
