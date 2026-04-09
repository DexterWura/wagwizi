@extends('app')

@section('title', 'Audit trail - ' . config('app.name'))
@section('page-id', 'admin-audit-trail')

@section('content')
<main class="app-content">
  <div class="page-head">
    <div class="page-head__row">
      <div class="page-head__title">
        <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-list-check"></i></div>
        <div>
          <h1>Audit Trail</h1>
          <p>Full site activity logs: auth, requests, and operational actions.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><span>Filters</span></div>
    <div class="card__body">
      <form method="GET">
        <div class="admin-form-grid">
          <div class="field">
            <label class="field__label">Category</label>
            <select class="select select--sm" name="category">
              <option value="">All</option>
              @foreach($categories as $cat)
                <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label class="field__label">Event</label>
            <input class="input input--sm" type="text" name="event" value="{{ request('event') }}" placeholder="login_success" />
          </div>
          <div class="field">
            <label class="field__label">Method</label>
            <select class="select select--sm" name="method">
              <option value="">Any</option>
              @foreach(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                <option value="{{ $method }}" {{ request('method') === $method ? 'selected' : '' }}>{{ $method }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label class="field__label">Status code</label>
            <input class="input input--sm" type="number" min="100" max="599" name="status_code" value="{{ request('status_code') }}" />
          </div>
          <div class="field">
            <label class="field__label">User ID</label>
            <input class="input input--sm" type="number" min="1" name="user_id" value="{{ request('user_id') }}" />
          </div>
          <div class="field">
            <label class="field__label">Path contains</label>
            <input class="input input--sm" type="text" name="path" value="{{ request('path') }}" placeholder="/admin" />
          </div>
        </div>
        <div class="admin-form-footer">
          <button class="btn btn--primary" type="submit">Apply filters</button>
          <a class="btn btn--ghost" href="{{ route('admin.audit-trail') }}">Reset</a>
          <a class="btn btn--outline" href="{{ route('admin.audit-trail.export', request()->query()) }}">Export CSV</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><span>Entries ({{ method_exists($events, 'total') ? $events->total() : $events->count() }})</span></div>
    <div class="card__body">
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>When</th>
              <th>Category</th>
              <th>Event</th>
              <th>Severity</th>
              <th>User</th>
              <th>Request</th>
              <th>Status</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
          @forelse($events as $entry)
            <tr>
              <td>{{ $entry->occurred_at?->format('Y-m-d H:i:s') }}</td>
              <td>{{ $entry->category }}</td>
              <td>{{ $entry->event }}</td>
              <td>
                @php
                  $severity = 'info';
                  $status = (int) ($entry->status_code ?? 0);
                  if ($status >= 500) $severity = 'critical';
                  elseif ($status >= 400) $severity = 'warning';
                  elseif (str_starts_with((string) $entry->event, 'login_failed')) $severity = 'warning';
                  elseif ((string) $entry->category === 'auth') $severity = 'security';
                @endphp
                <span class="badge
                  {{ $severity === 'critical' ? 'badge--danger' : '' }}
                  {{ $severity === 'warning' ? 'badge--warning' : '' }}
                  {{ $severity === 'security' ? 'badge--info' : '' }}
                ">{{ ucfirst($severity) }}</span>
              </td>
              <td>
                @if($entry->user)
                  #{{ $entry->user_id }} {{ $entry->user->email }}
                @else
                  —
                @endif
              </td>
              <td>
                {{ $entry->method ?: '—' }} {{ $entry->path ?: '—' }}
                @if($entry->route_name)
                  <div class="muted">{{ $entry->route_name }}</div>
                @endif
              </td>
              <td>{{ $entry->status_code ?: '—' }}</td>
              <td>{{ $entry->ip_address ?: '—' }}</td>
            </tr>
            @if(!empty($entry->metadata))
              <tr>
                <td colspan="8">
                  <details>
                    <summary>Metadata</summary>
                    <pre class="prose-muted" style="white-space: pre-wrap; margin-top: 8px;">{{ json_encode($entry->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                  </details>
                </td>
              </tr>
            @endif
          @empty
            <tr><td colspan="8" class="text-center prose-muted">No audit events found.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      @if(method_exists($events, 'links'))
        <div style="margin-top: 12px;">
          {{ $events->links() }}
        </div>
      @endif
    </div>
  </div>
</main>
@endsection

