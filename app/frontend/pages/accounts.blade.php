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

          @if($socialAccountLimit !== null)
          <p class="accounts-plan-cap prose-muted">
            Connected accounts: <strong>{{ $socialAccountActiveTotal }} / {{ $socialAccountLimit }}</strong> for your current plan.
            @if(!($canAddSocialAccounts ?? true))
              <a href="{{ route('plans') }}">Upgrade</a> for a higher limit.
            @endif
          </p>
          @endif

          @if(!($canAddSocialAccounts ?? true))
          <div class="alert alert--warning" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>You’ve reached the maximum number of accounts for your plan. Disconnect an account or <a href="{{ route('plans') }}">upgrade</a> to connect more.</span>
          </div>
          @endif

          @if($socialAccountPerPlatformLimit !== null)
          <p class="accounts-plan-cap prose-muted">
            Per-platform cap: <strong>{{ $socialAccountPerPlatformLimit }}</strong> account(s) per network.
          </p>
          @endif

          <div class="social-connect-grid">
            @foreach($enabledPlatforms as $platform)
            @php
                $slug = $platform->value;
                $connected = $connectedMap->get($slug, collect());
            @endphp
            <div class="social-connect-card{{ $connected->where('status', 'active')->count() > 0 ? ' social-connect-card--connected' : '' }}">
              <div class="social-connect-card__icon"><i class="{{ $platform->icon() }}" aria-hidden="true"></i></div>
              <div class="social-connect-card__body">
                <h3>{{ $platform->label() }}</h3>
                <p>{{ $platform->description() }}</p>
                @if(!($canAddSocialAccounts ?? true))
                  <button type="button" class="btn btn--primary social-connect-card__btn" disabled title="Account limit reached for your plan. Disconnect an account or upgrade.">Connect</button>
                @elseif($slug === 'telegram')
                  <button type="button" class="btn btn--primary social-connect-card__btn" data-app-modal-open="modal-telegram-connect">Connect</button>
                @elseif($slug === 'wordpress')
                  <button type="button" class="btn btn--primary social-connect-card__btn" data-app-modal-open="modal-wordpress-connect">Connect</button>
                @elseif($slug === 'discord')
                  <button type="button" class="btn btn--primary social-connect-card__btn" data-app-modal-open="modal-discord-connect">Connect</button>
                @elseif($slug === 'bluesky')
                  <button type="button" class="btn btn--primary social-connect-card__btn" data-app-modal-open="modal-bluesky-connect">Connect</button>
                @elseif($slug === 'whatsapp_channels')
                  <button type="button" class="btn btn--primary social-connect-card__btn" data-app-modal-open="modal-whatsapp-channels-connect">Connect</button>
                @else
                  <a class="btn btn--primary social-connect-card__btn" href="{{ route('accounts.connect', $slug) }}">Connect</a>
                @endif
              </div>
            </div>
            @endforeach
          </div>

          <div class="card" style="margin-top:16px;">
            <div class="card__head">Connected accounts</div>
            <div class="card__body">
              <table class="table">
                <thead>
                  <tr>
                    <th>Platform</th>
                    <th>Account</th>
                    <th>Status</th>
                    <th>Connected</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                @forelse($connectedAccounts as $account)
                  @php
                    $hasPendingPublishing = $account->postPlatforms()
                      ->whereIn('status', ['pending', 'publishing'])
                      ->whereNull('published_at')
                      ->exists();
                  @endphp
                  <tr>
                    <td>{{ \App\Services\Platform\Platform::tryFrom($account->platform)?->label() ?? ucfirst($account->platform) }}</td>
                    <td>{{ $account->display_name ?? $account->username ?? $account->platform_user_id }}</td>
                    <td>{{ ucfirst($account->status) }}</td>
                    <td>{{ optional($account->created_at)->format('Y-m-d') }}</td>
                    <td>
                      @if($account->status === 'active')
                      <form method="POST" action="{{ route('accounts.disconnect', $account->id) }}" data-app-force-disconnect="{{ $hasPendingPublishing ? '1' : '0' }}">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--compact">Disconnect</button>
                      </form>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="prose-muted">No connected accounts yet.</td>
                  </tr>
                @endforelse
                </tbody>
              </table>
            </div>
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

    <div class="app-modal" id="modal-bluesky-connect" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-bluesky-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel">
        <div class="app-modal__head">
          <div>
            <h2 id="modal-bluesky-title">Connect Bluesky</h2>
            <p class="app-modal__lede">Use your handle (or email) and an <a href="https://bsky.app/settings/app-passwords" target="_blank" rel="noopener">App Password</a> from Bluesky — not your main account password.</p>
          </div>
          <button type="button" class="icon-btn" data-app-modal-close aria-label="Close dialog">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
        <form method="POST" action="{{ route('accounts.bluesky') }}">
          @csrf
          <div class="app-modal__body">
            <div class="field">
              <label class="field__label" for="bsky-identifier">Handle or email</label>
              <input class="input" id="bsky-identifier" name="identifier" type="text" placeholder="you.bsky.social" required autocomplete="username" />
            </div>
            <div class="field">
              <label class="field__label" for="bsky-app-password">App Password</label>
              <input class="input" id="bsky-app-password" name="app_password" type="password" placeholder="xxxx-xxxx-xxxx-xxxx" required autocomplete="new-password" />
            </div>
          </div>
          <div class="app-modal__foot">
            <button type="button" class="btn btn--ghost" data-app-modal-close>Cancel</button>
            <button type="submit" class="btn btn--primary">Connect Bluesky</button>
          </div>
        </form>
      </div>
    </div>

    <div class="app-modal" id="modal-whatsapp-channels-connect" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-whatsapp-channels-title" aria-hidden="true">
      <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
      <div class="app-modal__panel">
        <div class="app-modal__head">
          <div>
            <h2 id="modal-whatsapp-channels-title">Connect WhatsApp Channels</h2>
            <p class="app-modal__lede">Use a <a href="https://developers.facebook.com/docs/whatsapp/cloud-api" target="_blank" rel="noopener">WhatsApp Cloud API</a> access token, your <strong>Phone number ID</strong> from Meta, and the API <strong>to</strong> value for your channel or recipient (E.164 phone, group id, or other id your app is allowed to message).</p>
          </div>
          <button type="button" class="icon-btn" data-app-modal-close aria-label="Close dialog">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
        <form method="POST" action="{{ route('accounts.whatsapp-channels') }}">
          @csrf
          <div class="app-modal__body">
            <div class="field">
              <label class="field__label" for="wa-access-token">Permanent access token</label>
              <input class="input" id="wa-access-token" name="access_token" type="password" placeholder="EAAG…" required autocomplete="off" />
              <p class="field__hint">System user or long-lived token with <code>whatsapp_business_messaging</code> (and related) permissions.</p>
            </div>
            <div class="field">
              <label class="field__label" for="wa-phone-number-id">Phone number ID</label>
              <input class="input" id="wa-phone-number-id" name="phone_number_id" type="text" inputmode="numeric" pattern="[0-9]+" placeholder="e.g. 123456789012345" required />
            </div>
            <div class="field">
              <label class="field__label" for="wa-channel-recipient">Channel / recipient ID (<code>to</code>)</label>
              <input class="input" id="wa-channel-recipient" name="channel_recipient" type="text" placeholder="Recipient id or +15551234567" required />
            </div>
            <div class="field">
              <label class="field__label" for="wa-recipient-type">Recipient type</label>
              <select class="input" id="wa-recipient-type" name="recipient_type">
                <option value="individual" selected>Individual</option>
                <option value="group">Group</option>
              </select>
            </div>
            <div class="field">
              <label class="field__label" for="wa-channel-name">Display name <span class="prose-muted">(optional)</span></label>
              <input class="input" id="wa-channel-name" name="channel_name" type="text" placeholder="My channel" />
            </div>
          </div>
          <div class="app-modal__foot">
            <button type="button" class="btn btn--ghost" data-app-modal-close>Cancel</button>
            <button type="submit" class="btn btn--primary">Connect WhatsApp</button>
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
