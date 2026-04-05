@extends('auth')

@section('title', 'Forgot password — ' . config('app.name'))

@section('content')
    <div class="login-page__brand">
      <span class="sr-only">{{ config('app.name') }}</span>
      @include('brand-logo')
    </div>
    <div class="login-page__panel">
      <div class="login-page__head">
        <h1>Forgot password</h1>
        <p>Enter your account email and we will send a secure reset link.</p>
      </div>

      @if(session('success'))
      <div class="alert alert--success" role="alert">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span>{{ session('success') }}</span>
      </div>
      @endif

      @if($errors->any())
      <div class="alert alert--error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span>{{ $errors->first() }}</span>
      </div>
      @endif

      <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="field">
          <label class="field__label" for="forgot-email">Email</label>
          <input class="input" id="forgot-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com" required />
        </div>
        <div class="login-page__actions">
          <button type="submit" class="btn btn--primary login-page__submit">Send reset link</button>
        </div>
      </form>

      <div class="login-page__meta-links" role="navigation" aria-label="Authentication links">
        <a href="{{ route('login') }}">Back to sign in</a>
        <a href="{{ route('signup') }}">Create an account</a>
      </div>
    </div>
@endsection

