@extends('app')

@section('title', 'Connect accounts — ' . config('app.name'))
@section('page-id', 'accounts')

@php
    $connectedMap = $connectedAccounts->groupBy('platform');
@endphp

@section('content')
        <main class="app-content">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true">
                  <i class="fa-solid fa-link" aria-hidden="true"></i>
                </div>
                <div>
                  <h1>Connect accounts</h1>
                  <p>Link the networks you publish to. OAuth tokens are stored securely on your server.</p>
                </div>
              </div>
            </div>
            <div class="head-actions">
              <a class="btn btn--outline" href="{{ route('dashboard') }}">
                <i class="fa-solid fa-house" aria-hidden="true"></i>
                Dashboard
              </a>
            </div>
          </div>

          @if(session('success'))
          <div class="alert alert--success" role="alert">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
          </div>
          @endif

          @if(session('error'))
          <div class="alert alert--error" role="alert">
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <span>{{ session('error') }}</span>
          </div>
          @endif

          @if(session('info'))
          <div class="alert alert--info" role="alert">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>{{ session('info') }}</span>
          </div>
          @endif

          <div class="social-connect-grid">
            @foreach($enabledPlatforms as $platform)
            @php
                $slug = $platform->value;
                $connected = $connectedMap->get($slug, collect());
                $activeAccount = $connected->firstWhere('status', 'active');
            @endphp
            <div class="social-connect-card{{ $activeAccount ? ' social-connect-card--connected' : '' }}">
              <div class="social-connect-card__icon"><i class="{{ $platform->icon() }}" aria-hidden="true"></i></div>
              <div class="social-connect-card__body">
                <h3>{{ $platform->label() }}</h3>
                @if($activeAccount)
                <p class="social-connect-card__user">
                  <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                  {{ $activeAccount->display_name ?? $activeAccount->username ?? 'Connected' }}
                  @if($slug === 'wordpress' && !empty($activeAccount->metadata['site_url']))
                    <span class="social-connect-card__site">{{ $activeAccount->metadata['site_url'] }}</span>
                  @endif
                  @if($slug === 'google_business' && !empty($activeAccount->metadata['location_name']))
                    <span class="social-connect-card__site">{{ $activeAccount->metadata['location_name'] }}</span>
                  @endif
                </p>
                <form method="POST" action="{{ route('accounts.disconnect', $activeAccount->id) }}">
                  @csrf
                  <button type="submit" class="btn btn--ghost social-connect-card__btn">Disconnect</button>
                </form>
                @else
                <p>{{ $platform->description() }}</p>
                  @if($slug === 'telegram')
                  <button type="button" class="btn btn--primary social-connect-card__btn" data-app-modal-open="modal-telegram-connect">Connect</button>
                  @elseif($slug === 'wordpress')
                  <button type="button" class="btn btn--primary social-connect-card__btn" data-app-modal-open="modal-wordpress-connect">Connect</button>
                  @elseif($slug === 'discord')
                  <button type="button" class="btn btn--primary social-connect-card__btn" data-app-modal-open="modal-discord-connect">Connect</button>
                  @else
                  <a class="btn btn--primary social-connect-card__btn" href="{{ route('accounts.connect', $slug) }}">Connect</a>
                  @endif
                @endif
              </div>
            </div>
            @endforeach
          </div>
        </main>
@endsection

