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

    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function redirect(string $provider): \Symfony\Component\HttpFoundation\Response
    {
        if (! $this->authService->canUseSocialProvider($provider)) {
            return redirect()->route('login')
                ->withErrors(['social' => 'This login method is not available.']);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        if (! $this->authService->canUseSocialProvider($provider)) {
            return redirect()->route('login')
                ->withErrors(['social' => 'This login method is not available.']);
        }

        if ($request->has('error')) {
            $errorDesc = $request->input('error_description', 'Authorization was denied.');
            Log::warning('Social auth denied by user', ['provider' => $provider, 'error' => $errorDesc]);
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
            return redirect()->route('login')
                ->withErrors(['social' => 'Unable to authenticate. Please try again.']);
        }

        if (empty($socialUser->getEmail())) {
            Log::warning('Social auth returned no email', ['provider' => $provider]);
            return redirect()->route('login')
                ->withErrors(['social' => 'We could not retrieve your email address. Please ensure your account has a verified email.']);
        }

        try {
            $result = $this->authService->findOrCreateFromSocialite($provider, $socialUser);
        } catch (\RuntimeException $e) {
            return redirect()->route('login')
                ->withErrors(['social' => $e->getMessage()]);
        }

        $request->session()->regenerate();

        $normalizedProvider = $this->normalizedProvider($provider);
        if ($result['is_new']) {
            return redirect()->route('profile')
                ->with('info', 'Welcome! Please complete your profile to get started.')
                ->withCookie(cookie(self::LAST_SOCIAL_LOGIN_COOKIE, $normalizedProvider, 60 * 24 * 365));
        }

        return redirect()->intended('/dashboard')
            ->withCookie(cookie(self::LAST_SOCIAL_LOGIN_COOKIE, $normalizedProvider, 60 * 24 * 365));
    }

    private function normalizedProvider(string $provider): string
    {
        $p = strtolower(trim($provider));

        return in_array($p, ['google', 'linkedin-openid'], true) ? $p : 'google';
    }
}
