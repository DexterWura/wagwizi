@extends('install')

@section('title', 'Install — Requirements')
@section('step', '1')

@section('content')
  <h2 class="installer__title">Server Requirements</h2>
  <p class="installer__desc">We need to check that your server meets the minimum requirements to run this application.</p>

  <div class="installer__section">
    <h3 class="installer__section-title">PHP Version</h3>
    <div class="installer__check">
      <span class="installer__check-icon installer__check-icon--{{ $checks['php_version']['passed'] ? 'ok' : 'fail' }}">
        <i class="fa-solid {{ $checks['php_version']['passed'] ? 'fa-check' : 'fa-xmark' }}"></i>
      </span>
      <span>PHP {{ $checks['php_version']['required'] }} or higher</span>
      <span class="installer__check-value">{{ $checks['php_version']['current'] }}</span>
    </div>
  </div>

  <div class="installer__section">
    <h3 class="installer__section-title">PHP Extensions</h3>
    @foreach ($checks['extensions'] as $ext)
    <div class="installer__check">
      <span class="installer__check-icon installer__check-icon--{{ $ext['loaded'] ? 'ok' : ($ext['required'] ? 'fail' : 'warn') }}">
        <i class="fa-solid {{ $ext['loaded'] ? 'fa-check' : 'fa-xmark' }}"></i>
      </span>
      <span>{{ $ext['name'] }}</span>
      @if (!$ext['required'])
        <span class="installer__tag">optional</span>
      @endif
      <span class="installer__check-value">{{ $ext['loaded'] ? 'Installed' : 'Missing' }}</span>
    </div>
    @endforeach
  </div>

  <div class="installer__section">
    <h3 class="installer__section-title">Directory Permissions</h3>
    <p class="installer__hint">These directories need to be writable by the web server (chmod 775 or 777).</p>
    @foreach ($checks['permissions'] as $perm)
    <div class="installer__check">
      <span class="installer__check-icon installer__check-icon--{{ $perm['writable'] ? 'ok' : 'fail' }}">
        <i class="fa-solid {{ $perm['writable'] ? 'fa-check' : 'fa-xmark' }}"></i>
      </span>
      <span>{{ $perm['directory'] }}</span>
      <span class="installer__check-value">{{ $perm['writable'] ? 'Writable' : 'Not writable' }}</span>
    </div>
    @endforeach
  </div>

  <div class="installer__actions">
    @if ($passed)
      <a href="{{ route('install.database') }}" class="btn btn--primary">Continue <i class="fa-solid fa-arrow-right"></i></a>
    @else
      <a href="{{ route('install.requirements') }}" class="btn btn--outline"><i class="fa-solid fa-rotate-right"></i> Re-check</a>
      <p class="installer__warn">Please fix the failed requirements above before continuing.</p>
    @endif
  </div>
@endsection
