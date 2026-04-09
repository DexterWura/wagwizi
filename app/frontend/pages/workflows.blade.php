@extends('app')

@section('title', 'Workflows — ' . config('app.name'))
@section('page-id', 'workflows')

@section('content')
<main class="app-content workflows-page" data-app-workflows>
  <section class="workflows-studio" data-workflows-root>
    <header class="workflows-studio__topbar">
      <div class="workflows-topbar__left">
        <i class="fa-solid fa-diagram-project" aria-hidden="true"></i>
        <input class="input workflows-topbar__name" id="workflow-name" type="text" placeholder="Untitled workflow" />
        <select class="select select--sm workflows-topbar__select" id="workflow-template" data-workflow-template-select>
          <option value="">Blank</option>
          @foreach($workflowTemplates as $template)
            <option value="{{ $template['key'] }}">{{ $template['name'] }}</option>
          @endforeach
        </select>
        <select class="select select--sm workflows-topbar__select" id="workflow-trigger" data-workflow-trigger>
          <option value="manual">Manual</option>
          <option value="schedule">Schedule</option>
          <option value="event">Event</option>
        </select>
      </div>
      <div class="workflows-topbar__center">
        <button class="btn btn--ghost btn--compact is-active" type="button">Editor</button>
        <button class="btn btn--ghost btn--compact" type="button">Executions</button>
      </div>
      <div class="workflows-topbar__right">
        <label class="check-line workflows-topbar__toggle">
          <span>Active</span>
          <input type="checkbox" data-workflow-active />
        </label>
        <button class="btn btn--ghost btn--compact" type="button" data-workflows-new>New</button>
        <button class="btn btn--ghost btn--compact" type="button" data-workflows-save-as-new>Save as new</button>
        <button class="btn btn--outline btn--compact" type="button" data-workflows-run>Run</button>
        <button class="btn btn--primary btn--compact" type="button" data-workflows-save>Save</button>
      </div>
    </header>

    <div class="workflows-studio__body">
      <aside class="workflows-side workflows-side--left">
        <div class="workflows-side__head">
          <h3>Nodes</h3>
        </div>
        <div class="workflows-node-list" data-workflow-node-palette></div>
      </aside>

      <section class="workflows-canvas-wrap">
        <div class="workflows-canvas-toolbar">
          <button class="icon-btn" type="button" data-workflow-zoom-out title="Zoom out">
            <i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i>
          </button>
          <button class="icon-btn" type="button" data-workflow-zoom-in title="Zoom in">
            <i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i>
          </button>
          <button class="icon-btn" type="button" data-workflow-auto-layout title="Auto layout">
            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
          </button>
          <button class="btn btn--ghost btn--compact" type="button" data-workflow-connect-mode>Connect nodes</button>
          <button class="btn btn--ghost btn--compact" type="button" data-workflow-clear-connections>Clear edges</button>
        </div>
        <div class="workflows-canvas" data-workflow-canvas aria-label="Workflow canvas">
          <svg class="workflows-canvas__edges" data-workflow-edges></svg>
          <div class="workflows-canvas__nodes" data-workflow-node-layer></div>
          <div class="workflows-canvas__empty muted" data-workflow-empty-state>
            Drag nodes from the left, then connect output to input.
          </div>
        </div>
        <div class="workflows-canvas-footer">
          <button class="btn btn--ghost btn--compact" type="button" data-workflow-reset-view>Reset view</button>
          <button class="btn btn--ghost btn--compact" type="button" data-workflow-unselect>Unselect node</button>
        </div>
      </section>

      <aside class="workflows-side workflows-side--right">
        <div class="workflows-side__head">
          <h3>Inspector</h3>
        </div>
        <p class="muted" data-workflow-inspector-empty>Select a node to edit configuration.</p>
        <textarea class="textarea" rows="10" data-workflow-node-config hidden></textarea>
        <button type="button" class="btn btn--primary btn--compact" data-workflow-node-config-save hidden>Apply node config</button>
        <hr />
        <div class="field field--full">
          <label class="field__label">Connected accounts</label>
          <div class="admin-checkbox-grid workflows-accounts">
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
      </aside>
    </div>
  </section>
</main>
@endsection

@push('scripts')
<script id="workflows-templates-json" type="application/json">@json($workflowTemplates)</script>
<script src="{{ asset('assets/js/workflows.js') }}"></script>
@endpush

