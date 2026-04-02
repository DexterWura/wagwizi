@extends('app')

@section('title', 'Support tickets — ' . config('app.name'))
@section('page-id', 'support-tickets')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-ticket"></i></div>
                <div>
                  <h1>Support tickets</h1>
                  <p>View your requests and continue the conversation with support.</p>
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

          <div class="card">
            <div class="card__head">
              <span>Your tickets ({{ $tickets->total() }})</span>
              <form method="GET" action="{{ route('support-tickets.index') }}" class="admin-filter-bar admin-filter-bar--inline">
                <label class="sr-only" for="ticket-status-filter">Filter by status</label>
                <select class="select select--sm" id="ticket-status-filter" name="status" onchange="this.form.submit()">
                  <option value="">All statuses</option>
                  <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Open (active)</option>
                  <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                  <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In progress</option>
                  <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                  <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                </select>
              </form>
            </div>
            <div class="card__body card__body--flush">
              <div class="table-wrap">
                <div class="table-scroll">
                  <table class="table">
                    <thead>
                      <tr>
                        <th scope="col">Ticket</th>
                        <th scope="col">Category</th>
                        <th scope="col">Status</th>
                        <th scope="col">Replies</th>
                        <th scope="col">Updated</th>
                        <th scope="col"><span class="sr-only">View</span></th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse($tickets as $ticket)
                      <tr>
                        <td>
                          <strong><a href="{{ route('support-tickets.show', $ticket->id) }}">#{{ $ticket->id }} — {{ $ticket->subject }}</a></strong>
                        </td>
                        <td>{{ ucfirst($ticket->category) }}</td>
                        <td>
                          <span class="badge badge--sm badge--{{ $ticket->status === 'open' ? 'warning' : ($ticket->status === 'in_progress' ? 'info' : ($ticket->status === 'resolved' ? 'success' : 'muted')) }}">{{ ucwords(str_replace('_', ' ', $ticket->status)) }}</span>
                        </td>
                        <td>{{ $ticket->replies_count }}</td>
                        <td class="prose-muted">{{ $ticket->updated_at->diffForHumans() }}</td>
                        <td><a class="btn btn--ghost btn--compact" href="{{ route('support-tickets.show', $ticket->id) }}">Open</a></td>
                      </tr>
                      @empty
                      <tr>
                        <td colspan="6" class="text-center prose-muted user-tickets-empty">
                          @if(request()->filled('status'))
                            No tickets match this filter.
                          @else
                            No tickets yet. Use the floating “Get help” button to submit one.
                          @endif
                        </td>
                      </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>
              @if($tickets->hasPages())
              <div class="admin-pagination user-tickets-pagination">
                {{ $tickets->links() }}
              </div>
              @endif
            </div>
          </div>
        </main>
@endsection
