@php
  $unreadNotifCount = (int) ($unreadNotificationCount ?? 0);
  $notifAria = $unreadNotifCount > 0
    ? 'Notifications, ' . $unreadNotifCount . ' unread'
    : 'Notifications';
@endphp
<span class="app-topbar__notif-wrap">
  <button type="button" class="icon-btn app-topbar__notif" data-app-modal-open="modal-notifications" data-app-notif-bell data-app-unread-notifications="{{ $unreadNotifCount }}" aria-label="{{ $notifAria }}">
    <i class="fa-solid fa-bell" aria-hidden="true"></i>
    @if($unreadNotifCount > 0)
    <span class="app-topbar__notif-badge" aria-hidden="true">{{ $unreadNotifCount > 9 ? '9+' : $unreadNotifCount }}</span>
    @endif
  </button>
</span>
