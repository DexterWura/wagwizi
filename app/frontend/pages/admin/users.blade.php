@extends('app')

@section('title', 'Manage Users — ' . config('app.name'))
@section('page-id', 'admin-users')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></div>
                <div>
                  <h1>Users</h1>
                  <p>Manage user roles, statuses, and accounts.</p>
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
              <span>All Users ({{ $users->total() }})</span>
            </div>
            <div class="card__body">
              <form method="GET" class="admin-filter-bar">
                <input class="input input--sm" type="search" name="search" value="{{ request('search') }}" placeholder="Search name or email…" />
                <select class="select select--sm" name="role">
                  <option value="">All roles</option>
                  <option value="user" {{ request('role') === 'user' ? 'selected' : '' }}>User</option>
                  <option value="support" {{ request('role') === 'support' ? 'selected' : '' }}>Support</option>
                  <option value="super_admin" {{ request('role') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                </select>
                <select class="select select--sm" name="status">
                  <option value="">All statuses</option>
                  <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                  <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                  <option value="banned" {{ request('status') === 'banned' ? 'selected' : '' }}>Banned</option>
                </select>
                <button class="btn btn--primary btn--compact" type="submit">Filter</button>
              </form>

              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Plan</th>
                      <th>Role</th>
                      <th>Status</th>
                      <th>Joined</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($users as $u)
                    <tr>
                      <td>{{ $u->name }}</td>
                      <td>{{ $u->email }}</td>
                      <td>{{ $u->subscription?->planModel?->name ?? '—' }}</td>
                      <td>
                        <form method="POST" action="{{ route('admin.users.role', $u->id) }}" class="inline-form">
                          @csrf
                          <select class="select select--xs" name="role" onchange="this.form.submit()">
                            @foreach(['user', 'support', 'super_admin'] as $r)
                              <option value="{{ $r }}" {{ $u->role === $r ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $r)) }}</option>
                            @endforeach
                          </select>
                        </form>
                      </td>
                      <td>
                        <form method="POST" action="{{ route('admin.users.status', $u->id) }}" class="inline-form">
                          @csrf
                          <select class="select select--xs" name="status" onchange="this.form.submit()">
                            @foreach(['active', 'suspended', 'banned'] as $s)
                              <option value="{{ $s }}" {{ $u->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                          </select>
                        </form>
                      </td>
                      <td>{{ $u->created_at->format('M j, Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center prose-muted">No users found.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>

              @if($users->hasPages())
              <div class="admin-pagination">
                {{ $users->links() }}
              </div>
              @endif
            </div>
          </div>
        </main>
@endsection
