@extends('app')

@section('title', 'Marketing campaigns — ' . config('app.name'))
@section('page-id', 'admin-marketing-campaigns')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-bullhorn"></i></div>
                <div>
                  <h1>Marketing campaigns</h1>
                  <p>Build audience segments and send template-based email to opted-in users.</p>
                </div>
              </div>
              <div class="page-head__actions">
                <a class="btn btn--primary" href="{{ route('admin.marketing-campaigns.create') }}">New campaign</a>
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
            <div class="card__head">Campaigns</div>
            <div class="card__body">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Template</th>
                      <th>Status</th>
                      <th>Scheduled</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($campaigns as $c)
                    <tr>
                      <td>{{ $c->name }}</td>
                      <td><code>{{ $c->template_key ?? $c->emailTemplate?->key ?? '—' }}</code></td>
                      <td><span class="admin-pill admin-pill--{{ $c->status }}">{{ $c->status }}</span></td>
                      <td>{{ $c->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</td>
                      <td class="admin-table__actions">
                        <a class="btn btn--compact btn--secondary" href="{{ route('admin.marketing-campaigns.edit', $c->id) }}">Edit</a>
                        @if($c->status === 'draft')
                        <form method="POST" action="{{ route('admin.marketing-campaigns.destroy', $c->id) }}" class="inline-form" onsubmit="return confirm('Delete this draft campaign?');">
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="btn btn--compact btn--ghost">Delete</button>
                        </form>
                        @endif
                      </td>
                    </tr>
                    @empty
                    <tr><td colspan="5">No campaigns yet.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
              <div class="admin-pagination">{{ $campaigns->links() }}</div>
            </div>
          </div>
        </main>
@endsection
