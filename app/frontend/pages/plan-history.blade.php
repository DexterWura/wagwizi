@extends('app')

@section('title', 'Plan history — ' . config('app.name'))
@section('page-id', 'plan-history')

@section('content')
        <main class="app-content app-content--plan-history">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true">
                  <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                </div>
                <div>
                  <h1>Plan history</h1>
                  <p>View your plan change history here. For invoices and payment receipts, visit your billing settings.</p>
                </div>
              </div>
            </div>
            <div class="head-actions">
              <a class="btn btn--outline" href="{{ route('plans') }}"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Plans &amp; billing</a>
            </div>
          </div>

          <div class="card card--app-section">
            <div class="card__head">Change history</div>
            <div class="card__body card__body--flush">
          <div class="table-wrap" data-app-plan-history>
            <div class="table-toolbar">
              <p class="table-meta-note">
                <i class="fa-solid fa-database" aria-hidden="true"></i>
                <span>Newest first. Upgrade or downgrade on the Plans page to add entries.</span>
              </p>
            </div>
            <div class="table-scroll" data-app-plan-history-table-wrap hidden>
              <table class="table plan-history-table">
                <thead>
                  <tr>
                    <th scope="col">When</th>
                    <th scope="col">From</th>
                    <th scope="col">To</th>
                    <th scope="col">Type</th>
                  </tr>
                </thead>
                <tbody data-app-plan-history-body></tbody>
              </table>
            </div>
            <div class="table-empty" data-app-plan-history-empty>
              <i class="fa-solid fa-receipt" aria-hidden="true"></i>
              <h3>No plan changes yet</h3>
              <p>When you switch tiers on the Plans page, your history will show up here.</p>
              <a class="btn btn--primary" href="{{ route('plans') }}">Go to Plans</a>
            </div>
          </div>
            </div>
          </div>
        </main>
@endsection
