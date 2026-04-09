@extends('app')

@section('title', 'Workflows — ' . config('app.name'))
@section('page-id', 'workflows')

@section('content')
<main class="app-content" data-app-workflows>
  <div class="page-head">
    <div class="page-head__title">
      <div class="page-icon" aria-hidden="true"><i class="fa-solid fa-diagram-project"></i></div>
      <div>
        <h1>Workflows</h1>
        <p>Build automation flows with triggers, AI steps, and publishing nodes.</p>
      </div>
    </div>
  </div>

  <section class="card" data-workflows-root>
    <div class="card__head">
      <span>Workflow Builder</span>
      <div class="app-modal__actions">
        <button class="btn btn--ghost btn--compact" type="button" data-workflows-new>New</button>
        <button class="btn btn--ghost btn--compact" type="button" data-workflows-run>Run now</button>
        <button class="btn btn--primary btn--compact" type="button" data-workflows-save>Save</button>
      </div>
    </div>
    <div class="card__body">
      <div class="field field--full">
        <label class="field__label" for="workflow-name">Name</label>
        <input class="input" id="workflow-name" type="text" placeholder="e.g. AI daily campaign workflow" />
      </div>
      <div class="field field--full">
        <label class="field__label" for="workflow-template">Quick start template</label>
        <select class="select" id="workflow-template" data-workflow-template-select>
          <option value="">Start from blank</option>
          @foreach($workflowTemplates as $template)
            <option value="{{ $template['key'] }}">{{ $template['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="field field--full">
        <label class="field__label" for="workflow-trigger">Trigger</label>
        <select class="select" id="workflow-trigger" data-workflow-trigger>
          <option value="manual">Manual</option>
          <option value="schedule">Scheduled</option>
          <option value="event">Event</option>
        </select>
      </div>
      <div class="workflows-layout">
        <aside class="workflows-panel">
          <h3>Nodes</h3>
          <div class="workflows-node-list" data-workflow-node-palette></div>
        </aside>
        <section class="workflows-canvas" data-workflow-canvas aria-label="Workflow canvas">
          <p class="muted">Drag nodes here (double-click node to edit config JSON).</p>
        </section>
        <aside class="workflows-panel">
          <h3>Inspector</h3>
          <p class="muted" data-workflow-inspector-empty>Select a node to edit configuration.</p>
          <textarea class="textarea" rows="14" data-workflow-node-config hidden></textarea>
          <button type="button" class="btn btn--primary btn--compact" data-workflow-node-config-save hidden>Apply node config</button>
        </aside>
      </div>
      <div class="field field--full">
        <label class="field__label">Connected accounts for node setup</label>
        <div class="admin-checkbox-grid">
          @foreach($workflowAccounts as $acc)
            <label class="check-line">
              <input type="checkbox" value="{{ $acc->id }}" data-workflow-account />
              <span>{{ ucfirst($acc->platform) }} — {{ $acc->display_name ?? $acc->username ?? ('Account #' . $acc->id) }}</span>
            </label>
          @endforeach
        </div>
      </div>
      <div class="field field--full">
        <label class="field__label">Saved workflows</label>
        <div data-workflow-list class="workflows-list"></div>
      </div>
      <div class="field field--full">
        <label class="field__label">Recent runs</label>
        <div data-workflow-runs class="workflows-list"></div>
      </div>
    </div>
  </section>
</main>
@endsection

@push('scripts')
<script id="workflows-templates-json" type="application/json">@json($workflowTemplates)</script>
<script src="{{ asset('assets/js/workflows.js') }}"></script>
@endpush

