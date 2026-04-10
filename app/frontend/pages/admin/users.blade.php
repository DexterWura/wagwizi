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
          @if(session()->has('impersonator_id'))
            <div class="alert alert--info">
              You are currently logged in as another user.
              <form method="POST" action="{{ route('impersonation.leave') }}" class="inline-form" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn--outline btn--compact">Return to admin account</button>
              </form>
            </div>
          @endif

          <div class="card">
            <div class="card__head">
              <span>
                All Users ({{ $users->total() }})
                <span class="badge badge--warning" style="margin-left:8px;">Trialing users: {{ (int) ($trialingUsersCount ?? 0) }}</span>
              </span>
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
                  <option value="trialing" {{ request('status') === 'trialing' ? 'selected' : '' }}>Trialing subscription</option>
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
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($users as $u)
                    <tr>
                      <td>{{ $u->name }}</td>
                      <td>{{ $u->email }}</td>
                      <td>
                        {{ $u->subscription?->planModel?->name ?? '—' }}
                        @if(($u->subscription?->status ?? '') === 'trialing' && $u->subscription?->trial_ends_at)
                          @php
                            $daysLeft = max(0, now()->startOfDay()->diffInDays($u->subscription->trial_ends_at->copy()->startOfDay(), false));
                          @endphp
                          <div class="prose-muted" style="font-size:12px;">
                            Trial ends {{ $u->subscription->trial_ends_at->format('M j, Y') }}
                            <span class="badge badge--warning" style="margin-left:6px;">
                              {{ $daysLeft === 0 ? 'Ends today' : $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left' }}
                            </span>
                          </div>
                        @endif
                      </td>
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
                      <td>
                        <div style="display:grid; gap:8px; min-width: 210px;">
                          <form method="POST" action="{{ route('admin.users.login-as', $u->id) }}" class="inline-form">
                            @csrf
                            <button class="btn btn--outline btn--compact" type="submit" {{ auth()->id() === $u->id ? 'disabled' : '' }}>
                              Login as user
                            </button>
                          </form>

                          <form method="POST" action="{{ route('admin.users.plan', $u->id) }}" class="inline-form" style="display:grid; gap:6px;">
                            @csrf
                            <div style="display:flex; gap:6px; align-items:center;">
                              <select class="select select--xs" name="plan_id" required>
                                <option value="">Select plan</option>
                                @foreach(($plans ?? collect()) as $planOption)
                                  <option value="{{ $planOption->id }}">{{ $planOption->name }}</option>
                                @endforeach
                              </select>
                              <input class="input input--xs" name="trial_days" type="number" min="1" max="3650" placeholder="Trial days" style="width:96px;" />
                            </div>
                            <div style="display:flex; gap:6px; align-items:center;">
                              <button class="btn btn--outline btn--compact" type="submit" name="action" value="change">Change</button>
                              <button class="btn btn--primary btn--compact" type="submit" name="action" value="gift">Gift</button>
                              <button class="btn btn--outline btn--compact" type="submit" name="action" value="trial">Trial</button>
                            </div>
                          </form>
                        </div>
                      </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center prose-muted">No users found.</td></tr>
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
