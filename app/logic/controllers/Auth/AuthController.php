<?php

namespace App\Controllers\Auth;

use App\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\SocialLoginAvailability;
use App\Services\Notifications\NotificationChannelConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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
        $viewData = $this->socialAuthViewData();
        $viewData['referralCode'] = trim((string) request()->query('ref', ''));

        return view('signup', $viewData);
    }

    public function showForgotPassword(): View
    {
        return view('forgot-password');
    }

    public function sendPasswordResetLink(
        Request $request,
        NotificationChannelConfigService $mailConfig,
    ): RedirectResponse {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if ($user !== null) {
            try {
                $token = Password::broker()->createToken($user);
                $resetUrl = URL::temporarySignedRoute(
                    'password.reset.form',
                    now()->addMinutes(60),
                    [
                        'email' => $user->email,
                        'token' => $token,
                    ]
                );

                $html = view('emails.password-reset-link', [
                    'user' => $user,
                    'resetUrl' => $resetUrl,
                ])->render();

                $mailConfig->sendHtml(
                    $user->email,
                    config('app.name') . ' password reset',
                    $html,
                    "Use this link to reset your password: {$resetUrl}"
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send password reset link', [
                    'email' => $validated['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'If an account exists for that email, a reset link has been sent.');
    }

    public function showResetPassword(Request $request): View
    {
        return view('reset-password', [
            'email' => (string) $request->query('email', ''),
            'token' => (string) $request->query('token', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker()->reset(
            [
                'email' => $validated['email'],
                'token' => $validated['token'],
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password reset successful. You can now sign in.');
        }

        return back()
            ->withErrors(['email' => __($status)])
            ->withInput($request->only('email'));
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
            'referral_code' => 'nullable|string|max:24|exists:users,referral_code',
        ]);

        $referredByUserId = null;
        if (! empty($validated['referral_code'])) {
            $referredByUserId = User::query()
                ->where('referral_code', $validated['referral_code'])
                ->value('id');
        }

        $this->authService->register(
            $validated['name'],
            $validated['email'],
            $validated['password'],
            $referredByUserId ? (int) $referredByUserId : null
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
