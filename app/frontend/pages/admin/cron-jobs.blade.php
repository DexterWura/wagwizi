@extends('app')

@section('title', 'Cron jobs — ' . config('app.name'))
@section('page-id', 'admin-cron-jobs')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-clock"></i></div>
                <div>
                  <h1>Cron jobs</h1>
                  <p>Manage task schedules, run tasks on demand, and copy your cPanel cron command.</p>
                </div>
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
          @if($errors->any())
            <div class="alert alert--danger">
              <ul class="admin-validation-errors" style="margin:0;padding-left:1.25rem;">
                @foreach($errors->all() as $err)
                  <li>{{ $err }}</li>
                @endforeach
              </ul>
            </div>
          @endif
          @if(session('new_cron_secret_once'))
            <div class="alert alert--warning">
              <p style="margin:0 0 0.5rem;"><strong>Copy this token now</strong> (shown only this once). It is already saved in the database.</p>
              <input class="input" type="text" readonly value="{{ session('new_cron_secret_once') }}" onclick="this.select();" />
            </div>
          @endif

          <div class="card">
            <div class="card__head"><span>Cron token</span></div>
            <div class="card__body">
              <p class="field__hint">
                Active token is read from the <strong>database</strong> when set here (stored encrypted). If none is stored, the app uses <code class="admin-code-tag">CRON_SECRET</code> from <code class="admin-code-tag">secrets/.env</code>.
                Current source:
                @if($cronSecretFromDb)
                  <strong>Database</strong>
                @elseif($cronSecret !== '')
                  <strong>Environment</strong> (<code class="admin-code-tag">CRON_SECRET</code>)
                @else
                  <strong>Not configured</strong> — save a token below or set <code class="admin-code-tag">CRON_SECRET</code>.
                @endif
              </p>
              <form method="POST" action="{{ route('admin.cron-jobs.secret') }}" class="admin-form-grid" style="margin-bottom:1rem;">
                @csrf
                <div class="field field--full">
                  <label class="field__label" for="cron-secret-input">New token (min 16 characters, no spaces)</label>
                  <input class="input" id="cron-secret-input" type="password" name="cron_secret" autocomplete="new-password" placeholder="Leave blank if only generating or clearing" />
                </div>
                <div class="field field--full admin-card-actions" style="margin:0;">
                  <button class="btn btn--primary btn--compact" type="submit">Save token to database</button>
                  <button class="btn btn--outline btn--compact" type="submit" name="generate_cron_secret" value="1">Generate random &amp; save</button>
                </div>
              </form>
              @if($cronSecretFromDb)
              <form method="POST" action="{{ route('admin.cron-jobs.secret') }}" onsubmit="return confirm('Remove the token from the database and use CRON_SECRET from the server environment (if any)?');">
                @csrf
                <button class="btn btn--ghost btn--compact btn--danger" type="submit" name="clear_stored_cron_secret" value="1">Clear stored token</button>
              </form>
              @endif
            </div>
          </div>

          <div class="card">
            <div class="card__head"><span>cPanel cron command</span></div>
            <div class="card__body">
              @if($cronSecret !== '')
                <p class="field__hint">Set this command in cPanel to run every minute (or as needed).</p>
                <div class="field">
                  <input class="input" type="text" readonly value="{{ $cpanelCommand }}" onclick="this.select();" />
                </div>
                <p class="field__hint">Uses <code class="admin-code-tag">GET /cron?token=…</code> with the active token. Advanced (POST): <span class="admin-mono">{{ $cronApiUrl }}</span> with header <code class="admin-code-tag">X-Cron-Secret</code>.</p>
              @else
                <div class="alert alert--warning">
                  <strong>No cron token is available yet.</strong>
                  Save a token in the section above, or set <code class="admin-code-tag">CRON_SECRET</code> in <code class="admin-code-tag">secrets/.env</code>.
                </div>
              @endif
            </div>
          </div>

          <div class="card">
            <div class="card__head">
              <span>Scheduled tasks</span>
              <form method="POST" action="{{ url('/admin/cron-jobs/run-due') }}" class="inline-form">
                @csrf
                <button class="btn btn--outline btn--compact" type="submit">Run due tasks now</button>
              </form>
            </div>
            <div class="card__body">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Task</th>
                      <th>Status</th>
                      <th>Last run</th>
                      <th>Duration</th>
                      <th>Settings</th>
                      <th>Run</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($tasks as $task)
                    <tr>
                      <td>
                        <strong>{{ $task->label }}</strong><br />
                        <span class="field__hint">{{ $task->description ?: $task->key }}</span>
                      </td>
                      <td>{{ $task->last_status ?? 'never' }}</td>
                      <td>{{ $task->last_ran_at ? $task->last_ran_at->diffForHumans() : 'Never' }}</td>
                      <td>{{ $task->last_duration_ms !== null ? $task->last_duration_ms . ' ms' : '—' }}</td>
                      <td>
                        <form method="POST" action="{{ url('/admin/cron-jobs/'.$task->id) }}" class="inline-form" style="display:flex; gap:8px; align-items:center;">
                          @csrf
                          <label class="check-line" style="margin:0;">
                            <input type="hidden" name="enabled" value="0" />
                            <input type="checkbox" name="enabled" value="1" {{ $task->enabled ? 'checked' : '' }} />
                            <span>Enabled</span>
                          </label>
                          <input class="input input--sm" type="number" name="interval_minutes" min="1" max="10080" value="{{ $task->interval_minutes }}" style="max-width:120px;" />
                          <button class="btn btn--primary btn--compact" type="submit">Save</button>
                        </form>
                      </td>
                      <td>
                        <form method="POST" action="{{ url('/admin/cron-jobs/'.$task->id.'/run') }}" class="inline-form">
                          @csrf
                          <button class="btn btn--outline btn--compact" type="submit">Run now</button>
                        </form>
                      </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center prose-muted">No cron tasks found.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card__head"><span>Run history (latest 200)</span></div>
            <div class="card__body">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>When</th>
                      <th>Task</th>
                      <th>Status</th>
                      <th>Duration</th>
                      <th>Output</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($runs as $run)
                    <tr>
                      <td>{{ $run->ran_at?->diffForHumans() ?? $run->created_at?->diffForHumans() ?? '—' }}</td>
                      <td>{{ $run->cronTask?->label ?? $run->task_key }}</td>
                      <td>{{ $run->status }}</td>
                      <td>{{ $run->duration_ms !== null ? $run->duration_ms . ' ms' : '—' }}</td>
                      <td>{{ $run->output ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center prose-muted">No cron runs logged yet.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </main>
@endsection

