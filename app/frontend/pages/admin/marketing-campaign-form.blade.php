@extends('app')

@section('title', ($campaign ? 'Edit campaign' : 'New campaign') . ' — ' . config('app.name'))
@section('page-id', 'admin-marketing-campaign-form')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-bullhorn"></i></div>
                <div>
                  <h1>{{ $campaign ? 'Edit campaign' : 'New campaign' }}</h1>
                  <p>Only users with marketing email opt-in receive sends.</p>
                </div>
              </div>
              <div class="page-head__actions">
                <a class="btn btn--secondary" href="{{ route('admin.marketing-campaigns.index') }}">Back</a>
              </div>
            </div>
          </div>

          @if(session('success'))
            <div class="alert alert--success">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert--danger">{{ session('error') }}</div>
          @endif

          @php
            $c = $campaign;
            $action = $c ? route('admin.marketing-campaigns.update', $c->id) : route('admin.marketing-campaigns.store');
            $method = $c ? 'PUT' : 'POST';
            $canEdit = !$c || in_array($c->status, ['draft', 'scheduled'], true);
          @endphp

          @if(!$canEdit)
            <div class="alert alert--danger">This campaign is read-only in its current state.</div>
          @endif

          <form method="POST" action="{{ $action }}" class="marketing-campaign-form">
            @csrf
            @if($method === 'PUT')
              @method('PUT')
            @endif
            <div class="grid-balance">
              <div>
                <div class="card">
                  <div class="card__head">Basics</div>
                  <div class="card__body">
                    <div class="field">
                      <label class="field__label" for="name">Name</label>
                      <input class="input" id="name" name="name" value="{{ old('name', $c?->name) }}" required {{ $canEdit ? '' : 'disabled' }} />
                    </div>
                    <div class="field">
                      <label class="field__label" for="template_key">Email template</label>
                      <select class="select" id="template_key" name="template_key" required {{ $canEdit ? '' : 'disabled' }}>
                        @foreach($templates as $tpl)
                          <option value="{{ $tpl->key }}" {{ old('template_key', $c?->template_key) === $tpl->key ? 'selected' : '' }}>{{ $tpl->key }} — {{ $tpl->name }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="field">
                      <label class="field__label" for="status">Status</label>
                      <select class="select" id="status" name="status" {{ $canEdit ? '' : 'disabled' }}>
                        <option value="draft" {{ old('status', $c?->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="scheduled" {{ old('status', $c?->status) === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                      </select>
                    </div>
                    <div class="field">
                      <label class="field__label" for="scheduled_at">Scheduled at (optional)</label>
                      <input class="input" id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $c?->scheduled_at?->format('Y-m-d\TH:i')) }}" {{ $canEdit ? '' : 'disabled' }} />
                    </div>
                  </div>
                </div>
              </div>
              <div>
                <div class="card">
                  <div class="card__head">Audience</div>
                  <div class="card__body">
                    @if($errors->has('segment'))
                      <div class="alert alert--danger">{{ $errors->first('segment') }}</div>
                    @endif
                    <label class="check-line check-line--spaced">
                      <input type="checkbox" name="seg_paid_subscribers" value="1" @checked(old('seg_paid_subscribers', $seg['paid'])) @disabled(!$canEdit) />
                      <span>Paid subscribers (non-free plan, active or trialing)</span>
                    </label>
                    <label class="check-line check-line--spaced">
                      <input type="checkbox" name="seg_free_only" value="1" @checked(old('seg_free_only', $seg['free_only'])) @disabled(!$canEdit) />
                      <span>Free plan only</span>
                    </label>
                    <div class="field">
                      <label class="field__label" for="seg_plan_slugs">Plan slugs (optional)</label>
                      <select class="select" id="seg_plan_slugs" name="seg_plan_slugs[]" multiple size="6" {{ $canEdit ? '' : 'disabled' }}>
                        @foreach($planSlugs as $slug)
                          <option value="{{ $slug }}" {{ in_array($slug, old('seg_plan_slugs', $seg['plan_slugs'] ?? []), true) ? 'selected' : '' }}>{{ $slug }}</option>
                        @endforeach
                      </select>
                      <p class="field__hint">Hold Ctrl/Cmd to select multiple.</p>
                    </div>
                    <div class="field">
                      <label class="field__label" for="seg_active_days">Active in last N days</label>
                      <input class="input" id="seg_active_days" type="number" min="0" name="seg_active_days" value="{{ old('seg_active_days', $seg['active_days']) }}" placeholder="e.g. 30" {{ $canEdit ? '' : 'disabled' }} />
                    </div>
                    <div class="field">
                      <label class="field__label" for="seg_inactive_days">Inactive for N days (no login)</label>
                      <input class="input" id="seg_inactive_days" type="number" min="0" name="seg_inactive_days" value="{{ old('seg_inactive_days', $seg['inactive_days']) }}" placeholder="e.g. 90" {{ $canEdit ? '' : 'disabled' }} />
                    </div>
                  </div>
                </div>
              </div>
            </div>
            @if($canEdit)
            <div class="admin-form-actions">
              <button type="submit" class="btn btn--primary">{{ $c ? 'Save' : 'Create' }}</button>
            </div>
            @endif
          </form>

          @if($c && $canEdit)
          <div class="card marketing-campaign-actions">
            <div class="card__head">Actions</div>
            <div class="card__body">
              <form method="POST" action="{{ route('admin.marketing-campaigns.test', $c->id) }}" class="inline-form">
                @csrf
                <button type="submit" class="btn btn--secondary">Send test to me</button>
              </form>
              <form method="POST" action="{{ route('admin.marketing-campaigns.send', $c->id) }}" class="inline-form" onsubmit="return confirm('Queue sends to all matching opted-in users?');">
                @csrf
                <button type="submit" class="btn btn--primary">Queue send</button>
              </form>
            </div>
          </div>
          @endif
        </main>
@endsection
