@extends('app')

@section('title', 'Email Notification Settings — ' . config('app.name'))
@section('page-id', 'admin-notification-settings')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-envelope-open-text"></i></div>
                <div>
                  <h1>Email Notification Settings</h1>
                  <p>Configure SMTP using the exact fields below.</p>
                </div>
              </div>
              <form method="POST" action="{{ route('admin.notifications.test-email') }}">
                @csrf
                <button type="submit" class="btn btn--secondary"><i class="fa-regular fa-paper-plane"></i> Send Test Mail</button>
              </form>
            </div>
          </div>

          @if(session('success'))
            <div class="alert alert--success">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert--danger">{{ session('error') }}</div>
          @endif

          <form method="POST" action="{{ route('admin.notifications.settings.update') }}" class="admin-notification-form card">
            @csrf
            <div class="card__body">
              <div class="field">
                <label class="field__label" for="email_send_method">Email Send Method</label>
                <select class="select" id="email_send_method" name="email_send_method" required>
                  <option value="smtp" {{ old('email_send_method', $settings['email_send_method'] ?? 'smtp') === 'smtp' ? 'selected' : '' }}>SMTP</option>
                </select>
              </div>

              <h3 style="margin: 18px 0 10px;">SMTP Configuration</h3>
              <div class="admin-form-grid admin-form-grid--three">
                <div class="field">
                  <label class="field__label" for="smtp_host">Host</label>
                  <input class="input" id="smtp_host" name="smtp_host" required value="{{ old('smtp_host', $settings['smtp_host'] ?? '') }}" />
                </div>
                <div class="field">
                  <label class="field__label" for="smtp_port">Port</label>
                  <input class="input" id="smtp_port" name="smtp_port" type="number" min="1" max="65535" required value="{{ old('smtp_port', $settings['smtp_port'] ?? 465) }}" />
                </div>
                <div class="field">
                  <label class="field__label" for="smtp_encryption">Encryption</label>
                  <select class="select" id="smtp_encryption" name="smtp_encryption" required>
                    <option value="ssl" {{ old('smtp_encryption', $settings['smtp_encryption'] ?? 'ssl') === 'ssl' ? 'selected' : '' }}>SSL</option>
                    <option value="tls" {{ old('smtp_encryption', $settings['smtp_encryption'] ?? 'ssl') === 'tls' ? 'selected' : '' }}>TLS</option>
                  </select>
                </div>
              </div>

              <div class="admin-form-grid admin-form-grid--two" style="margin-top: 10px;">
                <div class="field">
                  <label class="field__label" for="smtp_username">Username</label>
                  <input class="input" id="smtp_username" name="smtp_username" required value="{{ old('smtp_username', $settings['smtp_username'] ?? '') }}" autocomplete="off" />
                </div>
                <div class="field">
                  <label class="field__label" for="smtp_password">Password</label>
                  <input class="input" id="smtp_password" name="smtp_password" type="password" value="" placeholder="{{ ($settings['smtp_password_masked'] ?? '') ? 'Leave blank to keep existing password' : '' }}" autocomplete="new-password" />
                </div>
              </div>
            </div>
            <div class="app-modal__foot">
              <button type="submit" class="btn btn--primary" style="width: 100%;">Submit</button>
            </div>
          </form>
        </main>
@endsection
