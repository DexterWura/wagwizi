@extends('app')

@section('title', 'Operations — ' . config('app.name'))
@section('page-id', 'admin-operations')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></div>
                <div>
                  <h1>Operations & Reliability</h1>
                  <p>Monitor failures, retry safely, and control publishing reliability policy.</p>
                </div>
              </div>
            </div>
          </div>

          @if(session('success'))
            <div class="alert alert--success">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert--danger">{{ session('error') }}</div>
          @endif

          <div class="grid-charts grid-charts--thirds">
            <div class="card"><div class="card__body"><strong>Expired tokens</strong><div class="stat-card__value">{{ $tokenHealth['expired'] }}</div></div></div>
            <div class="card"><div class="card__body"><strong>Expiring in 24h</strong><div class="stat-card__value">{{ $tokenHealth['expiring_24h'] }}</div></div></div>
            <div class="card"><div class="card__body"><strong>Expiring in 7d</strong><div class="stat-card__value">{{ $tokenHealth['expiring_7d'] }}</div></div></div>
          </div>

          <div class="card">
            <div class="card__head"><span>Reliability Policy</span></div>
            <div class="card__body">
              <form method="POST" action="{{ route('admin.operations.settings') }}">
                @csrf
                <div class="admin-form-grid">
                  <div class="field field--full">
                    <label class="field__label">Paused platforms</label>
                    <div class="admin-checkbox-grid">
                      @foreach($allPlatforms as $platform)
                        <label class="check-line">
                          <input type="checkbox" name="paused_platforms[]" value="{{ $platform->value }}" {{ in_array($platform->value, $pausedPlatforms, true) ? 'checked' : '' }} />
                          <span><i class="{{ $platform->icon() }}" aria-hidden="true"></i> {{ $platform->label() }}</span>
                        </label>
                      @endforeach
                    </div>
                  </div>
                  <div class="field">
                    <label class="field__label">Max tries</label>
                    <input class="input input--sm" type="number" name="max_tries" value="{{ $retryPolicy['max_tries'] ?? 3 }}" min="1" max="10" required />
                  </div>
                  <div class="field">
                    <label class="field__label">Backoff seconds (comma-separated)</label>
                    <input class="input input--sm" type="text" name="backoff_seconds" value="{{ implode(',', $retryPolicy['backoff_seconds'] ?? [10,30,90]) }}" required />
                  </div>
                  <div class="field field--full">
                    <label class="check-line">
                      <input type="hidden" name="text_only_fallback" value="0" />
                      <input type="checkbox" name="text_only_fallback" value="1" {{ !empty($retryPolicy['text_only_fallback']) ? 'checked' : '' }} />
                      <span>Enable media-failure fallback to text-only when possible</span>
                    </label>
                  </div>
                </div>
                <div class="admin-form-footer">
                  <button class="btn btn--primary" type="submit">Save policy</button>
                </div>
              </form>
            </div>
          </div>

          <div class="card">
            <div class="card__head"><span>Failed Publishes ({{ $failedPublishes->count() }})</span></div>
            <div class="card__body">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead><tr><th>ID</th><th>Platform</th><th>Error</th><th>Updated</th><th>Action</th></tr></thead>
                  <tbody>
                  @forelse($failedPublishes as $row)
                    <tr>
                      <td>#{{ $row->id }}</td>
                      <td>{{ $row->platform }}</td>
                      <td>{{ $row->error_message ?: 'Unknown' }}</td>
                      <td>{{ $row->updated_at?->diffForHumans() }}</td>
                      <td>
                        <form method="POST" action="{{ route('admin.operations.retry-publish') }}" class="inline-form">
                          @csrf
                          <input type="hidden" name="post_platform_id" value="{{ $row->id }}" />
                          <button class="btn btn--compact btn--primary" type="submit">Retry</button>
                        </form>
                      </td>
                    </tr>
                  @empty
                    <tr><td colspan="5" class="text-center prose-muted">No failed publishes.</td></tr>
                  @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card__head"><span>Failed Comments ({{ $failedComments->count() }})</span></div>
            <div class="card__body">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead><tr><th>ID</th><th>Platform</th><th>Error</th><th>Updated</th><th>Action</th></tr></thead>
                  <tbody>
                  @forelse($failedComments as $row)
                    <tr>
                      <td>#{{ $row->id }}</td>
                      <td>{{ $row->platform }}</td>
                      <td>{{ $row->comment_error_message ?: 'Unknown' }}</td>
                      <td>{{ $row->updated_at?->diffForHumans() }}</td>
                      <td>
                        <form method="POST" action="{{ route('admin.operations.retry-comment') }}" class="inline-form">
                          @csrf
                          <input type="hidden" name="post_platform_id" value="{{ $row->id }}" />
                          <button class="btn btn--compact btn--primary" type="submit">Retry</button>
                        </form>
                      </td>
                    </tr>
                  @empty
                    <tr><td colspan="5" class="text-center prose-muted">No failed comments.</td></tr>
                  @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </main>
@endsection

