@extends('app')

@section('title', 'Manage Platforms — ' . config('app.name'))
@section('page-id', 'admin-platforms')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-plug"></i></div>
                <div>
                  <h1>Platforms</h1>
                  <p>Enable or disable social platforms globally. Only platforms with valid configuration credentials can be enabled.</p>
                </div>
              </div>
            </div>
          </div>

          @if(session('success'))
            <div class="alert alert--success">{{ session('success') }}</div>
          @endif

          <form method="POST" action="{{ route('admin.platforms.update') }}">
            @csrf
            <div class="admin-cards-grid">
              @foreach($allPlatforms as $platform)
                @php
                  $slug = $platform->value;
                  $configEnabled = config("platforms.{$slug}.enabled", false);
                  $isEnabled = in_array($slug, $enabledSlugs);
                  $plansUsing = $plans->filter(fn($p) => $p->allowed_platforms === null || in_array($slug, $p->allowed_platforms ?? []));
                @endphp
                <div class="card admin-platform-card {{ !$configEnabled ? 'admin-platform-card--disabled' : '' }}">
                  <div class="card__body">
                    <div class="admin-platform-card__header">
                      <i class="{{ $platform->icon() }} admin-platform-card__icon" aria-hidden="true"></i>
                      <div>
                        <strong>{{ $platform->label() }}</strong>
                        @if($configEnabled)
                          <span class="badge badge--success badge--sm">Configured</span>
                        @else
                          <span class="badge badge--muted badge--sm">Not configured</span>
                        @endif
                      </div>
                    </div>
                    <label class="admin-toggle">
                      <input type="checkbox" name="enabled[]" value="{{ $slug }}" {{ $isEnabled ? 'checked' : '' }} {{ !$configEnabled ? 'disabled' : '' }} />
                      <span class="admin-toggle__slider"></span>
                      <span>{{ $isEnabled ? 'Enabled' : 'Disabled' }}</span>
                    </label>
                    @if($plansUsing->isNotEmpty())
                      <p class="admin-platform-card__plans prose-muted">
                        Plans: {{ $plansUsing->pluck('name')->join(', ') }}
                      </p>
                    @endif
                  </div>
                </div>
              @endforeach
            </div>
            <div class="admin-form-footer">
              <button class="btn btn--primary" type="submit">Save platform settings</button>
            </div>
          </form>
        </main>
@endsection
