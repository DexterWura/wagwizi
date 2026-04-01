@extends('install')

@section('title', 'Already Installed')
@section('step', '4')

@section('content')
  <div class="installer__success">
    <div class="installer__success-icon installer__success-icon--info">
      <i class="fa-solid fa-shield-check"></i>
    </div>
    <h2 class="installer__title">Application Already Installed</h2>
    <p class="installer__desc">
      This application has already been installed and is running.
      If you need to reinstall, delete the <code>secrets/installed</code> file from the server
      and visit this page again.
    </p>
    <div class="installer__actions installer__actions--center">
      <a href="/" class="btn btn--primary"><i class="fa-solid fa-house"></i> Go to site</a>
    </div>
  </div>
@endsection
