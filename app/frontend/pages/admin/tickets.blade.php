@extends('app')

@section('title', 'Support Tickets — ' . config('app.name'))
@section('page-id', 'admin-tickets')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-life-ring"></i></div>
                <div>
                  <h1>Support Tickets</h1>
                  <p>View and respond to all support tickets.</p>
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
              <span>Tickets ({{ $tickets->total() }})</span>
              <form method="GET" class="admin-filter-bar admin-filter-bar--inline">
                <select class="select select--sm" name="status" onchange="this.form.submit()">
                  <option value="">All</option>
                  <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                  <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                  <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                  <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                </select>
              </form>
            </div>
            <div class="card__body">
              @forelse($tickets as $ticket)
              <div class="admin-ticket {{ $ticket->isOpen() ? 'admin-ticket--open' : '' }}">
                <div class="admin-ticket__header">
                  <div>
                    <strong>{{ $ticket->subject }}</strong>
                    <span class="badge badge--sm badge--{{ $ticket->status === 'open' ? 'warning' : ($ticket->status === 'in_progress' ? 'info' : 'muted') }}">{{ ucwords(str_replace('_', ' ', $ticket->status)) }}</span>
                    <span class="badge badge--sm badge--{{ $ticket->priority === 'high' ? 'danger' : ($ticket->priority === 'medium' ? 'warning' : 'muted') }}">{{ ucfirst($ticket->priority ?? 'normal') }}</span>
                  </div>
                  <span class="prose-muted">{{ $ticket->user?->name }} &middot; {{ $ticket->created_at->diffForHumans() }}</span>
                </div>
                <p class="admin-ticket__message">{{ $ticket->message }}</p>

                @if($ticket->replies->count())
                <div class="admin-ticket__replies">
                  @foreach($ticket->replies as $reply)
                  <div class="admin-ticket__reply">
                    <strong>{{ $reply->user?->name ?? 'Staff' }}</strong>
                    <span class="prose-muted">{{ $reply->created_at->diffForHumans() }}</span>
                    <p>{{ $reply->message }}</p>
                  </div>
                  @endforeach
                </div>
                @endif

                <div class="admin-ticket__actions">
                  <form method="POST" action="{{ route('admin.tickets.reply', $ticket->id) }}" class="admin-ticket__reply-form">
                    @csrf
                    <textarea class="input input--sm" name="message" rows="2" placeholder="Write a reply…" required></textarea>
                    <button class="btn btn--primary btn--compact" type="submit">Reply</button>
                  </form>
                  <form method="POST" action="{{ route('admin.tickets.status', $ticket->id) }}" class="inline-form">
                    @csrf
                    <select class="select select--xs" name="status" onchange="this.form.submit()">
                      @foreach(['open', 'in_progress', 'resolved', 'closed'] as $s)
                        <option value="{{ $s }}" {{ $ticket->status === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                      @endforeach
                    </select>
                  </form>
                </div>
              </div>
              @empty
                <p class="prose-muted text-center">No tickets found.</p>
              @endforelse

              @if($tickets->hasPages())
              <div class="admin-pagination">
                {{ $tickets->links() }}
              </div>
              @endif
            </div>
          </div>
        </main>
@endsection
