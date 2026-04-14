@extends('app')

@section('title', 'Choose destinations — ' . config('app.name'))
@section('page-id', 'accounts-destinations')

@section('content')
    <main class="app-content">
      <div class="page-head">
        <div class="page-head__row">
          <div class="page-head__title">
            <div class="page-icon" aria-hidden="true">
              <i class="{{ $platform->icon() }}" aria-hidden="true"></i>
            </div>
            <div>
              <h1>Choose {{ $platform->label() }} destinations</h1>
              <p>Select one or more destinations to connect.</p>
            </div>
          </div>
        </div>
      </div>

      @if(session('error'))
      <div class="alert alert--error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span>{{ session('error') }}</span>
      </div>
      @endif

      @if($errors->any())
      <div class="alert alert--error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span>{{ $errors->first() }}</span>
      </div>
      @endif

      <form method="POST" action="{{ route('accounts.destinations.store', ['platform' => $platform->value]) }}">
        @csrf
        <div class="card">
          <div class="card__head">Available destinations</div>
          <div class="card__body">
            @forelse($destinations as $destination)
            @php
                $destinationKey = (string) ($destination['key'] ?? '');
                $destinationName = (string) ($destination['display_name'] ?? $destination['platform_user_id'] ?? 'Destination');
                $destinationType = (string) ($destination['metadata']['account_type'] ?? 'destination');
            @endphp
            <label class="input" style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
              <input type="checkbox" name="destinations[]" value="{{ $destinationKey }}" {{ in_array($destinationKey, (array) old('destinations', []), true) ? 'checked' : '' }} />
              <span style="display:flex; flex-direction:column;">
                <strong>{{ $destinationName }}</strong>
                <small class="prose-muted">{{ ucfirst($destinationType) }}</small>
              </span>
            </label>
            @empty
            <p class="prose-muted">No destinations available for this account.</p>
            @endforelse
          </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:12px;">
          <a class="btn btn--ghost" href="{{ route('accounts') }}">Cancel</a>
          <button type="submit" class="btn btn--primary">Connect selected destinations</button>
        </div>
      </form>
    </main>
@endsection
