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

          <section class="card" style="margin-bottom: 18px;">
            <div class="card__head">
              <span>Tool Availability</span>
            </div>
            <div class="card__body">
              <form method="POST" action="{{ route('admin.plans.tools.update') }}">
                @csrf
                <div class="field field--full">
                  <label class="field__label">Globally enabled tools</label>
                  <div class="admin-checkbox-grid">
                    @foreach($toolCatalog as $toolSlug => $toolMeta)
                      <label class="check-line">
                        <input
                          type="checkbox"
                          name="enabled_tools[]"
                          value="{{ $toolSlug }}"
                          {{ in_array($toolSlug, $enabledToolSlugs ?? [], true) ? 'checked' : '' }}
                        />
                        <span>{{ $toolMeta['label'] }} <small style="opacity: .7;">({{ $toolMeta['category'] }})</small></span>
                      </label>
                    @endforeach
                  </div>
                  <p class="field__hint">Disable a tool here to hide it from all plans.</p>
                </div>
                <div class="admin-card-actions">
                  <button class="btn btn--primary btn--compact" type="submit">Save tool availability</button>
                </div>
              </form>
            </div>
          </section>

          <div class="admin-cards-grid">
            @foreach($plans as $plan)
            <div class="card">
              <div class="card__head">
                <span>{{ $plan->name }}</span>
                @if($plan->is_most_popular)
                  <span class="badge badge--success">Most popular</span>
                @endif
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
                <form method="POST" action="{{ route('admin.plans.update', $plan->id) }}" data-admin-plan-form>
                  @csrf
                  @method('PUT')
                  <input type="hidden" name="tools_present" value="1" />
                  <div class="admin-form-grid">
                    <div class="field">
                      <label class="field__label">Slug</label>
                      <input class="input input--sm" name="slug" value="{{ $plan->slug }}" required />
                    </div>
                    <div class="field">
                      <label class="field__label">Name</label>
                      <input class="input input--sm" name="name" value="{{ $plan->name }}" required />
                    </div>
                    @php
                      $showLifetimePriceRow = $plan->is_lifetime && ! $plan->is_free;
                    @endphp
                    <div class="field" data-admin-monthly-price-row style="{{ $showLifetimePriceRow ? 'display:none' : '' }}">
                      <label class="field__label" data-admin-monthly-price-label>{{ $plan->is_free ? 'Price (not used for free tier)' : 'Monthly price (minor units)' }}</label>
                      <span data-admin-monthly-price-mount>
                        @if(! $showLifetimePriceRow)
                          <input class="input input--sm" name="monthly_price_cents" type="number" value="{{ $plan->monthly_price_cents }}" data-admin-monthly-price-input />
                        @endif
                      </span>
                      <p class="field__hint" data-admin-monthly-price-hint>@if($plan->is_free)Free plans ignore price fields; both are cleared when you save.@else Smallest units of <strong>{{ $pricingBaseCurrency ?? 'USD' }}</strong> (e.g. cents). Set base currency under Admin → Payment gateways.@endif</p>
                    </div>
                    <div class="field" data-admin-yearly-price-row style="{{ $plan->is_lifetime || $plan->is_free ? 'display:none' : '' }}">
                      <label class="field__label">Yearly price (minor units)</label>
                      <input class="input input--sm" name="yearly_price_cents" type="number" value="{{ $plan->yearly_price_cents }}" data-admin-yearly-price-input />
                      <p class="field__hint">Optional. If empty, the app may derive an annual total from the monthly price for display.</p>
                    </div>
                    <div class="field" data-admin-lifetime-price-row style="{{ $showLifetimePriceRow ? '' : 'display:none' }}">
                      <label class="field__label">Lifetime price (minor units)</label>
                      <span data-admin-lifetime-price-mount>
                        @if($showLifetimePriceRow)
                          <input class="input input--sm" name="monthly_price_cents" type="number" value="{{ $plan->monthly_price_cents }}" data-admin-monthly-price-input />
                        @endif
                      </span>
                      <p class="field__hint">One-time lifetime payment in the smallest units of <strong>{{ $pricingBaseCurrency ?? 'USD' }}</strong> (e.g. cents).</p>
                    </div>
                    <div class="field">
                      <label class="field__label">Max profiles</label>
                      <input class="input input--sm" name="max_social_profiles" type="number" value="{{ $plan->max_social_profiles }}" placeholder="Unlimited" />
                    </div>
                    <div class="field">
                      <label class="field__label">Max posts/month</label>
                      <input class="input input--sm" name="max_scheduled_posts_per_month" type="number" value="{{ $plan->max_scheduled_posts_per_month }}" placeholder="Unlimited" />
                    </div>
                    <div class="field field--full">
                      <label class="field__label">Platform AI tokens / billing period</label>
                      <input class="input input--sm" name="platform_ai_tokens_per_period" type="number" min="0" max="999999999999" value="{{ (int) ($plan->platform_ai_tokens_per_period ?? 0) }}" required />
                      <p class="field__hint">Total API tokens (prompt + completion) subscribers may use per period with the <strong>admin</strong> API key. Set to <strong>0</strong> to disable platform AI on this plan (users must use their own key). Resets when they renew or when you change this value.</p>
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
                    <div class="field field--full">
                      <label class="field__label">Allowed tools</label>
                      <div class="admin-checkbox-grid">
                        @foreach($toolCatalog as $toolSlug => $toolMeta)
                          @php $isGloballyEnabled = in_array($toolSlug, $enabledToolSlugs ?? [], true); @endphp
                          <label class="check-line" style="{{ $isGloballyEnabled ? '' : 'opacity: 0.55;' }}">
                            <input
                              type="checkbox"
                              name="allowed_tools[]"
                              value="{{ $toolSlug }}"
                              {{ $plan->allowed_tools === null || in_array($toolSlug, $plan->allowed_tools ?? [], true) ? 'checked' : '' }}
                              {{ $isGloballyEnabled ? '' : 'disabled' }}
                            />
                            <span>{{ $toolMeta['label'] }}</span>
                          </label>
                        @endforeach
                      </div>
                      <p class="field__hint">Only globally enabled tools are assignable to plans.</p>
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
                        <input type="hidden" name="is_most_popular" value="0" />
                        <input type="checkbox" name="is_most_popular" value="1" {{ $plan->is_most_popular ? 'checked' : '' }} />
                        <span>Most popular (landing &amp; plans page highlight)</span>
                      </label>
                      <p class="field__hint">Only one plan should be marked; saving automatically clears this flag on all other plans.</p>
                    </div>
                    <div class="field">
                      <label class="check-line">
                        <input type="hidden" name="includes_replies" value="0" />
                        <input type="checkbox" name="includes_replies" value="1" {{ ($plan->includes_replies ?? true) ? 'checked' : '' }} />
                        <span>Includes first-comment / reply publishing (composer)</span>
                      </label>
                      <p class="field__hint">If unchecked, subscribers on this plan cannot add scheduled first comments on posts.</p>
                    </div>
                    <div class="field">
                      <label class="check-line">
                        <input type="hidden" name="includes_workflows" value="0" />
                        <input type="checkbox" name="includes_workflows" value="1" {{ ($plan->includes_workflows ?? false) ? 'checked' : '' }} />
                        <span>Includes workflows automation</span>
                      </label>
                      <p class="field__hint">If unchecked, subscribers cannot access the Workflows builder and execution features.</p>
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
                        <input type="checkbox" name="is_lifetime" value="1" data-admin-lifetime-toggle {{ $plan->is_lifetime ? 'checked' : '' }} />
                        <span>Lifetime deal (one-time payment; yearly price is ignored)</span>
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
          <div style="display:flex;align-items:center;gap:.5rem;">
            <button type="submit" form="add-plan-form" class="btn btn--primary btn--compact">Save plan</button>
            <button type="button" class="app-modal__close" data-app-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
          </div>
        </div>
        <form id="add-plan-form" class="app-modal__form" method="POST" action="{{ route('admin.plans.store') }}" data-admin-plan-form>
          @csrf
          <input type="hidden" name="tools_present" value="1" />
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
                <label class="check-line">
                  <input type="hidden" name="is_lifetime" value="0" />
                  <input type="checkbox" name="is_lifetime" value="1" data-admin-lifetime-toggle />
                  <span>Lifetime deal (one-time payment only)</span>
                </label>
              </div>
              <div class="field">
                <label class="field__label">Max lifetime subscribers</label>
                <input class="input" name="lifetime_max_subscribers" type="number" placeholder="Leave empty for unlimited" />
              </div>
              <div class="field" data-admin-monthly-price-row>
                <label class="field__label" data-admin-monthly-price-label>Monthly price (minor units)</label>
                <span data-admin-monthly-price-mount>
                  <input class="input" name="monthly_price_cents" type="number" placeholder="990" data-admin-monthly-price-input />
                </span>
                <p class="field__hint" data-admin-monthly-price-hint>Smallest units of <strong>{{ $pricingBaseCurrency ?? 'USD' }}</strong> (e.g. cents). Set base currency under Admin → Payment gateways.</p>
              </div>
              <div class="field" data-admin-yearly-price-row>
                <label class="field__label">Yearly price (minor units)</label>
                <input class="input" name="yearly_price_cents" type="number" placeholder="9900" data-admin-yearly-price-input />
                <p class="field__hint">Optional. Leave empty to derive from monthly for annual billing display.</p>
              </div>
              <div class="field" data-admin-lifetime-price-row style="display:none">
                <label class="field__label">Lifetime price (minor units)</label>
                <span data-admin-lifetime-price-mount></span>
                <p class="field__hint">One-time lifetime payment in the smallest units of <strong>{{ $pricingBaseCurrency ?? 'USD' }}</strong> (e.g. cents).</p>
              </div>
              <div class="field">
                <label class="field__label">Max profiles</label>
                <input class="input" name="max_social_profiles" type="number" placeholder="Unlimited" />
              </div>
              <div class="field">
                <label class="field__label">Max posts/month</label>
                <input class="input" name="max_scheduled_posts_per_month" type="number" placeholder="Unlimited" />
              </div>
              <div class="field field--full">
                <label class="field__label">Platform AI tokens / billing period</label>
                <input class="input" name="platform_ai_tokens_per_period" type="number" min="0" max="999999999999" value="0" required />
                <p class="field__hint">0 = no platform AI credits (BYOK only). Typical paid tiers: 50k–500k+ depending on pricing.</p>
              </div>
              <div class="field">
                <label class="field__label">Sort order</label>
                <input class="input" name="sort_order" type="number" value="0" />
              </div>
              <div class="field field--full">
                <label class="field__label">Features (one per line)</label>
                <textarea class="input" name="features" rows="3" placeholder="Feature 1&#10;Feature 2"></textarea>
              </div>
              <div class="field field--full">
                <label class="field__label">Allowed platforms</label>
                <div class="admin-checkbox-grid">
                  @foreach($enabledPlatforms as $slug)
                    @php $plat = \App\Services\Platform\Platform::tryFrom($slug); @endphp
                    @if($plat)
                      <label class="check-line">
                        <input type="checkbox" name="allowed_platforms[]" value="{{ $slug }}" checked />
                        <span><i class="{{ $plat->icon() }}" aria-hidden="true"></i> {{ $plat->label() }}</span>
                      </label>
                    @endif
                  @endforeach
                </div>
                <p class="field__hint">New plans start with access to every enabled platform.</p>
              </div>
              <div class="field field--full">
                <label class="field__label">Allowed tools</label>
                <div class="admin-checkbox-grid">
                  @foreach($toolCatalog as $toolSlug => $toolMeta)
                    @php $isGloballyEnabled = in_array($toolSlug, $enabledToolSlugs ?? [], true); @endphp
                    <label class="check-line" style="{{ $isGloballyEnabled ? '' : 'opacity: 0.55;' }}">
                      <input
                        type="checkbox"
                        name="allowed_tools[]"
                        value="{{ $toolSlug }}"
                        {{ $isGloballyEnabled ? 'checked' : '' }}
                        {{ $isGloballyEnabled ? '' : 'disabled' }}
                      />
                      <span>{{ $toolMeta['label'] }}</span>
                    </label>
                  @endforeach
                </div>
                <p class="field__hint">New plans start with all globally enabled tools selected.</p>
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
                  <input type="hidden" name="is_most_popular" value="0" />
                  <input type="checkbox" name="is_most_popular" value="1" />
                  <span>Most popular (landing &amp; plans page highlight)</span>
                </label>
                <p class="field__hint">Only one plan should be marked; saving clears this flag on all other plans.</p>
              </div>
              <div class="field">
                <label class="check-line">
                  <input type="hidden" name="includes_replies" value="0" />
                  <input type="checkbox" name="includes_replies" value="1" checked />
                  <span>Includes first-comment / reply publishing (composer)</span>
                </label>
                <p class="field__hint">Uncheck for entry tiers that should not use first comments on posts.</p>
              </div>
              <div class="field">
                <label class="check-line">
                  <input type="hidden" name="includes_workflows" value="0" />
                  <input type="checkbox" name="includes_workflows" value="1" />
                  <span>Includes workflows automation</span>
                </label>
                <p class="field__hint">Enable this when the plan should access the Workflows builder and automation runs.</p>
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

