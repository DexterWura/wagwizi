<?php

namespace App\Controllers\Auth;

use App\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function showLogin(): View
    {
        return view('login');
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
        return view('signup');
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
