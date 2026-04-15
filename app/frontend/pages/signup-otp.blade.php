@extends('auth')

@section('title', 'Verify email — ' . config('app.name'))

@section('content')
    <div class="login-page__brand">
      <span class="sr-only">{{ config('app.name') }}</span>
      @include('brand-logo')
    </div>
    <div class="login-page__panel">
      <div class="login-page__head login-page__head--center">
        <h1>Verify your email</h1>
        <p>Enter the 6-digit code we sent to <strong>{{ $maskedEmail }}</strong>.</p>
      </div>

      @if(session('success'))
      <div class="alert alert--success" role="status">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span>{{ session('success') }}</span>
      </div>
      @endif

      @if($errors->any())
      <div class="alert alert--error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <ul>
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
      @endif

      <form method="POST" action="{{ route('signup.otp.verify') }}">
        @csrf
        <div class="field">
          <label class="field__label" for="signup-otp-code">Verification code</label>
          <input
            class="input"
            id="signup-otp-code"
            type="text"
            name="otp_code"
            value="{{ old('otp_code') }}"
            inputmode="numeric"
            pattern="\d{6}"
            minlength="6"
            maxlength="6"
            placeholder="123456"
            required
            autofocus
          />
          <p class="field__hint">Code expires in {{ (int) ($otpExpiresMinutes ?? 10) }} minutes.</p>
        </div>

        <button type="submit" class="btn btn--primary login-page__submit">
          Verify and create account
        </button>
      </form>

      <form method="POST" action="{{ route('signup.otp.resend') }}" style="margin-top: 0.65rem;">
        @csrf
        <button type="submit" class="btn btn--ghost login-page__submit">Resend code</button>
      </form>

      <div class="login-page__meta-links" role="navigation" aria-label="Signup verification links">
        <a href="{{ route('signup') }}">Start over</a>
        <a href="{{ route('landing') }}">Back to home</a>
      </div>
    </div>
@endsection

