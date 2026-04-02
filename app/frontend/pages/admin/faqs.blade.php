@extends('app')

@section('title', 'Manage FAQs — ' . config('app.name'))
@section('page-id', 'admin-faqs')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-circle-question"></i></div>
                <div>
                  <h1>FAQs</h1>
                  <p>Questions and answers shown on the landing page FAQ section.</p>
                </div>
              </div>
              <button class="btn btn--primary" data-app-modal-open="modal-add-faq">Add FAQ</button>
            </div>
          </div>

          @if(session('success'))
            <div class="alert alert--success">{{ session('success') }}</div>
          @endif

          <div class="admin-cards-grid">
            @forelse($faqs as $faq)
            <div class="card">
              <div class="card__body">
                <form method="POST" action="{{ route('admin.faqs.update', $faq->id) }}">
                  @csrf
                  @method('PUT')
                  <div class="admin-form-grid">
                    <div class="field field--full">
                      <label class="field__label">Question</label>
                      <input class="input input--sm" name="question" value="{{ $faq->question }}" required maxlength="500" />
                    </div>
                    <div class="field">
                      <label class="field__label">Sort order</label>
                      <input class="input input--sm" name="sort_order" type="number" min="0" value="{{ $faq->sort_order }}" />
                    </div>
                    <div class="field">
                      <label class="check-line">
                        <input type="hidden" name="is_active" value="0" />
                        <input type="checkbox" name="is_active" value="1" {{ $faq->is_active ? 'checked' : '' }} />
                        <span>Active</span>
                      </label>
                    </div>
                    <div class="field field--full">
                      <label class="field__label">Answer</label>
                      <textarea class="input input--sm" name="answer" rows="4" required>{{ $faq->answer }}</textarea>
                    </div>
                  </div>
                  <div class="admin-card-actions">
                    <button class="btn btn--primary btn--compact" type="submit">Save</button>
                    <button type="button" class="btn btn--ghost btn--compact btn--danger" onclick="if(confirm('Delete this FAQ?')){document.getElementById('delete-faq-{{ $faq->id }}').submit();}">Delete</button>
                  </div>
                </form>
                <form id="delete-faq-{{ $faq->id }}" method="POST" action="{{ route('admin.faqs.destroy', $faq->id) }}" class="hidden">@csrf @method('DELETE')</form>
              </div>
            </div>
            @empty
              <div class="card"><div class="card__body"><p class="prose-muted text-center">No FAQs yet. Add one to show the FAQ section on the landing page.</p></div></div>
            @endforelse
          </div>
        </main>
@endsection

@push('modals')
    <div class="app-modal" id="modal-add-faq" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-add-faq-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel app-modal__panel--wide">
        <div class="app-modal__head">
          <h2 id="modal-add-faq-title">Add FAQ</h2>
          <button type="button" class="app-modal__close" data-app-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.faqs.store') }}">
          @csrf
          <div class="app-modal__body">
            <div class="admin-form-grid">
              <div class="field field--full">
                <label class="field__label">Question</label>
                <input class="input" name="question" required maxlength="500" />
              </div>
              <div class="field">
                <label class="field__label">Sort order</label>
                <input class="input" name="sort_order" type="number" min="0" value="0" />
              </div>
              <div class="field">
                <label class="check-line">
                  <input type="hidden" name="is_active" value="0" />
                  <input type="checkbox" name="is_active" value="1" checked />
                  <span>Active</span>
                </label>
              </div>
              <div class="field field--full">
                <label class="field__label">Answer</label>
                <textarea class="input" name="answer" rows="4" required></textarea>
              </div>
            </div>
          </div>
          <div class="app-modal__foot">
            <button type="button" class="btn btn--ghost" data-app-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Add FAQ</button>
          </div>
        </form>
      </div>
    </div>
@endpush
