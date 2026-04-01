@extends('install')

@section('title', 'Install — Database')
@section('step', '2')

@section('content')
  <h2 class="installer__title">Database Configuration</h2>
  <p class="installer__desc">Enter your MySQL database credentials. The database must already exist on your server.</p>

  @if ($errors->any())
  <div class="alert alert--error" role="alert">
    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
    <span>{{ $errors->first() }}</span>
  </div>
  @endif

  <form method="POST" action="{{ url('/install/database') }}">
    @csrf
    <div class="installer__field-row">
      <div class="field">
        <label class="field__label" for="db_host">Database Host</label>
        <input class="input" id="db_host" type="text" name="db_host" value="{{ old('db_host', '127.0.0.1') }}" required />
      </div>
      <div class="field" style="max-width: 120px;">
        <label class="field__label" for="db_port">Port</label>
        <input class="input" id="db_port" type="number" name="db_port" value="{{ old('db_port', '3306') }}" required />
      </div>
    </div>

    <div class="field">
      <label class="field__label" for="db_database">Database Name</label>
      <input class="input" id="db_database" type="text" name="db_database" value="{{ old('db_database', 'postai') }}" placeholder="postai" required />
    </div>

    <div class="field">
      <label class="field__label" for="db_username">Database Username</label>
      <input class="input" id="db_username" type="text" name="db_username" value="{{ old('db_username', 'root') }}" required />
    </div>

    <div class="field">
      <label class="field__label" for="db_password">Database Password</label>
      <input class="input" id="db_password" type="password" name="db_password" value="{{ old('db_password') }}" placeholder="Leave empty if none" />
    </div>

    <div class="installer__actions">
      <a href="{{ url('/install/requirements') }}" class="btn btn--outline"><i class="fa-solid fa-arrow-left"></i> Back</a>
      <button type="submit" class="btn btn--primary">Test &amp; Continue <i class="fa-solid fa-arrow-right"></i></button>
    </div>
  </form>
@endsection
