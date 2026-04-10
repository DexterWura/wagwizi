@extends('app')

@section('title', 'Join workspace — ' . config('app.name'))
@section('page-id', 'workspace-invite-accept')

@section('content')
  <main class="app-content app-content--settings">
    <div class="card">
      <div class="card__head">Join workspace</div>
      <div class="card__body">
        <p>You were invited to join <strong>{{ $workspaceName }}</strong> as <strong>{{ $invite->role }}</strong>.</p>
        <form method="POST" action="{{ route('workspace.invite.confirm') }}">
          @csrf
          <input type="hidden" name="token" value="{{ $invite->token }}" />
          <button type="submit" class="btn btn--primary">Confirm and join</button>
          <a href="{{ route('dashboard') }}" class="btn btn--ghost">Cancel</a>
        </form>
      </div>
    </div>
  </main>
@endsection

