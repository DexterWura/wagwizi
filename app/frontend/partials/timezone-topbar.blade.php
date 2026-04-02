@php
  $tzList = $displayTimezonesList ?? collect();
  $defaultId = $defaultDisplayTimezoneIdentifier ?? 'UTC';
  $defaultRow = $tzList->firstWhere('identifier', $defaultId);
  $defaultShort = $defaultRow ? $defaultRow->label_short : 'UTC';
@endphp
    <div class="app-topbar__timezone" data-app-timezone-wrap>
      <button type="button" class="timezone-trigger" data-app-timezone-trigger aria-haspopup="listbox" aria-expanded="false" aria-label="Choose display timezone for schedules and reports">
        <i class="fa-solid fa-globe" aria-hidden="true"></i>
        <span data-app-timezone-label>{{ $defaultShort }}</span>
        <i class="fa-solid fa-chevron-down fa-xs timezone-trigger__chev" aria-hidden="true"></i>
      </button>
      <ul class="timezone-menu" data-app-timezone-menu role="listbox" hidden>
@if($tzList->isEmpty())
        <li role="presentation">
          <button type="button" class="timezone-menu__option" role="option" data-app-timezone-option data-value="UTC" aria-selected="true">
            <span class="timezone-menu__abbr">UTC</span>
            <span class="timezone-menu__label">Coordinated Universal Time</span>
          </button>
        </li>
@else
  @foreach($tzList as $tz)
        <li role="presentation">
          <button type="button" class="timezone-menu__option" role="option" data-app-timezone-option data-value="{{ $tz->identifier }}" aria-selected="{{ $tz->identifier === $defaultId ? 'true' : 'false' }}">
            <span class="timezone-menu__abbr">{{ $tz->label_short }}</span>
            <span class="timezone-menu__label">{{ $tz->label_long }}</span>
          </button>
        </li>
  @endforeach
@endif
      </ul>
    </div>
