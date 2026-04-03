@extends('app')

@section('title', 'Edit template — ' . config('app.name'))
@section('page-id', 'admin-email-template-edit')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-pen-to-square"></i></div>
                <div>
                  <h1>Edit template</h1>
                  <p><code>{{ $template->key }}</code></p>
                </div>
              </div>
              <div class="page-head__actions">
                <a class="btn btn--secondary" href="{{ route('admin.email-templates.index') }}">Back to list</a>
                <a class="btn btn--secondary" href="{{ route('admin.email-templates.preview', $template->id) }}" target="_blank" rel="noopener">Preview sample</a>
              </div>
            </div>
          </div>

          @if(session('success'))
            <div class="alert alert--success">{{ session('success') }}</div>
          @endif

          <form method="POST" action="{{ route('admin.email-templates.update', $template->id) }}">
            @csrf
            @method('PUT')
            <div class="card">
              <div class="card__head">Content</div>
              <div class="card__body">
                <div class="field">
                  <label class="field__label" for="name">Label</label>
                  <input class="input" id="name" name="name" value="{{ old('name', $template->name) }}" required />
                </div>
                <div class="field">
                  <label class="field__label" for="subject">Subject (Blade)</label>
                  <input class="input" id="subject" name="subject" value="{{ old('subject', $template->subject) }}" required />
                </div>
                <div class="field">
                  <label class="field__label" for="body_html">Body HTML (Blade)</label>
                  <textarea class="input admin-email-template__body" id="body_html" name="body_html" rows="16" spellcheck="false" required>{{ old('body_html', $template->body_html) }}</textarea>
                </div>
                <div class="field">
                  <label class="field__label" for="body_text">Body plain text (optional)</label>
                  <textarea class="input" id="body_text" name="body_text" rows="8" spellcheck="false">{{ old('body_text', $template->body_text) }}</textarea>
                </div>
                <div class="field">
                  <label class="field__label" for="description">Description (admin)</label>
                  <textarea class="input" id="description" name="description" rows="2">{{ old('description', $template->description) }}</textarea>
                </div>
              </div>
            </div>
            <div class="admin-form-actions">
              <button type="submit" class="btn btn--primary">Save template</button>
            </div>
          </form>
        </main>
@endsection
