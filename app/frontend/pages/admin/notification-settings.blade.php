@extends('app')

@section('title', 'Notification channel settings — ' . config('app.name'))
@section('page-id', 'admin-notification-settings')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-envelope-open-text"></i></div>
                <div>
                  <h1>Notification channels</h1>
                  <p>Configure outbound email and SMS credentials. Passwords are stored encrypted.</p>
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

          <form method="POST" action="{{ route('admin.notifications.settings.update') }}" class="admin-notification-form">
            @csrf
            <div class="grid-balance">
              <div>
                <div class="card">
                  <div class="card__head">Email</div>
                  <div class="card__body">
                    <div class="field">
                      <label class="field__label" for="driver">Driver</label>
                      <select class="select" id="driver" name="driver" required>
                        <option value="log" {{ ($settings['driver'] ?? '') === 'log' ? 'selected' : '' }}>Log (development)</option>
                        <option value="sendmail" {{ ($settings['driver'] ?? '') === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                        <option value="smtp" {{ ($settings['driver'] ?? '') === 'smtp' ? 'selected' : '' }}>SMTP</option>
                      </select>
                    </div>
                    <div class="field">
                      <label class="field__label" for="from_name">From name</label>
                      <input class="input" id="from_name" name="from_name" value="{{ old('from_name', $settings['from_name'] ?? '') }}" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="from_address">From address</label>
                      <input class="input" id="from_address" name="from_address" type="email" value="{{ old('from_address', $settings['from_address'] ?? '') }}" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="reply_to">Reply-to (optional)</label>
                      <input class="input" id="reply_to" name="reply_to" type="email" value="{{ old('reply_to', $settings['reply_to'] ?? '') }}" />
                    </div>
                    <p class="field__hint admin-notification-form__smtp-title">SMTP (when driver is SMTP)</p>
                    <div class="field">
                      <label class="field__label" for="smtp_host">SMTP host</label>
                      <input class="input" id="smtp_host" name="smtp_host" value="{{ old('smtp_host', $settings['smtp_host'] ?? '') }}" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="smtp_port">SMTP port</label>
                      <input class="input" id="smtp_port" name="smtp_port" type="number" min="1" max="65535" value="{{ old('smtp_port', $settings['smtp_port'] ?? '') }}" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="smtp_encryption">Encryption</label>
                      <select class="select" id="smtp_encryption" name="smtp_encryption">
                        <option value="" {{ old('smtp_encryption', $settings['smtp_encryption'] ?? '') === '' ? 'selected' : '' }}>None</option>
                        <option value="tls" {{ old('smtp_encryption', $settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' }}>TLS</option>
                        <option value="ssl" {{ old('smtp_encryption', $settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                      </select>
                    </div>
                    <div class="field">
                      <label class="field__label" for="smtp_username">SMTP username</label>
                      <input class="input" id="smtp_username" name="smtp_username" value="{{ old('smtp_username', $settings['smtp_username'] ?? '') }}" autocomplete="off" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="smtp_password">SMTP password</label>
                      <input class="input" id="smtp_password" name="smtp_password" type="password" value="" placeholder="{{ ($settings['smtp_password_masked'] ?? '') ? 'Leave blank to keep existing' : '' }}" autocomplete="new-password" />
                      @if(!empty($settings['smtp_password_masked']))
                        <p class="field__hint">Current password on file: {{ $settings['smtp_password_masked'] }}</p>
                      @endif
                    </div>
                    <div class="field">
                      <label class="field__label" for="smtp_timeout">Timeout (seconds)</label>
                      <input class="input" id="smtp_timeout" name="smtp_timeout" type="number" min="1" max="300" value="{{ old('smtp_timeout', $settings['smtp_timeout'] ?? '') }}" />
                    </div>
                  </div>
                </div>
              </div>
              <div>
                <div class="card">
                  <div class="card__head">SMS</div>
                  <div class="card__body">
                    <div class="field">
                      <label class="field__label" for="sms_provider">Provider</label>
                      <select class="select" id="sms_provider" name="sms_provider" required>
                        <option value="none" {{ ($settings['sms_provider'] ?? '') === 'none' ? 'selected' : '' }}>None</option>
                        <option value="twilio" {{ ($settings['sms_provider'] ?? '') === 'twilio' ? 'selected' : '' }}>Twilio</option>
                        <option value="vonage" {{ ($settings['sms_provider'] ?? '') === 'vonage' ? 'selected' : '' }}>Vonage</option>
                      </select>
                    </div>
                    <div class="field" id="twilio-fields">
                      <label class="field__label" for="twilio_account_sid">Twilio Account SID</label>
                      <input class="input" id="twilio_account_sid" name="twilio_account_sid" value="{{ old('twilio_account_sid', $settings['twilio_account_sid'] ?? '') }}" autocomplete="off" />
                    </div>
                    <div class="field">
                      <label class="field__label" for="twilio_auth_token">Twilio Auth Token</label>
                      <input class="input" id="twilio_auth_token" name="twilio_auth_token" type="password" value="" placeholder="{{ !empty($settings['twilio_auth_token_masked']) ? 'Leave blank to keep existing' : '' }}" autocomplete="new-password" />
                      @if(!empty($settings['twilio_auth_token_masked']))
                        <p class="field__hint">Token on file: {{ $settings['twilio_auth_token_masked'] }}</p>
                      @endif
                    </div>
                    <p class="field__hint">SMS sending is configured here for future use; transactional SMS flows are not yet wired.</p>
                  </div>
                </div>
                <div class="card">
                  <div class="card__head">Master email wrapper</div>
                  <div class="card__body">
                    <div class="field">
                      <label class="field__label" for="master_template_html">HTML wrapper (Blade)</label>
                      <textarea class="input admin-notification-form__master" id="master_template_html" name="master_template_html" rows="14" spellcheck="false">{{ old('master_template_html', $settings['master_template_html'] ?? '') }}</textarea>
                      <p class="field__hint">Use <code>$bodyHtml</code>, <code>$siteName</code>, <code>$unsubscribeUrl</code> in Blade syntax.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="admin-form-actions">
              <button type="submit" class="btn btn--primary">Save settings</button>
            </div>
          </form>

          <div class="card admin-notification-test">
            <div class="card__head">Test email</div>
            <div class="card__body">
              <p>Queues a sample “Subscription updated” email to your signed-in address using the saved channel settings.</p>
              <form method="POST" action="{{ route('admin.notifications.test-email') }}">
                @csrf
                <button type="submit" class="btn btn--secondary">Send test email</button>
              </form>
            </div>
          </div>
        </main>
@endsection
