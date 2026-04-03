@extends('app')

@section('title', 'Payment transactions — ' . config('app.name'))
@section('page-id', 'admin-payment-transactions')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-receipt"></i></div>
                <div>
                  <h1>Payment transactions</h1>
                  <p>All checkout attempts: status, timing, user, gateway, and errors.</p>
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card__head">
              <span>Transactions ({{ $transactions->total() }})</span>
            </div>
            <div class="card__body">
              <form method="GET" class="admin-filter-bar" action="{{ route('admin.payment-transactions') }}">
                <input class="input input--sm" type="search" name="q" value="{{ request('q') }}" placeholder="Reference, processor ref, user id, email…" />
                <select class="select select--sm" name="status">
                  <option value="">All statuses</option>
                  <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                  <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                  <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
                <select class="select select--sm" name="gateway">
                  <option value="">All gateways</option>
                  <option value="paynow" {{ request('gateway') === 'paynow' ? 'selected' : '' }}>Paynow</option>
                  <option value="pesepay" {{ request('gateway') === 'pesepay' ? 'selected' : '' }}>Pesepay</option>
                </select>
                <label class="admin-filter-bar__date">
                  <span class="sr-only">From date</span>
                  <input class="input input--sm" type="date" name="date_from" value="{{ request('date_from') }}" title="Created on or after" />
                </label>
                <label class="admin-filter-bar__date">
                  <span class="sr-only">To date</span>
                  <input class="input input--sm" type="date" name="date_to" value="{{ request('date_to') }}" title="Created on or before" />
                </label>
                <button class="btn btn--primary btn--compact" type="submit">Filter</button>
                @if(request()->hasAny(['q', 'status', 'gateway', 'date_from', 'date_to']))
                  <a class="btn btn--ghost btn--compact" href="{{ route('admin.payment-transactions') }}">Clear</a>
                @endif
              </form>

              <div class="admin-table-wrap">
                <table class="admin-table admin-table--tx">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>User</th>
                      <th>Plan</th>
                      <th>Gateway</th>
                      <th>Amount</th>
                      <th>Status</th>
                      <th>Initiated</th>
                      <th>Completed</th>
                      <th>Failed</th>
                      <th>Error</th>
                      <th>Refs</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($transactions as $tx)
                    <tr>
                      <td>{{ $tx->id }}</td>
                      <td>
                        @if($tx->user)
                          <span title="{{ $tx->user->email }}">{{ $tx->user->name }}</span>
                          <div class="prose-muted admin-table__sub">{{ $tx->user->email }}</div>
                        @else
                          —
                        @endif
                      </td>
                      <td>{{ $tx->plan?->name ?? '—' }}</td>
                      <td>{{ $tx->gateway }}</td>
                      <td>{{ strtoupper((string) ($tx->currency ?? 'USD')) }} {{ number_format(($tx->amount_cents ?? 0) / 100, 2) }}</td>
                      <td>
                        @if($tx->status === 'completed')
                          <span class="admin-pill admin-pill--success">completed</span>
                        @elseif($tx->status === 'failed')
                          <span class="admin-pill admin-pill--danger">failed</span>
                        @else
                          <span class="admin-pill admin-pill--muted">pending</span>
                        @endif
                      </td>
                      <td>{{ $tx->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                      <td>
                        @if($tx->status === 'completed')
                          {{ $tx->completed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                        @else
                          —
                        @endif
                      </td>
                      <td>
                        @if($tx->status === 'failed')
                          {{ $tx->failed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                        @else
                          —
                        @endif
                      </td>
                      <td class="admin-table__error-cell">
                        @if($tx->status === 'failed')
                          {{ $tx->resolvedFailureMessage() ?? '—' }}
                        @else
                          —
                        @endif
                      </td>
                      <td class="admin-table__refs">
                        <span title="Our reference">{{ \Illuminate\Support\Str::limit($tx->reference, 24) }}</span>
                        @if($tx->paynow_reference)
                          <div class="prose-muted" title="Processor reference">{{ \Illuminate\Support\Str::limit($tx->paynow_reference, 28) }}</div>
                        @endif
                      </td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center prose-muted">No transactions match these filters.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>

              @if($transactions->hasPages())
              <div class="admin-pagination">
                {{ $transactions->links() }}
              </div>
              @endif
            </div>
          </div>
        </main>
@endsection
