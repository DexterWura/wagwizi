@extends('app')

@section('title', 'Manage Testimonials — ' . config('app.name'))
@section('page-id', 'admin-testimonials')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-quote-left"></i></div>
                <div>
                  <h1>Testimonials</h1>
                  <p>Manage testimonials shown on the landing page.</p>
                </div>
              </div>
              <button class="btn btn--primary" data-app-modal-open="modal-add-testimonial">Add testimonial</button>
            </div>
          </div>

          @if(session('success'))
            <div class="alert alert--success">{{ session('success') }}</div>
          @endif

          <div class="admin-cards-grid">
            @forelse($testimonials as $t)
            <div class="card">
              <div class="card__body">
                <form method="POST" action="{{ route('admin.testimonials.update', $t->id) }}">
                  @csrf
                  @method('PUT')
                  <div class="admin-form-grid">
                    <div class="field">
                      <label class="field__label">Author name</label>
                      <input class="input input--sm" name="author_name" value="{{ $t->author_name }}" required />
                    </div>
                    <div class="field">
                      <label class="field__label">Author title</label>
                      <input class="input input--sm" name="author_title" value="{{ $t->author_title }}" />
                    </div>
                    <div class="field">
                      <label class="field__label">Avatar URL</label>
                      <input class="input input--sm" name="author_avatar" type="url" value="{{ $t->author_avatar }}" />
                    </div>
                    <div class="field">
                      <label class="field__label">Rating</label>
                      <select class="select select--sm" name="rating">
                        @for($i = 1; $i <= 5; $i++)
                          <option value="{{ $i }}" {{ $t->rating === $i ? 'selected' : '' }}>{{ $i }} star{{ $i > 1 ? 's' : '' }}</option>
                        @endfor
                      </select>
                    </div>
                    <div class="field">
                      <label class="field__label">Sort order</label>
                      <input class="input input--sm" name="sort_order" type="number" value="{{ $t->sort_order }}" />
                    </div>
                    <div class="field">
                      <label class="check-line">
                        <input type="hidden" name="is_active" value="0" />
                        <input type="checkbox" name="is_active" value="1" {{ $t->is_active ? 'checked' : '' }} />
                        <span>Active</span>
                      </label>
                    </div>
                    <div class="field field--full">
                      <label class="field__label">Body</label>
                      <textarea class="input input--sm" name="body" rows="3" required>{{ $t->body }}</textarea>
                    </div>
                  </div>
                  <div class="admin-card-actions">
                    <button class="btn btn--primary btn--compact" type="submit">Save</button>
                    <button type="button" class="btn btn--ghost btn--compact btn--danger" onclick="if(confirm('Delete this testimonial?')){document.getElementById('delete-testimonial-{{ $t->id }}').submit();}">Delete</button>
                  </div>
                </form>
                <form id="delete-testimonial-{{ $t->id }}" method="POST" action="{{ route('admin.testimonials.destroy', $t->id) }}" class="hidden">@csrf @method('DELETE')</form>
              </div>
            </div>
            @empty
              <div class="card"><div class="card__body"><p class="prose-muted text-center">No testimonials yet. Add one to get started.</p></div></div>
            @endforelse
          </div>
        </main>
@endsection

@push('modals')
    <div class="app-modal" id="modal-add-testimonial" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-add-testimonial-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel app-modal__panel--wide">
        <div class="app-modal__head">
          <h2 id="modal-add-testimonial-title">Add Testimonial</h2>
          <button type="button" class="app-modal__close" data-app-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.testimonials.store') }}">
          @csrf
          <div class="app-modal__body">
            <div class="admin-form-grid">
              <div class="field">
                <label class="field__label">Author name</label>
                <input class="input" name="author_name" required />
              </div>
              <div class="field">
                <label class="field__label">Author title</label>
                <input class="input" name="author_title" />
              </div>
              <div class="field">
                <label class="field__label">Avatar URL</label>
                <input class="input" name="author_avatar" type="url" />
              </div>
              <div class="field">
                <label class="field__label">Rating</label>
                <select class="select" name="rating">
                  @for($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}">{{ $i }} star{{ $i > 1 ? 's' : '' }}</option>
                  @endfor
                </select>
              </div>
              <div class="field">
                <label class="field__label">Sort order</label>
                <input class="input" name="sort_order" type="number" value="0" />
              </div>
              <div class="field">
                <label class="check-line">
                  <input type="hidden" name="is_active" value="0" />
                  <input type="checkbox" name="is_active" value="1" checked />
                  <span>Active</span>
                </label>
              </div>
              <div class="field field--full">
                <label class="field__label">Body</label>
                <textarea class="input" name="body" rows="4" required></textarea>
              </div>
            </div>
          </div>
          <div class="app-modal__foot">
            <button type="button" class="btn btn--ghost" data-app-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Add testimonial</button>
          </div>
        </form>
      </div>
    </div>
@endpush
