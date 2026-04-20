@extends('auth')

@section('title', 'Create account — ' . config('app.name'))

@section('content')
    <div class="login-page__brand">
      <span class="sr-only">{{ config('app.name') }}</span>
      @include('brand-logo')
    </div>
    <div class="login-page__panel">
      <div class="login-page__head login-page__head--center">
        <h1>Create account</h1>
        <p>Start managing your social media in one place.</p>
      </div>

      @if($errors->any())
      <div class="alert alert--error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <ul>
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
      @endif

      @if(session('social_error'))
      <div class="alert alert--error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span>{{ session('social_error') }}</span>
      </div>
      @endif

      @if(!empty($signupOtpEnabled))
      <div class="alert alert--info" role="note">
        <i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i>
        <span>After signup, we will email a 6-digit verification code before creating your account.</span>
      </div>
      @endif

      @if($socialGoogleEnabled || $socialLinkedinEnabled)
      <div class="social-auth-buttons">
        @if($socialGoogleEnabled)
        <a href="{{ route('social.redirect', 'google') }}" class="btn btn--social btn--google" data-social-auth-popup>
          @if(($lastSocialLoginProvider ?? null) === 'google')
          <span class="social-auth-last-used">Last used</span>
          @endif
          <svg class="social-auth-icon" viewBox="0 0 24 24" width="20" height="20"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09a6.97 6.97 0 0 1 0-4.17V7.07H2.18A11.97 11.97 0 0 0 0 12c0 1.94.46 3.77 1.28 5.4l3.56-2.77.01-.54z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
          Sign up with Google
        </a>
        @endif
        @if($socialLinkedinEnabled)
        <a href="{{ route('social.redirect', 'linkedin-openid') }}" class="btn btn--social btn--linkedin" data-social-auth-popup>
          @if(($lastSocialLoginProvider ?? null) === 'linkedin-openid')
          <span class="social-auth-last-used">Last used</span>
          @endif
          <i class="fa-brands fa-linkedin" aria-hidden="true"></i>
          Sign up with LinkedIn
        </a>
        @endif
      </div>

      <div class="social-auth-divider">
        <span>or sign up with email</span>
      </div>
      @endif

      <form method="POST" action="{{ route('signup') }}">
        @csrf
        <input type="hidden" name="referral_code" value="{{ old('referral_code', $referralCode ?? '') }}" />
        <input type="hidden" name="redirect" value="{{ old('redirect', $redirectTarget ?? '') }}" />
        <div class="field">
          <label class="field__label" for="signup-name">Full Name</label>
          <input class="input" id="signup-name" type="text" name="name" value="{{ old('name') }}" autocomplete="name" placeholder="Your Full Name" required />
        </div>
        <div class="field">
          <label class="field__label" for="signup-email">Email</label>
          <input class="input" id="signup-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com" required />
        </div>
        <div class="field">
          <label class="field__label" for="signup-password">Password</label>
          <input class="input" id="signup-password" type="password" name="password" autocomplete="new-password" placeholder="At least 8 characters" required />
        </div>
        <div class="field">
          <label class="field__label" for="signup-password-confirm">Confirm password</label>
          <input class="input" id="signup-password-confirm" type="password" name="password_confirmation" autocomplete="new-password" placeholder="Same password again" required />
        </div>
        <button type="submit" class="btn btn--primary login-page__submit">
          Create account
        </button>
      </form>

      <div class="login-page__meta-links" role="navigation" aria-label="Authentication links">
        <a href="{{ route('login', ['redirect' => ($redirectTarget ?? '')]) }}">Already have an account?</a>
        <a href="{{ route('landing') }}">Back to home</a>
      </div>
    </div>
@endsection

@section('scripts')
<script>
  (function () {
    function popupFeatures(width, height) {
      var dualLeft = window.screenLeft !== undefined ? window.screenLeft : window.screenX;
      var dualTop = window.screenTop !== undefined ? window.screenTop : window.screenY;
      var viewportWidth = window.innerWidth || document.documentElement.clientWidth || screen.width;
      var viewportHeight = window.innerHeight || document.documentElement.clientHeight || screen.height;
      var left = Math.max(0, dualLeft + ((viewportWidth - width) / 2));
      var top = Math.max(0, dualTop + ((viewportHeight - height) / 2));
      return "scrollbars=yes,resizable=yes,width=" + width + ",height=" + height + ",left=" + left + ",top=" + top;
    }

    function openSocialAuthPopup(url) {
      var withPopupFlag = url.indexOf("?") === -1 ? (url + "?popup=1") : (url + "&popup=1");
      var popup = window.open(withPopupFlag, "socialAuthPopup", popupFeatures(620, 760));
      if (!popup) {
        window.location.href = withPopupFlag;
        return;
      }
      try {
        popup.focus();
      } catch (e) {}
    }

    document.addEventListener("click", function (event) {
      var link = event.target && event.target.closest ? event.target.closest("a[data-social-auth-popup]") : null;
      if (!link) return;
      var href = link.getAttribute("href");
      if (!href) return;
      event.preventDefault();
      openSocialAuthPopup(href);
    });

    window.addEventListener("message", function (event) {
      if (event.origin !== window.location.origin) return;
      var payload = event.data;
      if (!payload || typeof payload !== "object") return;
      if (payload.type !== "social-auth-complete") return;

      var redirectUrl = typeof payload.redirectUrl === "string" ? payload.redirectUrl : "";
      if (redirectUrl) {
        window.location.href = redirectUrl;
        return;
      }
      window.location.reload();
    });
  })();
</script>
@endsection
