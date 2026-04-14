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
              @if(session('error'))
              <div class="alert alert--danger" style="margin: 0.85rem;">
                {{ session('error') }}
              </div>
              @endif
              @if($errors->any())
              <div class="alert alert--danger" style="margin: 0.85rem;">
                {{ $errors->first() }}
              </div>
              @endif
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
                      @elseif($tool['is_download'])
                      <p>Paste a direct media URL and import it to your Media Library.</p>
                      @else
                      <p>This tool is enabled for your plan. Self-serve UI is available here.</p>
                      @endif
                    @else
                      <p>{{ $tool['message'] !== '' ? $tool['message'] : 'This tool is not available on your current plan.' }}</p>
                    @endif
                  </div>
                  @if($tool['enabled'] && $tool['is_download'])
                  <form method="POST" action="{{ route('tools.download') }}" class="tool-card__form">
                    @csrf
                    <input type="hidden" name="tool_slug" value="{{ $tool['slug'] }}" />
                    <label class="tool-card__label" for="tool-url-{{ $tool['slug'] }}">Media URL</label>
                    <input
                      id="tool-url-{{ $tool['slug'] }}"
                      class="input"
                      type="url"
                      name="media_url"
                      placeholder="https://example.com/video.mp4"
                      required
                    />
                    <button type="submit" class="btn btn--primary">Download to Media Library</button>
                  </form>
                  @endif
                  <div class="tool-card__foot">
                    @if($tool['action_url'])
                      @if($tool['enabled'])
                      <a href="{{ $tool['action_url'] }}" class="btn {{ $tool['enabled'] ? 'btn--primary' : 'btn--outline' }}">
                        {{ $tool['action_label'] }}
                      </a>
                      @elseif(! $tool['implemented'])
                      <button type="button" class="btn btn--outline" disabled>Upgrade to use</button>
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

