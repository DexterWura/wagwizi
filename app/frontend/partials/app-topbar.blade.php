<header class="app-topbar" role="banner" aria-label="Page toolbar">
  <button type="button" class="menu-btn" data-app-drawer-open aria-label="Open menu">
    <i class="fa-solid fa-bars" aria-hidden="true"></i>
  </button>
  @if($currentUser)
  <div class="app-topbar__lead">
    <span class="app-topbar__hello">Hello,</span>
    <strong class="app-topbar__name">{{ $currentUser->name }}</strong>
  </div>
  @endif
  <div class="app-topbar__spacer" aria-hidden="true"></div>
  <div class="app-topbar__cluster">
    <time class="app-topbar__date" data-app-topbar-date datetime=""></time>
    @if($currentUser && $currentUser->shouldShowUpgradePlan())
    <a class="btn btn--topbar-upgrade" href="{{ route('plans') }}">Upgrade plan</a>
    @endif
    @include('notifications-bell')
@include('timezone-topbar')
    <button type="button" class="app-theme-toggle" data-app-theme-toggle aria-label="Toggle theme">
      <i class="fa-solid fa-moon" data-app-theme-icon aria-hidden="true"></i>
      <span class="app-theme-toggle__label" data-app-theme-label>Dark</span>
    </button>
    @if($currentUser)
    <div class="app-topbar__account-wrap" data-app-account-wrap>
      <div class="app-topbar__account">
        <span class="app-topbar__avatar" aria-hidden="true"></span>
        <button type="button" class="app-topbar__account-toggle" data-app-account-trigger id="app-topbar-account-trigger" aria-label="Account menu" aria-haspopup="menu" aria-expanded="false" aria-controls="app-topbar-account-menu">
          <i class="fa-solid fa-chevron-down fa-xs app-topbar__account-chev" aria-hidden="true"></i>
        </button>
      </div>
      <nav class="app-topbar-account-menu" data-app-account-menu id="app-topbar-account-menu" role="menu" aria-labelledby="app-topbar-account-trigger" hidden>
        <a class="app-topbar-account-menu__link" role="menuitem" href="{{ route('profile') }}">
          <i class="fa-solid fa-user fa-fw" aria-hidden="true"></i>
          Manage profile
        </a>
        <a class="app-topbar-account-menu__link" role="menuitem" href="{{ route('settings') }}">
          <i class="fa-solid fa-gear fa-fw" aria-hidden="true"></i>
          Settings
        </a>
        <button type="button" class="app-topbar-account-menu__link" role="menuitem" data-app-logout>
          <i class="fa-solid fa-right-from-bracket fa-fw" aria-hidden="true"></i>
          Log out
        </button>
      </nav>
    </div>
    @endif
  </div>
</header>
