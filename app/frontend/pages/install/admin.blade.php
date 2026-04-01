@extends('install')

@section('title', 'Install — Admin Account')
@section('step', '3')

@section('content')
  <h2 class="installer__title">Create Admin Account</h2>
  <p class="installer__desc">This will be the super admin who manages the entire platform.</p>

  @if ($errors->any())
  <div class="alert alert--error" role="alert">
    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
    <ul>
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <form method="POST" action="{{ url('/install/admin') }}">
    @csrf

    <div class="field">
      <label class="field__label" for="app_url">Application URL</label>
      <input class="input" id="app_url" type="url" name="app_url" value="{{ old('app_url', $appUrl) }}" placeholder="https://yourdomain.com" required />
      <p class="field__hint">The public URL where this application will be accessed.</p>
    </div>

    <hr class="installer__divider" />

    <div class="field">
      <label class="field__label" for="admin_name">Admin Name</label>
      <input class="input" id="admin_name" type="text" name="admin_name" value="{{ old('admin_name') }}" placeholder="John Doe" autocomplete="name" required />
    </div>

    <div class="field">
      <label class="field__label" for="admin_email">Admin Email</label>
      <input class="input" id="admin_email" type="email" name="admin_email" value="{{ old('admin_email') }}" placeholder="admin@yourdomain.com" autocomplete="email" required />
    </div>

    <div class="field">
      <label class="field__label" for="admin_password">Password</label>
      <input class="input" id="admin_password" type="password" name="admin_password" autocomplete="new-password" placeholder="At least 8 characters" required />
    </div>

    <div class="field">
      <label class="field__label" for="admin_password_confirmation">Confirm Password</label>
      <input class="input" id="admin_password_confirmation" type="password" name="admin_password_confirmation" autocomplete="new-password" placeholder="Same password again" required />
    </div>

    <div class="installer__actions">
      <a href="{{ url('/install/database') }}" class="btn btn--outline"><i class="fa-solid fa-arrow-left"></i> Back</a>
      <button type="submit" class="btn btn--primary"><i class="fa-solid fa-rocket"></i> Install Now</button>
    </div>
  </form>
@endsection
