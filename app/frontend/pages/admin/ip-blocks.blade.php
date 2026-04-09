@extends('app')

@section('title', 'IP blocks - ' . config('app.name'))
@section('page-id', 'admin-ip-blocks')

@section('content')
<main class="app-content">
  <div class="page-head">
    <div class="page-head__row">
      <div class="page-head__title">
        <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-ban"></i></div>
        <div>
          <h1>IP Address Blocks</h1>
          <p>Block abusive IPs from accessing your site and APIs.</p>
        </div>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert--success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert--danger">{{ $errors->first() }}</div>
  @endif

  <div class="card">
    <div class="card__head"><span>Add block</span></div>
    <div class="card__body">
      <form method="POST" action="{{ route('admin.ip-blocks.store') }}">
        @csrf
        <div class="admin-form-grid">
          <div class="field">
            <label class="field__label">IP address</label>
            <input class="input input--sm" type="text" name="ip_address" required placeholder="203.0.113.10" />
          </div>
          <div class="field">
            <label class="field__label">Expires at (optional)</label>
            <input class="input input--sm" type="datetime-local" name="expires_at" />
          </div>
          <div class="field field--full">
            <label class="field__label">Reason (optional)</label>
            <input class="input input--sm" type="text" name="reason" maxlength="255" placeholder="Brute force attempts" />
          </div>
        </div>
        <div class="admin-form-footer">
          <button class="btn btn--primary" type="submit">Block IP</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><span>Blocked IPs ({{ method_exists($blocks, 'total') ? $blocks->total() : $blocks->count() }})</span></div>
    <div class="card__body">
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>IP</th>
              <th>Reason</th>
              <th>Expires</th>
              <th>Added by</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          @forelse($blocks as $row)
            <tr>
              <td>#{{ $row->id }}</td>
              <td>{{ $row->ip_address }}</td>
              <td>{{ $row->reason ?: '—' }}</td>
              <td>{{ $row->expires_at?->format('Y-m-d H:i') ?: 'Never' }}</td>
              <td>{{ $row->creator?->email ?: 'System' }}</td>
              <td>
                <form method="POST" action="{{ route('admin.ip-blocks.destroy', $row->id) }}" class="inline-form" onsubmit="return confirm('Unblock this IP address?');">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn--compact btn--ghost" type="submit">Unblock</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center prose-muted">No blocked IP addresses.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      @if(method_exists($blocks, 'links'))
        <div style="margin-top: 12px;">{{ $blocks->links() }}</div>
      @endif
    </div>
  </div>
</main>
@endsection

