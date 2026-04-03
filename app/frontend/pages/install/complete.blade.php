@extends('install')

@section('title', 'Install — Complete')
@section('step', '4')

@section('content')
  <div class="installer__success">
    <div class="installer__success-icon">
      <i class="fa-solid fa-circle-check"></i>
    </div>
    <h2 class="installer__title">Installation Complete</h2>
    <p class="installer__desc">
      Your application has been installed and is ready to use.
      All database tables have been created and your admin account is active.
    </p>
    <div class="installer__actions installer__actions--center">
      <a href="{{ $appUrl }}" class="btn btn--primary"><i class="fa-solid fa-arrow-right"></i> Go to your site</a>
    </div>
    <p class="installer__hint installer__hint--stack">
      You can sign in with the admin credentials you just created.
    </p>
  </div>
@endsection
