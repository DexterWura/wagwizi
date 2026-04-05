{{-- Brand logo: social-network aura icon + adaptive wordmark for app/landing themes. --}}
<svg
  class="brand-logo-svg"
  xmlns="http://www.w3.org/2000/svg"
  width="188"
  height="40"
  viewBox="0 0 188 40"
  fill="none"
  aria-hidden="true"
  focusable="false"
>
  <defs>
    <linearGradient id="wagwiziIconGradient" x1="4" y1="4" x2="36" y2="36" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#7C3AED" />
      <stop offset="0.55" stop-color="#6366F1" />
      <stop offset="1" stop-color="#06B6D4" />
    </linearGradient>
    <radialGradient id="wagwiziAura" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(20 20) rotate(90) scale(20 20)">
      <stop offset="0" stop-color="#A78BFA" stop-opacity="0.36" />
      <stop offset="1" stop-color="#A78BFA" stop-opacity="0" />
    </radialGradient>
    <filter id="wagwiziGlow" x="-8" y="-8" width="56" height="56" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
      <feGaussianBlur stdDeviation="3.2" result="blur" />
      <feColorMatrix
        in="blur"
        type="matrix"
        values="1 0 0 0 0
                0 1 0 0 0
                0 0 1 0 0
                0 0 0 0.48 0"
      />
    </filter>
  </defs>

  <circle cx="20" cy="20" r="17" fill="url(#wagwiziAura)" filter="url(#wagwiziGlow)" />
  <rect x="4" y="4" width="32" height="32" rx="10" fill="#111111" />
  <rect x="4" y="4" width="32" height="32" rx="10" fill="url(#wagwiziIconGradient)" fill-opacity="0.95" />

  <path
    d="M10.2 11.2L13.8 28.2L19.1 16.8L24.4 28.2L28 11.2H24.7L22.7 21.7L19.1 14.1L15.5 21.7L13.5 11.2H10.2Z"
    fill="white"
  />

  <circle cx="8.8" cy="12.2" r="1.55" fill="#C4B5FD" />
  <circle cx="31.3" cy="9.8" r="1.25" fill="#67E8F9" />
  <circle cx="33.1" cy="29.9" r="1.45" fill="#A78BFA" />

  <text
    x="47"
    y="26"
    fill="currentColor"
    font-family="DM Sans, Outfit, &quot;Plus Jakarta Sans&quot;, ui-sans-serif, system-ui, sans-serif"
    font-size="24"
    font-weight="700"
    letter-spacing="-0.042em"
  >wagwizi</text>
</svg>
