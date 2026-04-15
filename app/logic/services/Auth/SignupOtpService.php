<?php

namespace App\Services\Auth;

use App\Models\SiteSetting;
use App\Services\Notifications\EmailTemplateRenderService;
use App\Services\Notifications\EmailTemplateService;
use App\Services\Notifications\NotificationChannelConfigService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class SignupOtpService
{
    private const SITE_SETTING_KEY = 'signup_email_otp_enabled';
    private const OTP_TEMPLATE_KEY = 'auth.signup_otp';
    private const OTP_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;
    private const SEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly EmailTemplateService $templates,
        private readonly EmailTemplateRenderService $render,
        private readonly NotificationChannelConfigService $mailer,
    ) {}

    public function isEnabled(): bool
    {
        return (string) SiteSetting::get(self::SITE_SETTING_KEY, '0') === '1';
    }

    /**
     * @param array{name: string, email: string, password_hash: string, referred_by_user_id: ?int} $pendingSignup
     */
    public function begin(array $pendingSignup): void
    {
        $email = $this->normalizeEmail((string) ($pendingSignup['email'] ?? ''));
        if ($email === '') {
            throw new RuntimeException('A valid email is required for OTP verification.');
        }

        $name = trim((string) ($pendingSignup['name'] ?? ''));
        $passwordHash = trim((string) ($pendingSignup['password_hash'] ?? ''));
        if ($name === '' || $passwordHash === '') {
            throw new RuntimeException('Incomplete signup payload for OTP verification.');
        }
        $this->assertSendCooldown($email);

        $code = $this->generateCode();
        $this->storePendingSignup($email, [
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'referred_by_user_id' => $pendingSignup['referred_by_user_id'] ?? null,
            'otp_hash' => $this->hashOtp($email, $code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES)->timestamp,
        ]);

        $this->sendOtpEmail($email, $name, $code);
    }

    public function resend(string $email): bool
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $pending = $this->pendingSignup($normalizedEmail);
        if ($pending === null) {
            return false;
        }
        $this->assertSendCooldown($normalizedEmail);

        $code = $this->generateCode();
        $pending['otp_hash'] = $this->hashOtp($normalizedEmail, $code);
        $pending['attempts'] = 0;
        $pending['expires_at'] = now()->addMinutes(self::OTP_TTL_MINUTES)->timestamp;
        $this->storePendingSignup($normalizedEmail, $pending);

        $this->sendOtpEmail($normalizedEmail, (string) ($pending['name'] ?? 'there'), $code);

        return true;
    }

    public function hasPendingSignup(string $email): bool
    {
        return $this->pendingSignup($email) !== null;
    }

    /**
     * @return array{ok: bool, reason?: string, pending?: array{name: string, email: string, password_hash: string, referred_by_user_id: ?int}}
     */
    public function verifyCode(string $email, string $code): array
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $pending = $this->pendingSignup($normalizedEmail);
        if ($pending === null) {
            return [
                'ok' => false,
                'reason' => 'This verification code expired. Please sign up again.',
            ];
        }

        $attempts = (int) ($pending['attempts'] ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->clear($normalizedEmail);

            return [
                'ok' => false,
                'reason' => 'Too many incorrect attempts. Please sign up again.',
            ];
        }

        $expectedHash = (string) ($pending['otp_hash'] ?? '');
        if ($expectedHash === '' || !hash_equals($expectedHash, $this->hashOtp($normalizedEmail, $code))) {
            $pending['attempts'] = $attempts + 1;
            $this->storePendingSignup($normalizedEmail, $pending);

            return [
                'ok' => false,
                'reason' => 'The verification code is incorrect.',
            ];
        }

        $this->clear($normalizedEmail);

        return [
            'ok' => true,
            'pending' => [
                'name' => (string) ($pending['name'] ?? ''),
                'email' => $normalizedEmail,
                'password_hash' => (string) ($pending['password_hash'] ?? ''),
                'referred_by_user_id' => isset($pending['referred_by_user_id']) ? (int) $pending['referred_by_user_id'] : null,
            ],
        ];
    }

    public function clear(string $email): void
    {
        Cache::forget($this->cacheKey($email));
    }

    public function ttlMinutes(): int
    {
        return self::OTP_TTL_MINUTES;
    }

    /**
     * @return array{name: string, email: string, password_hash: string, referred_by_user_id: ?int, otp_hash: string, attempts: int, expires_at: int}|null
     */
    private function pendingSignup(string $email): ?array
    {
        $normalizedEmail = $this->normalizeEmail($email);
        if ($normalizedEmail === '') {
            return null;
        }

        $value = Cache::get($this->cacheKey($normalizedEmail));
        if (!is_array($value)) {
            return null;
        }

        $expiresAt = (int) ($value['expires_at'] ?? 0);
        if ($expiresAt <= now()->timestamp) {
            $this->clear($normalizedEmail);
            return null;
        }

        return $value;
    }

    /**
     * @param array{name: string, email: string, password_hash: string, referred_by_user_id: ?int, otp_hash: string, attempts: int, expires_at: int} $payload
     */
    private function storePendingSignup(string $email, array $payload): void
    {
        $ttlSeconds = max(60, ((int) $payload['expires_at']) - now()->timestamp);
        Cache::put($this->cacheKey($email), $payload, Carbon::now()->addSeconds($ttlSeconds));
    }

    private function sendOtpEmail(string $email, string $name, string $code): void
    {
        $template = $this->templates->findByKey(self::OTP_TEMPLATE_KEY);
        if ($template === null) {
            throw new RuntimeException('Signup OTP email template is missing.');
        }

        $vars = array_merge(
            $this->render->samplePreviewVars(),
            [
                'userName' => $name !== '' ? $name : 'there',
                'otpCode' => $code,
                'otpExpiresMinutes' => (string) self::OTP_TTL_MINUTES,
            ]
        );
        $rendered = $this->render->renderTemplate($template, $vars);

        $this->mailer->sendHtml(
            $email,
            $rendered['subject'],
            $rendered['html'],
            $rendered['text']
        );
    }

    private function cacheKey(string $email): string
    {
        return 'signup_otp:' . sha1($this->normalizeEmail($email));
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hashOtp(string $email, string $code): string
    {
        return hash_hmac('sha256', $this->normalizeEmail($email) . '|' . $code, (string) config('app.key'));
    }

    private function assertSendCooldown(string $email): void
    {
        $key = 'signup_otp_cooldown:' . sha1($this->normalizeEmail($email));
        $allowed = Cache::add($key, '1', now()->addSeconds(self::SEND_COOLDOWN_SECONDS));
        if (!$allowed) {
            throw new RuntimeException('Please wait before requesting another verification code.');
        }
    }
}

