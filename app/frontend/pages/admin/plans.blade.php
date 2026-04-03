@extends('app')

@section('title', 'Manage Plans — ' . config('app.name'))
@section('page-id', 'admin-plans')

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-credit-card"></i></div>
                <div>
                  <h1>Plans</h1>
                  <p>Manage billing plans, pricing, and feature limits.</p>
                </div>
              </div>
              <button class="btn btn--primary" data-app-modal-open="modal-add-plan">Add plan</button>
            </div>
          </div>

          @if(session('success'))
            <div class="alert alert--success">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert--danger">{{ session('error') }}</div>
          @endif
          @if($errors->any())
            <div class="alert alert--danger">
              <ul class="admin-validation-errors">
                @foreach($errors->all() as $err)
                  <li>{{ $err }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="admin-cards-grid">
            @foreach($plans as $plan)
            <div class="card">
              <div class="card__head">
                <span>{{ $plan->name }}</span>
                @if($plan->is_lifetime)
                  <span class="badge badge--warning">Lifetime</span>
                @endif
                @if(!$plan->is_active)
                  <span class="badge badge--muted">Inactive</span>
                @endif
                @if($plan->is_free)
                  <span class="badge badge--info">Free</span>
                @endif
                @if($plan->freeTrialSummary())
                  <span class="badge badge--success">{{ $plan->free_trial_days }}d trial</span>
                @endif
              </div>
              <div class="card__body">
                <form method="POST" action="{{ route('admin.plans.update', $plan->id) }}">
                  @csrf
                  @method('PUT')
                  <div class="admin-form-grid">
                    <div class="field">
                      <label class="field__label">Slug</label>
                      <input class="input input--sm" name="slug" value="{{ $plan->slug }}" required />
                    </div>
                    <div class="field">
                      <label class="field__label">Name</label>
                      <input class="input input--sm" name="name" value="{{ $plan->name }}" required />
                    </div>
                    <div class="field">
                      <label class="field__label">Monthly price (minor units)</label>
                      <input class="input input--sm" name="monthly_price_cents" type="number" value="{{ $plan->monthly_price_cents }}" />
                      <p class="field__hint">Smallest units of <strong>{{ $pricingBaseCurrency ?? 'USD' }}</strong> (e.g. cents). Set base currency under Admin → Payment gateways.</p>
                    </div>
                    <div class="field">
                      <label class="field__label">Yearly price (minor units)</label>
                      <input class="input input--sm" name="yearly_price_cents" type="number" value="{{ $plan->yearly_price_cents }}" />
                    </div>
                    <div class="field">
                      <label class="field__label">Max profiles</label>
                      <input class="input input--sm" name="max_social_profiles" type="number" value="{{ $plan->max_social_profiles }}" placeholder="Unlimited" />
                    </div>
                    <div class="field">
                      <label class="field__label">Max posts/month</label>
                      <input class="input input--sm" name="max_scheduled_posts_per_month" type="number" value="{{ $plan->max_scheduled_posts_per_month }}" placeholder="Unlimited" />
                    </div>
                    <div class="field">
                      <label class="field__label">Sort order</label>
                      <input class="input input--sm" name="sort_order" type="number" value="{{ $plan->sort_order }}" />
                    </div>
                    <div class="field field--full">
                      <label class="field__label">Features (one per line)</label>
                      <textarea class="input input--sm" name="features" rows="3">{{ $plan->features ? implode("\n", $plan->features) : '' }}</textarea>
                    </div>
                    <div class="field field--full">
                      <label class="field__label">Allowed platforms</label>
                      <div class="admin-checkbox-grid">
                        @foreach($enabledPlatforms as $slug)
                          @php $plat = \App\Services\Platform\Platform::tryFrom($slug); @endphp
                          @if($plat)
                            <label class="check-line">
                              <input type="checkbox" name="allowed_platforms[]" value="{{ $slug }}" {{ $plan->allowed_platforms === null || in_array($slug, $plan->allowed_platforms ?? []) ? 'checked' : '' }} />
                              <span><i class="{{ $plat->icon() }}" aria-hidden="true"></i> {{ $plat->label() }}</span>
                            </label>
                          @endif
                        @endforeach
                      </div>
                      <p class="field__hint">Leave all checked to grant access to every enabled platform.</p>
                    </div>
                    <div class="field">
                      <label class="check-line">
                        <input type="hidden" name="is_active" value="0" />
                        <input type="checkbox" name="is_active" value="1" {{ $plan->is_active ? 'checked' : '' }} />
                        <span>Active</span>
                      </label>
                    </div>
                    <div class="field">
                      <label class="check-line">
                        <input type="hidden" name="is_free" value="0" />
                        <input type="checkbox" name="is_free" value="1" {{ $plan->is_free ? 'checked' : '' }} />
                        <span>Free tier (only one free plan; no trial on free)</span>
                      </label>
                    </div>
                    <div class="field">
                      <label class="check-line">
                        <input type="hidden" name="has_free_trial" value="0" />
                        <input type="checkbox" name="has_free_trial" value="1" {{ $plan->has_free_trial ? 'checked' : '' }} />
                        <span>Offers a free trial</span>
                      </label>
                    </div>
                    <div class="field">
                      <label class="field__label" for="free-trial-days-{{ $plan->id }}">Trial length (days)</label>
                      <input class="input input--sm" id="free-trial-days-{{ $plan->id }}" name="free_trial_days" type="number" min="1" max="366" value="{{ $plan->free_trial_days }}" placeholder="e.g. 14" />
                      <p class="field__hint">Required when free trial is enabled.</p>
                    </div>
                    <div class="field">
                      <label class="check-line">
                        <input type="hidden" name="is_lifetime" value="0" />
                        <input type="checkbox" name="is_lifetime" value="1" {{ $plan->is_lifetime ? 'checked' : '' }} />
                        <span>Lifetime deal</span>
                      </label>
                    </div>
                    @if($plan->is_lifetime)
                    <div class="field">
                      <label class="field__label">Max lifetime subscribers</label>
                      <input class="input input--sm" name="lifetime_max_subscribers" type="number" value="{{ $plan->lifetime_max_subscribers }}" />
                    </div>
                    <div class="field">
                      <label class="field__label">Current count</label>
                      <input class="input input--sm" type="number" value="{{ $plan->lifetime_current_count }}" disabled />
                    </div>
                    @endif
                  </div>
                  <div class="admin-card-actions">
                    <button class="btn btn--primary btn--compact" type="submit">Save</button>
                    <button type="button" class="btn btn--ghost btn--compact btn--danger" onclick="if(confirm('Delete this plan?')){document.getElementById('delete-plan-{{ $plan->id }}').submit();}">Delete</button>
                  </div>
                </form>
                <form id="delete-plan-{{ $plan->id }}" method="POST" action="{{ route('admin.plans.destroy', $plan->id) }}" class="hidden">@csrf @method('DELETE')</form>
              </div>
            </div>
            @endforeach
          </div>
        </main>
@endsection

@push('modals')
    <div class="app-modal" id="modal-add-plan" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-add-plan-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel app-modal__panel--wide">
        <div class="app-modal__head">
          <h2 id="modal-add-plan-title">Add Plan</h2>
          <button type="button" class="app-modal__close" data-app-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.plans.store') }}">
          @csrf
          <div class="app-modal__body">
            <div class="admin-form-grid">
              <div class="field">
                <label class="field__label">Slug</label>
                <input class="input" name="slug" required placeholder="e.g. pro" />
              </div>
              <div class="field">
                <label class="field__label">Name</label>
                <input class="input" name="name" required placeholder="e.g. Pro" />
              </div>
              <div class="field">
                <label class="field__label">Monthly price (minor units, {{ $pricingBaseCurrency ?? 'USD' }})</label>
                <input class="input" name="monthly_price_cents" type="number" placeholder="990" />
              </div>
              <div class="field">
                <label class="field__label">Yearly price (minor units)</label>
                <input class="input" name="yearly_price_cents" type="number" placeholder="9900" />
              </div>
              <div class="field">
                <label class="field__label">Max profiles</label>
                <input class="input" name="max_social_profiles" type="number" placeholder="Unlimited" />
              </div>
              <div class="field">
                <label class="field__label">Max posts/month</label>
                <input class="input" name="max_scheduled_posts_per_month" type="number" placeholder="Unlimited" />
              </div>
              <div class="field">
                <label class="field__label">Sort order</label>
                <input class="input" name="sort_order" type="number" value="0" />
              </div>
              <div class="field field--full">
                <label class="field__label">Features (one per line)</label>
                <textarea class="input" name="features" rows="3" placeholder="Feature 1&#10;Feature 2"></textarea>
              </div>
              <div class="field">
                <label class="check-line">
                  <input type="hidden" name="is_active" value="0" />
                  <input type="checkbox" name="is_active" value="1" checked />
                  <span>Active</span>
                </label>
              </div>
              <div class="field">
                <label class="check-line">
                  <input type="hidden" name="is_free" value="0" />
                  <input type="checkbox" name="is_free" value="1" />
                  <span>Free tier (only one; no trial on free)</span>
                </label>
              </div>
              <div class="field">
                <label class="check-line">
                  <input type="hidden" name="has_free_trial" value="0" />
                  <input type="checkbox" name="has_free_trial" value="1" />
                  <span>Offers a free trial</span>
                </label>
              </div>
              <div class="field">
                <label class="field__label" for="modal-free-trial-days">Trial length (days)</label>
                <input class="input" id="modal-free-trial-days" name="free_trial_days" type="number" min="1" max="366" placeholder="e.g. 14" />
              </div>
              <div class="field">
                <label class="check-line">
                  <input type="hidden" name="is_lifetime" value="0" />
                  <input type="checkbox" name="is_lifetime" value="1" />
                  <span>Lifetime deal</span>
                </label>
              </div>
              <div class="field">
                <label class="field__label">Max lifetime subscribers</label>
                <input class="input" name="lifetime_max_subscribers" type="number" placeholder="Leave empty if not lifetime" />
              </div>
            </div>
          </div>
          <div class="app-modal__foot">
            <button type="button" class="btn btn--ghost" data-app-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Create plan</button>
          </div>
        </form>
      </div>
    </div>
@endpush
