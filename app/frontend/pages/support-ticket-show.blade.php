@extends('app')

@section('title', 'Ticket #' . $ticket->id . ' — ' . config('app.name'))
@section('page-id', 'support-tickets')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-ticket"></i></div>
                <div>
                  <h1>Ticket #{{ $ticket->id }}</h1>
                  <p>{{ $ticket->subject }}</p>
                </div>
              </div>
              <div class="head-actions">
                <a class="btn btn--outline" href="{{ route('support-tickets.index') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All tickets</a>
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
              <span>
                <span class="badge badge--sm badge--{{ $ticket->status === 'open' ? 'warning' : ($ticket->status === 'in_progress' ? 'info' : ($ticket->status === 'resolved' ? 'success' : 'muted')) }}">{{ ucwords(str_replace('_', ' ', $ticket->status)) }}</span>
                <span class="prose-muted">&middot; {{ ucfirst($ticket->category) }} &middot; {{ $ticket->created_at->format('M j, Y g:i a') }}</span>
              </span>
            </div>
            <div class="card__body">
              <div class="admin-ticket admin-ticket--user-thread">
                <div class="admin-ticket__reply">
                  <strong>You</strong>
                  <span class="prose-muted">{{ $ticket->created_at->diffForHumans() }}</span>
                  <p class="admin-ticket__message">{{ $ticket->message }}</p>
                </div>

                @foreach($ticket->replies as $reply)
                <div class="admin-ticket__reply">
                  <strong>
                    @if($reply->user_id === auth()->id())
                      You
                    @else
                      {{ $reply->user?->name ?? 'Support' }}
                    @endif
                  </strong>
                  <span class="prose-muted">{{ $reply->created_at->diffForHumans() }}</span>
                  <p class="user-ticket-reply-body">{{ $reply->message }}</p>
                </div>
                @endforeach
              </div>

              @if($ticket->status === 'closed')
              <p class="table-meta-note user-ticket-closed-note">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <span>This ticket is closed. You cannot add more replies.</span>
              </p>
              @else
              <form method="POST" action="{{ route('support-tickets.reply', $ticket->id) }}" class="admin-ticket__reply-form user-ticket-reply-form">
                @csrf
                <label class="field__label" for="ticket-reply-body">Your reply</label>
                <textarea class="input input--sm" id="ticket-reply-body" name="message" rows="4" placeholder="Write a message…" required></textarea>
                <div>
                  <button class="btn btn--primary" type="submit">Send reply</button>
                </div>
              </form>
              @endif
            </div>
          </div>
        </main>
@endsection
