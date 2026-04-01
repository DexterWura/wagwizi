<header class="app-topbar" role="banner" aria-label="Page toolbar">
  <button type="button" class="menu-btn" data-app-drawer-open aria-label="Open menu">
    <i class="fa-solid fa-bars" aria-hidden="true"></i>
  </button>
  <div class="app-topbar__spacer" aria-hidden="true"></div>
  <div class="app-topbar__cluster">
    <time class="app-topbar__date" data-app-topbar-date datetime=""></time>
    <a class="btn btn--topbar-upgrade" href="{{ route('plans') }}">Upgrade plan</a>
    <button type="button" class="icon-btn app-topbar__notif" data-app-modal-open="modal-notifications" aria-label="Notifications">
      <i class="fa-solid fa-bell" aria-hidden="true"></i>
    </button>
    <div class="app-topbar__timezone" data-app-timezone-wrap>
      <button type="button" class="timezone-trigger" data-app-timezone-trigger aria-haspopup="listbox" aria-expanded="false" aria-label="Choose display timezone for schedules and reports">
        <i class="fa-solid fa-globe" aria-hidden="true"></i>
        <span data-app-timezone-label>UTC</span>
        <i class="fa-solid fa-chevron-down fa-xs timezone-trigger__chev" aria-hidden="true"></i>
      </button>
      <ul class="timezone-menu" data-app-timezone-menu role="listbox" hidden>
        <li role="presentation">
          <button type="button" class="timezone-menu__option" role="option" data-app-timezone-option data-value="utc" aria-selected="true">
            <span class="timezone-menu__abbr">UTC</span>
            <span class="timezone-menu__label">Coordinated Universal Time</span>
          </button>
        </li>
        <li role="presentation">
          <button type="button" class="timezone-menu__option" role="option" data-app-timezone-option data-value="est" aria-selected="false">
            <span class="timezone-menu__abbr">EST</span>
            <span class="timezone-menu__label">Eastern (US)</span>
          </button>
        </li>
        <li role="presentation">
          <button type="button" class="timezone-menu__option" role="option" data-app-timezone-option data-value="cet" aria-selected="false">
            <span class="timezone-menu__abbr">CET</span>
            <span class="timezone-menu__label">Central European</span>
          </button>
        </li>
      </ul>
    </div>
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
