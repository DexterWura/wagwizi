@extends('app')

@section('title', 'Migrations — ' . config('app.name'))
@section('page-id', 'admin-migrations')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-database"></i></div>
                <div>
                  <h1>Migrations</h1>
                  <p>Run, rollback, and inspect database migrations.</p>
                </div>
              </div>
              <div class="page-head__actions">
                <form method="POST" action="{{ route('admin.migrations.run') }}" class="inline-form">
                  @csrf
                  <button
                    class="btn btn--primary"
                    type="submit"
                    @if($pendingMigrationsCount === 0) disabled aria-disabled="true" title="No pending migrations"@endif
                    @if($pendingMigrationsCount > 0) onclick="return confirm('Run all pending migrations?')"@endif
                  >Run All Pending</button>
                </form>
                <form method="POST" action="{{ route('admin.migrations.rollback') }}" class="inline-form">
                  @csrf
                  <button class="btn btn--outline btn--danger" type="submit" onclick="return confirm('Rollback the last batch?')">Rollback Last Batch</button>
                </form>
              </div>
            </div>
          </div>

          @if(session('success'))
            <div class="alert alert--success">{{ session('success') }}</div>
          @endif
          @if(session('info'))
            <div class="alert alert--info">{{ session('info') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert--danger">{{ session('error') }}</div>
          @endif

          <div class="card">
            <div class="card__body">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Migration</th>
                      <th>Status</th>
                      <th>Batch</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($migrations as $m)
                    <tr>
                      <td class="admin-migration-name"><code>{{ $m['name'] }}</code></td>
                      <td>
                        @if($m['ran'])
                          <span class="badge badge--success">Ran</span>
                        @else
                          <span class="badge badge--warning">Pending</span>
                        @endif
                      </td>
                      <td>{{ $m['batch'] ?? '—' }}</td>
                      <td>
                        @if($m['ran'])
                          <form method="POST" action="{{ route('admin.migrations.rollback') }}" class="inline-form">
                            @csrf
                            <input type="hidden" name="migration" value="{{ $m['name'] }}" />
                            <button class="btn btn--ghost btn--compact btn--danger" type="submit" onclick="return confirm('Rollback {{ $m['name'] }}?')">Rollback</button>
                          </form>
                        @else
                          <form method="POST" action="{{ route('admin.migrations.run') }}" class="inline-form">
                            @csrf
                            <input type="hidden" name="migration" value="{{ $m['name'] }}" />
                            <button class="btn btn--ghost btn--compact btn--success" type="submit" onclick="return confirm('Run {{ $m['name'] }}?')">Run</button>
                          </form>
                        @endif
                      </td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </main>
@endsection
