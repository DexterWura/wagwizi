@php
  $avatarUser = $user ?? null;
  $avatarSize = $size ?? 'sm';
  $pixels = $avatarSize === 'lg' ? 176 : ($avatarSize === 'md' ? 80 : 64);
  $wh = $avatarSize === 'lg' ? 88 : ($avatarSize === 'md' ? 40 : 32);
@endphp
@if($avatarUser)
<img
  class="app-user-avatar app-user-avatar--{{ $avatarSize }}"
  src="{{ $avatarUser->avatarUrl($pixels) }}"
  alt=""
  width="{{ $wh }}"
  height="{{ $wh }}"
  decoding="async"
  @if($avatarSize !== 'sm') loading="lazy" @endif
  data-app-user-avatar="1"
/>
@endif
