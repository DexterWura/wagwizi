<?php

namespace App\Controllers\Auth;

use App\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const LAST_SOCIAL_LOGIN_COOKIE = 'last_social_login_provider';
    private const POPUP_SOCIAL_AUTH_SESSION_KEY = 'popup_social_auth_provider';

    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function redirect(Request $request, string $provider): \Symfony\Component\HttpFoundation\Response
    {
        if (! $this->authService->canUseSocialProvider($provider)) {
            return redirect()->route('login')
                ->withErrors(['social' => 'This login method is not available.']);
        }

        $this->setPopupSocialAuthContext($provider, $request->boolean('popup'));

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $popupRequested = $this->pullPopupSocialAuthContext($provider);

        if (! $this->authService->canUseSocialProvider($provider)) {
            if ($popupRequested) {
                session()->flash('social_error', 'This login method is not available.');
                return $this->socialAuthPopupCompletionResponse();
            }

            return redirect()->route('login')
                ->withErrors(['social' => 'This login method is not available.']);
        }

        if ($request->has('error')) {
            $errorDesc = $request->input('error_description', 'Authorization was denied.');
            Log::warning('Social auth denied by user', ['provider' => $provider, 'error' => $errorDesc]);

            if ($popupRequested) {
                session()->flash('social_error', $errorDesc);
                return $this->socialAuthPopupCompletionResponse();
            }

            return redirect()->route('login')
                ->withErrors(['social' => $errorDesc]);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            Log::error('Social auth token exchange failed', [
                'provider' => $provider,
                'error'    => $e->getMessage(),
            ]);

            if ($popupRequested) {
                session()->flash('social_error', 'Unable to authenticate. Please try again.');
                return $this->socialAuthPopupCompletionResponse();
            }

            return redirect()->route('login')
                ->withErrors(['social' => 'Unable to authenticate. Please try again.']);
        }

        if (empty($socialUser->getEmail())) {
            Log::warning('Social auth returned no email', ['provider' => $provider]);

            if ($popupRequested) {
                session()->flash('social_error', 'We could not retrieve your email address. Please ensure your account has a verified email.');
                return $this->socialAuthPopupCompletionResponse();
            }

            return redirect()->route('login')
                ->withErrors(['social' => 'We could not retrieve your email address. Please ensure your account has a verified email.']);
        }

        try {
            $result = $this->authService->findOrCreateFromSocialite($provider, $socialUser);
        } catch (\RuntimeException $e) {
            if ($popupRequested) {
                session()->flash('social_error', $e->getMessage());
                return $this->socialAuthPopupCompletionResponse();
            }

            return redirect()->route('login')
                ->withErrors(['social' => $e->getMessage()]);
        }

        $request->session()->regenerate();

        $normalizedProvider = $this->normalizedProvider($provider);
        $providerCookie = cookie(self::LAST_SOCIAL_LOGIN_COOKIE, $normalizedProvider, 60 * 24 * 365);

        if ($popupRequested) {
            $targetUrl = $result['is_new']
                ? route('profile')
                : $this->intendedUrlForPopup();

            return $this->socialAuthPopupCompletionResponse($targetUrl)
                ->withCookie($providerCookie);
        }

        if ($result['is_new']) {
            return redirect()->route('profile')
                ->with('info', 'Welcome! Please complete your profile to get started.')
                ->withCookie($providerCookie);
        }

        return redirect()->intended('/dashboard')
            ->withCookie($providerCookie);
    }

    private function normalizedProvider(string $provider): string
    {
        $p = strtolower(trim($provider));

        return in_array($p, ['google', 'linkedin-openid'], true) ? $p : 'google';
    }

    private function setPopupSocialAuthContext(string $provider, bool $popupRequested): void
    {
        $all = session(self::POPUP_SOCIAL_AUTH_SESSION_KEY, []);
        if (!is_array($all)) {
            $all = [];
        }

        if ($popupRequested) {
            $all[$provider] = true;
        } else {
            unset($all[$provider]);
        }

        session([self::POPUP_SOCIAL_AUTH_SESSION_KEY => $all]);
    }

    private function pullPopupSocialAuthContext(string $provider): bool
    {
        $all = session(self::POPUP_SOCIAL_AUTH_SESSION_KEY, []);
        if (!is_array($all)) {
            return false;
        }

        $isPopup = (bool) ($all[$provider] ?? false);
        unset($all[$provider]);
        session([self::POPUP_SOCIAL_AUTH_SESSION_KEY => $all]);

        return $isPopup;
    }

    private function intendedUrlForPopup(): string
    {
        $intended = session()->pull('url.intended');
        if (is_string($intended) && trim($intended) !== '') {
            return $intended;
        }

        return route('dashboard');
    }

    private function socialAuthPopupCompletionResponse(?string $redirectUrl = null): \Symfony\Component\HttpFoundation\Response
    {
        return response()->view('auth-social-popup-result', [
            'redirectUrl' => $redirectUrl,
        ]);
    }
}
