@extends('app')

@section('title', 'Email templates — ' . config('app.name'))
@section('page-id', 'admin-email-templates')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></div>
                <div>
                  <h1>Email templates</h1>
                  <p>Edit subjects and bodies. Keys are stable identifiers used by the application.</p>
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card__head">Templates</div>
            <div class="card__body">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Key</th>
                      <th>Name</th>
                      <th>System</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($templates as $t)
                    <tr>
                      <td><code>{{ $t->key }}</code></td>
                      <td>{{ $t->name }}</td>
                      <td>{{ $t->is_system ? 'Yes' : 'No' }}</td>
                      <td><a class="btn btn--compact btn--secondary" href="{{ route('admin.email-templates.edit', $t->id) }}">Edit</a></td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </main>
@endsection
