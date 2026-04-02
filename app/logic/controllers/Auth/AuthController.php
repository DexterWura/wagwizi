<?php

namespace App\Controllers\Auth;

use App\Controllers\Controller;
use App\Services\Auth\AuthService;
use App\Services\Auth\SocialLoginAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly SocialLoginAvailability $socialLoginAvailability,
    ) {}

    public function showLogin(): View
    {
        return view('login', $this->socialAuthViewData());
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        $result = $this->authService->attemptLogin(
            $validated['email'],
            $validated['password'],
            $request->boolean('remember')
        );

        if (!$result['success']) {
            return back()
                ->withErrors(['email' => $result['message']])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function showSignup(): View
    {
        return view('signup', $this->socialAuthViewData());
    }

    /**
     * @return array<string, bool>
     */
    private function socialAuthViewData(): array
    {
        return [
            'socialGoogleEnabled'   => $this->socialLoginAvailability->isGoogleEnabled(),
            'socialLinkedinEnabled' => $this->socialLoginAvailability->isLinkedinEnabled(),
        ];
    }

    public function signup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $this->authService->register(
            $validated['name'],
            $validated['email'],
            $validated['password']
        );

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
