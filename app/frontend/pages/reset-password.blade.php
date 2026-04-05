@extends('auth')

@section('title', 'Reset password — ' . config('app.name'))

@section('content')
    <div class="login-page__brand">
      <span class="sr-only">{{ config('app.name') }}</span>
      @include('brand-logo')
    </div>
    <div class="login-page__panel">
      <div class="login-page__head">
        <h1>Reset password</h1>
        <p>Create a new password for your account.</p>
      </div>

      @if($errors->any())
      <div class="alert alert--error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span>{{ $errors->first() }}</span>
      </div>
      @endif

      <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}" />
        <div class="field">
          <label class="field__label" for="reset-email">Email</label>
          <input class="input" id="reset-email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required />
        </div>
        <div class="field">
          <label class="field__label" for="reset-password">New password</label>
          <input class="input" id="reset-password" type="password" name="password" autocomplete="new-password" placeholder="At least 8 characters" required />
        </div>
        <div class="field">
          <label class="field__label" for="reset-password-confirm">Confirm new password</label>
          <input class="input" id="reset-password-confirm" type="password" name="password_confirmation" autocomplete="new-password" placeholder="Repeat your password" required />
        </div>
        <div class="login-page__actions">
          <button type="submit" class="btn btn--primary login-page__submit">Update password</button>
        </div>
      </form>

      <div class="login-page__meta-links" role="navigation" aria-label="Authentication links">
        <a href="{{ route('login') }}">Back to sign in</a>
        <a href="{{ route('password.request') }}">Request a new link</a>
      </div>
    </div>
@endsection

