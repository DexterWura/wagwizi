<?php

namespace App\Controllers\Auth;

use App\Controllers\Controller;
use App\Jobs\QueueTemplatedEmailForUserJob;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\SignupOtpService;
use App\Services\Auth\SocialLoginAvailability;
use App\Services\Audit\AuditTrailService;
use App\Services\Notifications\InAppNotificationService;
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
        private readonly SignupOtpService $signupOtpService,
        private readonly SocialLoginAvailability $socialLoginAvailability,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function showLogin(): View
    {
        $this->captureIntendedFromQuery(request());
        $viewData = $this->socialAuthViewData();
        $viewData['redirectTarget'] = $this->safeRedirectPath((string) request()->query('redirect', '')) ?? '';

        return view('login', $viewData);
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:8',
            'redirect' => 'nullable|string|max:500',
        ]);

        if (! empty($validated['redirect'])) {
            $this->captureIntendedFromQuery($request);
        }

        $result = $this->authService->attemptLogin(
            $validated['email'],
            $validated['password'],
            $request->boolean('remember')
        );

        if (!$result['success']) {
            $this->auditTrailService->record(
                category: 'auth',
                event: 'login_failed',
                request: $request,
                statusCode: 422,
                metadata: [
                    'email' => (string) ($validated['email'] ?? ''),
                    'reason' => (string) ($result['message'] ?? 'Login failed'),
                ],
            );

            return back()
                ->withErrors(['email' => $result['message']])
                ->withInput($request->only('email', 'redirect'));
        }

        $request->session()->regenerate();
        $user = $request->user();
        $this->auditTrailService->record(
            category: 'auth',
            event: 'login_success',
            userId: $user?->id ? (int) $user->id : null,
            request: $request,
            statusCode: 200,
            metadata: [
                'email' => (string) ($validated['email'] ?? ''),
            ],
        );

        return redirect()->intended('/dashboard');
    }

    public function showSignup(): View
    {
        $this->captureIntendedFromQuery(request());
        $viewData = $this->socialAuthViewData();
        $viewData['referralCode'] = trim((string) request()->query('ref', ''));
        $viewData['redirectTarget'] = $this->safeRedirectPath((string) request()->query('redirect', '')) ?? '';
        $viewData['signupOtpEnabled'] = $this->signupOtpService->isEnabled();

        return view('signup', $viewData);
    }

    public function showSignupOtpForm(Request $request): RedirectResponse|View
    {
        if (! $this->signupOtpService->isEnabled()) {
            return redirect()->route('signup');
        }

        $pendingEmail = (string) $request->session()->get('signup_otp_email', '');
        if ($pendingEmail === '' || ! $this->signupOtpService->hasPendingSignup($pendingEmail)) {
            $request->session()->forget('signup_otp_email');
            return redirect()->route('signup')->withErrors([
                'email' => 'Your signup verification session expired. Please sign up again.',
            ]);
        }

        return view('signup-otp', [
            'pendingEmail' => $pendingEmail,
            'maskedEmail' => $this->maskEmail($pendingEmail),
            'otpExpiresMinutes' => $this->signupOtpService->ttlMinutes(),
        ]);
    }

    public function showForgotPassword(): View
    {
        return view('forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
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

                QueueTemplatedEmailForUserJob::dispatch($user->id, 'auth.password_reset', [
                    'resetUrl' => $resetUrl,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send password reset link', [
                    'email' => $validated['email'],
                    'error' => $e->getMessage(),
                ]);
                try {
                    app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                        'admin_critical_password_reset_email',
                        'Password reset email failed',
                        'Could not send a reset link for ' . mb_substr($validated['email'], 0, 120) . ': ' . mb_substr($e->getMessage(), 0, 300),
                        route('admin.notifications.settings'),
                        [],
                        'pw_reset_email_fail:' . md5($e->getMessage()),
                        3600,
                    );
                } catch (\Throwable) {
                }
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
            'redirect' => 'nullable|string|max:500',
        ]);

        if (! empty($validated['redirect'])) {
            $this->captureIntendedFromQuery($request);
        }

        $referredByUserId = null;
        if (! empty($validated['referral_code'])) {
            $referredByUserId = User::query()
                ->where('referral_code', $validated['referral_code'])
                ->value('id');
        }

        if (! $this->signupOtpService->isEnabled()) {
            try {
                return $this->completeSignupAndRedirect(
                    $request,
                    $validated['name'],
                    $validated['email'],
                    Hash::make($validated['password']),
                    $referredByUserId ? (int) $referredByUserId : null
                );
            } catch (\RuntimeException $e) {
                return back()
                    ->withErrors(['email' => $e->getMessage()])
                    ->withInput($request->except('password', 'password_confirmation'));
            }
        }

        try {
            $this->signupOtpService->begin([
                'name' => (string) $validated['name'],
                'email' => (string) $validated['email'],
                'password_hash' => Hash::make((string) $validated['password']),
                'referred_by_user_id' => $referredByUserId ? (int) $referredByUserId : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch signup OTP email', [
                'email' => (string) $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withErrors(['email' => 'Could not send verification code right now. Please try again.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }

        $request->session()->put('signup_otp_email', strtolower(trim((string) $validated['email'])));

        return redirect()->route('signup.otp.form')
            ->with('success', 'We sent a 6-digit verification code to your email.');
    }

    public function verifySignupOtp(Request $request): RedirectResponse
    {
        if (! $this->signupOtpService->isEnabled()) {
            return redirect()->route('signup');
        }

        $validated = $request->validate([
            'otp_code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $pendingEmail = (string) $request->session()->get('signup_otp_email', '');
        if ($pendingEmail === '') {
            return redirect()->route('signup')->withErrors([
                'email' => 'Your signup verification session expired. Please sign up again.',
            ]);
        }

        $verification = $this->signupOtpService->verifyCode($pendingEmail, (string) $validated['otp_code']);
        if (! ($verification['ok'] ?? false)) {
            return back()->withErrors([
                'otp_code' => (string) ($verification['reason'] ?? 'Invalid verification code.'),
            ]);
        }

        $pending = $verification['pending'] ?? null;
        if (! is_array($pending)) {
            return redirect()->route('signup')->withErrors([
                'email' => 'Your signup verification session expired. Please sign up again.',
            ]);
        }

        $request->session()->forget('signup_otp_email');

        try {
            return $this->completeSignupAndRedirect(
                $request,
                (string) ($pending['name'] ?? ''),
                (string) ($pending['email'] ?? ''),
                (string) ($pending['password_hash'] ?? ''),
                isset($pending['referred_by_user_id']) ? (int) $pending['referred_by_user_id'] : null
            );
        } catch (\RuntimeException $e) {
            return redirect()->route('signup')
                ->withErrors(['email' => $e->getMessage()])
                ->withInput(['name' => (string) ($pending['name'] ?? ''), 'email' => (string) ($pending['email'] ?? '')]);
        }
    }

    public function resendSignupOtp(Request $request): RedirectResponse
    {
        if (! $this->signupOtpService->isEnabled()) {
            return redirect()->route('signup');
        }

        $pendingEmail = (string) $request->session()->get('signup_otp_email', '');
        if ($pendingEmail === '' || ! $this->signupOtpService->hasPendingSignup($pendingEmail)) {
            $request->session()->forget('signup_otp_email');
            return redirect()->route('signup')->withErrors([
                'email' => 'Your signup verification session expired. Please sign up again.',
            ]);
        }

        try {
            $resent = $this->signupOtpService->resend($pendingEmail);
        } catch (\Throwable $e) {
            Log::error('Failed to resend signup OTP', [
                'email' => $pendingEmail,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors([
                'otp_code' => 'Could not resend verification code right now. Please try again.',
            ]);
        }

        if (! $resent) {
            $request->session()->forget('signup_otp_email');
            return redirect()->route('signup')->withErrors([
                'email' => 'Your signup verification session expired. Please sign up again.',
            ]);
        }

        return back()->with('success', 'A new 6-digit verification code was sent to your email.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->auditTrailService->record(
            category: 'auth',
            event: 'logout',
            userId: $user?->id ? (int) $user->id : null,
            request: $request,
            statusCode: 200
        );

        $this->authService->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function captureIntendedFromQuery(Request $request): void
    {
        $redirect = $this->safeRedirectPath((string) $request->query('redirect', $request->input('redirect', '')));
        if ($redirect !== null) {
            $request->session()->put('url.intended', $redirect);
        }
    }

    private function safeRedirectPath(string $target): ?string
    {
        $target = trim($target);
        if ($target === '') {
            return null;
        }

        // Only allow in-app absolute paths to prevent open redirects.
        if (! str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return null;
        }

        return $target;
    }

    private function completeSignupAndRedirect(
        Request $request,
        string $name,
        string $email,
        string $passwordHash,
        ?int $referredByUserId
    ): RedirectResponse {
        $this->authService->registerWithPasswordHash(
            $name,
            $email,
            $passwordHash,
            $referredByUserId
        );

        $request->session()->regenerate();
        $user = $request->user();
        $this->auditTrailService->record(
            category: 'auth',
            event: 'signup_success',
            userId: $user?->id ? (int) $user->id : null,
            request: $request,
            statusCode: 201,
            metadata: [
                'email' => $email,
                'referred' => $referredByUserId !== null,
                'otp_verified' => $this->signupOtpService->isEnabled(),
            ],
        );

        return redirect()->intended('/dashboard');
    }

    private function maskEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '' || ! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $localLen = strlen($local);
        if ($localLen <= 2) {
            $maskedLocal = str_repeat('*', max(1, $localLen));
        } else {
            $maskedLocal = substr($local, 0, 1)
                . str_repeat('*', max(1, $localLen - 2))
                . substr($local, -1);
        }

        return $maskedLocal . '@' . $domain;
    }
}
