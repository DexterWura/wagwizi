@php $activePage = $activePage ?? ''; @endphp
<aside class="app-sidebar" id="app-sidebar" data-app-drawer-panel aria-label="Main navigation">
  <div class="app-sidebar__brand">
    <button type="button" class="sidebar-collapse-btn" data-app-sidebar-collapse aria-expanded="true" aria-label="Collapse navigation" title="Collapse sidebar">
      <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
    </button>
    <a href="{{ route('landing') }}" aria-label="{{ config('app.name') }} home">
      <img src="{{ asset('assets/images/logo.svg') }}" width="120" height="32" alt="{{ config('app.name') }}" />
    </a>
    <button type="button" class="menu-btn menu-btn--close" data-app-drawer-close aria-label="Close menu">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
  </div>
  <div class="app-sidebar__search">
    <div class="app-sidebar-search-wrap" data-app-sidebar-search>
      <label class="search-field">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input
          type="search"
          id="app-sidebar-search-input"
          data-app-search-input
          placeholder="Search pages…"
          autocomplete="off"
          role="combobox"
          aria-autocomplete="list"
          aria-controls="app-sidebar-search-results"
          aria-expanded="false"
          aria-label="Search pages and settings. Keyboard shortcut Control K or Command K."
        />
        <kbd title="Focus search: ⌘K on Mac, Ctrl+K on Windows or Linux">⌘K</kbd>
      </label>
      <div class="app-sidebar-search-results" id="app-sidebar-search-results" role="listbox" hidden data-app-sidebar-search-results></div>
    </div>
  </div>
  <nav class="nav-scroll" aria-label="Main">
    <a class="nav-link {{ $activePage === 'dashboard-home' ? 'nav-link--active' : '' }}" href="{{ route('dashboard') }}">
      <i class="fa-solid fa-house fa-fw" aria-hidden="true"></i>
      Home
    </a>
    <div class="nav-group">
      <div class="nav-group__label"><span class="nav-group__dot nav-group__dot--publishing"></span> Publishing</div>
      <a class="nav-link nav-link--sub {{ $activePage === 'composer' ? 'nav-link--active' : '' }}" href="{{ route('composer') }}"><i class="fa-solid fa-pen-to-square fa-fw" aria-hidden="true"></i>Create post</a>
      <a class="nav-link nav-link--sub {{ $activePage === 'calendar' ? 'nav-link--active' : '' }}" href="{{ route('calendar') }}"><i class="fa-solid fa-calendar-days fa-fw" aria-hidden="true"></i>Calendar</a>
      <a class="nav-link nav-link--sub {{ $activePage === 'media-library' ? 'nav-link--active' : '' }}" href="{{ route('media-library') }}"><i class="fa-solid fa-photo-film fa-fw" aria-hidden="true"></i>Media library</a>
    </div>
    <div class="nav-group">
      <div class="nav-group__label"><span class="nav-group__dot nav-group__dot--accounts"></span> Accounts</div>
      <a class="nav-link nav-link--sub {{ $activePage === 'accounts' ? 'nav-link--active' : '' }}" href="{{ route('accounts') }}"><i class="fa-solid fa-link fa-fw" aria-hidden="true"></i>Connect accounts</a>
    </div>
    <div class="nav-group">
      <div class="nav-group__label"><span class="nav-group__dot nav-group__dot--analytics"></span> Analytics</div>
      <a class="nav-link nav-link--sub {{ $activePage === 'insights' ? 'nav-link--active' : '' }}" href="{{ route('insights') }}"><i class="fa-solid fa-chart-line fa-fw" aria-hidden="true"></i>Insights</a>
    </div>
    <div class="nav-group">
      <div class="nav-group__label"><span class="nav-group__dot nav-group__dot--billing"></span> Billing</div>
      <a class="nav-link nav-link--sub {{ $activePage === 'plans' ? 'nav-link--active' : '' }}" href="{{ route('plans') }}"><i class="fa-solid fa-layer-group fa-fw" aria-hidden="true"></i>Plans</a>
      <a class="nav-link nav-link--sub {{ $activePage === 'plan-history' ? 'nav-link--active' : '' }}" href="{{ route('plan-history') }}"><i class="fa-solid fa-clock-rotate-left fa-fw" aria-hidden="true"></i>Plan history</a>
    </div>
    <div class="nav-group">
      <div class="nav-group__label"><span class="nav-group__dot nav-group__dot--account"></span> Account</div>
      <a class="nav-link nav-link--sub {{ $activePage === 'profile' ? 'nav-link--active' : '' }}" href="{{ route('profile') }}"><i class="fa-solid fa-user fa-fw" aria-hidden="true"></i>Profile</a>
      <a class="nav-link nav-link--sub {{ $activePage === 'settings' ? 'nav-link--active' : '' }}" href="{{ route('settings') }}"><i class="fa-solid fa-gear fa-fw" aria-hidden="true"></i> Settings</a>
      <a class="nav-link nav-link--sub {{ $activePage === 'support-tickets' ? 'nav-link--active' : '' }}" href="{{ route('support-tickets.index') }}"><i class="fa-solid fa-ticket fa-fw" aria-hidden="true"></i>Support tickets</a>
    </div>
    @if($currentUser?->isSuperAdmin())
    <div class="nav-group">
      <div class="nav-group__label"><span class="nav-group__dot nav-group__dot--admin"></span> Super Admin</div>
      <span class="nav-subgroup-label">Commerce &amp; billing</span>
      <a class="nav-link nav-link--sub {{ $activePage === 'admin-subscriptions' ? 'nav-link--active' : '' }}" href="{{ route('admin.subscriptions') }}"><i class="fa-solid fa-chart-line fa-fw" aria-hidden="true"></i>Subscriptions</a>
      <a class="nav-link nav-link--sub {{ $activePage === 'admin-payment-gateways' ? 'nav-link--active' : '' }}" href="{{ route('admin.payment-gateways') }}"><i class="fa-solid fa-building-columns fa-fw" aria-hidden="true"></i>Payment gateways</a>
      <a class="nav-link nav-link--sub {{ $activePage === 'admin-plans' ? 'nav-link--active' : '' }}" href="{{ route('admin.plans') }}"><i class="fa-solid fa-layer-group fa-fw" aria-hidden="true"></i>Plans &amp; pricing</a>
      <span class="nav-subgroup-label">People &amp; platform</span>
      <a class="nav-link nav-link--sub {{ $activePage === 'admin-users' ? 'nav-link--active' : '' }}" href="{{ route('admin.users') }}"><i class="fa-solid fa-users fa-fw" aria-hidden="true"></i>Users</a>
      <a class="nav-link nav-link--sub {{ $activePage === 'admin-platforms' ? 'nav-link--active' : '' }}" href="{{ route('admin.platforms') }}"><i class="fa-solid fa-plug fa-fw" aria-hidden="true"></i>Platforms</a>
      <a class="nav-link nav-link--sub {{ $activePage === 'admin-tickets' ? 'nav-link--active' : '' }}" href="{{ route('admin.tickets') }}"><i class="fa-solid fa-life-ring fa-fw" aria-hidden="true"></i>Support tickets</a>
      <span class="nav-subgroup-label">Marketing content</span>
      <a class="nav-link nav-link--sub {{ $activePage === 'admin-testimonials' ? 'nav-link--active' : '' }}" href="{{ route('admin.testimonials') }}"><i class="fa-solid fa-quote-left fa-fw" aria-hidden="true"></i>Testimonials</a>
      <a class="nav-link nav-link--sub {{ $activePage === 'admin-faqs' ? 'nav-link--active' : '' }}" href="{{ route('admin.faqs') }}"><i class="fa-solid fa-circle-question fa-fw" aria-hidden="true"></i>FAQs</a>
      <span class="nav-subgroup-label">System</span>
      <a class="nav-link nav-link--sub {{ $activePage === 'admin-settings' ? 'nav-link--active' : '' }}" href="{{ route('admin.settings') }}"><i class="fa-solid fa-sliders fa-fw" aria-hidden="true"></i>Site settings</a>
      <a class="nav-link nav-link--sub {{ $activePage === 'admin-migrations' ? 'nav-link--active' : '' }}" href="{{ route('admin.migrations') }}"><i class="fa-solid fa-database fa-fw" aria-hidden="true"></i>Migrations</a>
      <a class="nav-link nav-link--sub {{ $activePage === 'admin-operations' ? 'nav-link--active' : '' }}" href="{{ route('admin.operations') }}"><i class="fa-solid fa-shield-halved fa-fw" aria-hidden="true"></i>Operations</a>
    </div>
    @endif
  </nav>
  <div class="app-sidebar__footer">
    <div class="user-chip">
      <span class="user-chip__avatar" aria-hidden="true"></span>
      <div class="user-chip__meta">
        <strong title="{{ $currentUser->name ?? 'User' }}">{{ $currentUser->name ?? 'User' }}</strong>
        <span title="{{ $currentUser->email ?? '' }}">{{ $currentUser->email ?? '' }}</span>
      </div>
      <button type="button" aria-label="Account menu"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
    </div>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="sidebar-logout" aria-label="Log out">
        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
        <span>Log out</span>
      </button>
    </form>
  </div>
</aside>