@push('scripts')
<script>
(function () {
  var cur = @json($pricingBaseCurrency ?? 'USD');
  function syncPlanPricingForm(form) {
    var life = form.querySelector('[data-admin-lifetime-toggle]');
    var free = form.querySelector('input[type="checkbox"][name="is_free"]');
    var yearlyRow = form.querySelector('[data-admin-yearly-price-row]');
    var monthlyRow = form.querySelector('[data-admin-monthly-price-row]');
    var lifetimeRow = form.querySelector('[data-admin-lifetime-price-row]');
    var monthlyMount = form.querySelector('[data-admin-monthly-price-mount]');
    var lifetimeMount = form.querySelector('[data-admin-lifetime-price-mount]');
    var input = form.querySelector('[data-admin-monthly-price-input]');
    var lbl = form.querySelector('[data-admin-monthly-price-label]');
    var hint = form.querySelector('[data-admin-monthly-price-hint]');
    var trialCb = form.querySelector('input[type="checkbox"][name="has_free_trial"]');
    var lifeOn = !!(life && life.checked);
    var freeOn = !!(free && free.checked);

    if (yearlyRow) {
      yearlyRow.style.display = lifeOn || freeOn ? 'none' : '';
    }
    if (monthlyRow) {
      monthlyRow.style.display = lifeOn && !freeOn ? 'none' : '';
    }
    if (lifetimeRow) {
      lifetimeRow.style.display = lifeOn && !freeOn ? '' : 'none';
    }
    if (input && monthlyMount && lifetimeMount) {
      if (freeOn || !lifeOn) {
        monthlyMount.appendChild(input);
      } else {
        lifetimeMount.appendChild(input);
      }
    }
    if (lbl) {
      if (freeOn) {
        lbl.textContent = 'Price (not used for free tier)';
      } else {
        lbl.textContent = 'Monthly price (minor units)';
      }
    }
    if (hint) {
      if (freeOn) {
        hint.textContent = 'Free plans ignore price fields; both are cleared when you save.';
      } else {
        hint.textContent = 'Smallest units of ' + cur + ' (e.g. cents). Set base currency under Admin → Payment gateways.';
      }
    }
    if (trialCb) {
      if (lifeOn) {
        trialCb.checked = false;
        trialCb.disabled = true;
      } else {
        trialCb.disabled = false;
      }
    }
  }
  document.querySelectorAll('[data-admin-plan-form]').forEach(function (form) {
    form.querySelectorAll('input[type="checkbox"][name="is_lifetime"], input[type="checkbox"][name="is_free"]').forEach(function (el) {
      el.addEventListener('change', function () {
        syncPlanPricingForm(form);
      });
    });
    syncPlanPricingForm(form);
  });
})();
</script>
@endpush