@push('modals')
    <div class="app-modal" id="modal-discord-connect" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-discord-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel">
        <div class="app-modal__head">
          <div>
            <h2 id="modal-discord-title">Connect Discord</h2>
            <p class="app-modal__lede">Enter a <a href="https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks" target="_blank" rel="noopener">channel webhook URL</a> to publish messages to.</p>
          </div>
          <button type="button" class="icon-btn" data-app-modal-close aria-label="Close dialog">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
        <form method="POST" action="{{ route('accounts.discord') }}">
          @csrf
          <div class="app-modal__body">
            <div class="field">
              <label class="field__label" for="dc-webhook-url">Webhook URL</label>
              <input class="input" id="dc-webhook-url" name="webhook_url" type="url" placeholder="https://discord.com/api/webhooks/123456/abcdef…" required />
              <p class="field__hint">Create one in <em>Channel Settings &rarr; Integrations &rarr; Webhooks</em>.</p>
            </div>
            <div class="field">
              <label class="field__label" for="dc-channel-name">Channel name <span class="prose-muted">(optional)</span></label>
              <input class="input" id="dc-channel-name" name="channel_name" type="text" placeholder="#announcements" />
            </div>
          </div>
          <div class="app-modal__foot">
            <button type="button" class="btn btn--ghost" data-app-modal-close>Cancel</button>
            <button type="submit" class="btn btn--primary">Connect Discord</button>
          </div>
        </form>
      </div>
    </div>

    <div class="app-modal" id="modal-wordpress-connect" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-wordpress-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel">
        <div class="app-modal__head">
          <div>
            <h2 id="modal-wordpress-title">Connect WordPress</h2>
            <p class="app-modal__lede">Enter your site URL and an <a href="https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/" target="_blank" rel="noopener">Application Password</a> to publish blog posts.</p>
          </div>
          <button type="button" class="icon-btn" data-app-modal-close aria-label="Close dialog">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
        <form method="POST" action="{{ route('accounts.wordpress') }}">
          @csrf
          <div class="app-modal__body">
            <div class="field">
              <label class="field__label" for="wp-site-url">Site URL</label>
              <input class="input" id="wp-site-url" name="site_url" type="url" placeholder="https://myblog.com" required />
            </div>
            <div class="field">
              <label class="field__label" for="wp-username">WordPress username</label>
              <input class="input" id="wp-username" name="wp_username" type="text" placeholder="admin" required autocomplete="username" />
            </div>
            <div class="field">
              <label class="field__label" for="wp-app-password">Application Password</label>
              <input class="input" id="wp-app-password" name="app_password" type="password" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" required autocomplete="new-password" />
              <p class="field__hint">Generate one at <em>Users &rarr; Profile &rarr; Application Passwords</em> in your WP admin.</p>
            </div>
          </div>
          <div class="app-modal__foot">
            <button type="button" class="btn btn--ghost" data-app-modal-close>Cancel</button>
            <button type="submit" class="btn btn--primary">Connect WordPress</button>
          </div>
        </form>
      </div>
    </div>

    <div class="app-modal" id="modal-telegram-connect" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-telegram-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel">
        <div class="app-modal__head">
          <div>
            <h2 id="modal-telegram-title">Connect Telegram</h2>
            <p class="app-modal__lede">Enter your bot token and the chat or channel ID to publish to.</p>
          </div>
          <button type="button" class="icon-btn" data-app-modal-close aria-label="Close dialog">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
        <form method="POST" action="{{ route('accounts.telegram') }}">
          @csrf
          <div class="app-modal__body">
            <div class="field">
              <label class="field__label" for="tg-bot-token">Bot token</label>
              <input class="input" id="tg-bot-token" name="bot_token" type="text" placeholder="123456:ABC-DEF1234…" required autocomplete="off" />
            </div>
            <div class="field">
              <label class="field__label" for="tg-chat-id">Chat / Channel ID</label>
              <input class="input" id="tg-chat-id" name="chat_id" type="text" placeholder="-1001234567890 or @channelname" required />
            </div>
            <div class="field">
              <label class="field__label" for="tg-name">Channel name <span class="prose-muted">(optional)</span></label>
              <input class="input" id="tg-name" name="channel_name" type="text" placeholder="My Channel" />
            </div>
          </div>
          <div class="app-modal__foot">
            <button type="button" class="btn btn--ghost" data-app-modal-close>Cancel</button>
            <button type="submit" class="btn btn--primary">Connect Telegram</button>
          </div>
        </form>
      </div>
    </div>
@endpush
