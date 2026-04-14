@extends('app')

@section('title', 'Tools — ' . config('app.name'))
@section('page-id', 'tools')

@section('content')
        <main class="app-content app-content--tools">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true">
                  <i class="fa-solid fa-toolbox" aria-hidden="true"></i>
                </div>
                <div>
                  <h1>Tools</h1>
                  <p>Everything is in one place. Use enabled tools directly from here.</p>
                </div>
              </div>
            </div>
          </div>

          <div class="card card--app-section">
            <div class="card__head">Available in your plan</div>
            <div class="card__body card__body--flush">
              @if(!empty($tools))
              <div class="tools-grid">
                @foreach($tools as $tool)
                <article class="tool-card{{ $tool['enabled'] ? ' tool-card--enabled' : '' }}">
                  <div class="tool-card__head">
                    <div>
                      <h3>{{ $tool['label'] }}</h3>
                      <p>{{ $tool['category'] }}</p>
                    </div>
                    @if($tool['enabled'])
                      <span class="tool-pill tool-pill--enabled">Enabled</span>
                    @else
                      <span class="tool-pill">Locked</span>
                    @endif
                  </div>
                  <div class="tool-card__body">
                    @if($tool['enabled'])
                      @if($tool['implemented'])
                      <p>This tool is ready to use.</p>
                      @else
                      <p>This tool is enabled for your plan. Setup is required before first use.</p>
                      @endif
                    @else
                      <p>{{ $tool['message'] !== '' ? $tool['message'] : 'This tool is not available on your current plan.' }}</p>
                    @endif
                  </div>
                  <div class="tool-card__foot">
                    @if($tool['action_url'])
                      @if($tool['enabled'] || ! $tool['implemented'])
                      <a href="{{ $tool['action_url'] }}" class="btn {{ $tool['enabled'] ? 'btn--primary' : 'btn--outline' }}">
                        {{ $tool['action_label'] }}
                      </a>
                      @else
                      <button type="button" class="btn btn--outline" disabled>Upgrade to use</button>
                      @endif
                    @endif
                  </div>
                </article>
                @endforeach
              </div>
              @else
              <div class="empty-sm">
                <i class="fa-solid fa-toolbox" aria-hidden="true"></i>
                No tools found for this workspace.
              </div>
              @endif
            </div>
          </div>
        </main>
@endsection

