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
    <button type="button" class="icon-btn app-topbar__notif" data-app-modal-open="modal-notifications" aria-label="Notifications">
      <i class="fa-solid fa-bell" aria-hidden="true"></i>
    </button>
@include('timezone-topbar')
    <button type="button" class="app-theme-toggle" data-app-theme-toggle aria-label="Toggle theme">
      <i class="fa-solid fa-moon" data-app-theme-icon aria-hidden="true"></i>
      <span class="app-theme-toggle__label" data-app-theme-label>Dark</span>
    </button>
    <button type="button" class="app-topbar__account" aria-label="Account menu">
      <span class="app-topbar__avatar" aria-hidden="true"></span>
      <i class="fa-solid fa-chevron-down fa-xs" aria-hidden="true"></i>
    </button>
  </div>
</header>
