@extends('app')

@section('title', 'Notification deliveries — ' . config('app.name'))
@section('page-id', 'admin-notification-deliveries')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-paper-plane"></i></div>
                <div>
                  <h1>Delivery log</h1>
                  <p>Queued and sent notifications (email and SMS).</p>
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card__head">Filters</div>
            <div class="card__body">
              <form method="GET" action="{{ route('admin.notification-deliveries') }}" class="admin-filter-bar admin-filter-bar--wrap">
                <select class="select select--sm" name="channel">
                  <option value="">All channels</option>
                  <option value="email" {{ ($filters['channel'] ?? '') === 'email' ? 'selected' : '' }}>Email</option>
                  <option value="sms" {{ ($filters['channel'] ?? '') === 'sms' ? 'selected' : '' }}>SMS</option>
                </select>
                <input class="input input--sm" type="text" name="template_key" value="{{ $filters['template_key'] ?? '' }}" placeholder="Template key" />
                <select class="select select--sm" name="status">
                  <option value="">All statuses</option>
                  <option value="queued" {{ ($filters['status'] ?? '') === 'queued' ? 'selected' : '' }}>Queued</option>
                  <option value="sent" {{ ($filters['status'] ?? '') === 'sent' ? 'selected' : '' }}>Sent</option>
                  <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
                <input class="input input--sm" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" />
                <input class="input input--sm" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" />
                <input class="input input--sm" type="search" name="user_search" value="{{ $filters['user_search'] ?? '' }}" placeholder="User / address" />
                <button class="btn btn--primary btn--compact" type="submit">Apply</button>
              </form>
            </div>
          </div>

          <div class="card">
            <div class="card__head">Deliveries ({{ $deliveries->total() }})</div>
            <div class="card__body">
              <div class="admin-table-wrap">
                <table class="admin-table admin-table--deliveries">
                  <thead>
                    <tr>
                      <th>When</th>
                      <th>Channel</th>
                      <th>Template</th>
                      <th>To</th>
                      <th>User</th>
                      <th>Status</th>
                      <th>Error</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($deliveries as $d)
                    <tr>
                      <td>{{ $d->created_at?->format('Y-m-d H:i') }}</td>
                      <td>{{ $d->channel }}</td>
                      <td><code>{{ $d->template_key ?? '—' }}</code></td>
                      <td>{{ $d->to_address ?? $d->to_phone ?? '—' }}</td>
                      <td>@if($d->user){{ $d->user->email }}@else — @endif</td>
                      <td><span class="admin-pill admin-pill--{{ $d->status }}">{{ $d->status }}</span></td>
                      <td class="admin-table__cell-clip" title="{{ $d->error_message }}">{{ $d->error_message ? \Illuminate\Support\Str::limit($d->error_message, 80) : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7">No deliveries yet.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
              <div class="admin-pagination">
                {{ $deliveries->appends(request()->query())->links() }}
              </div>
            </div>
          </div>
        </main>
@endsection
