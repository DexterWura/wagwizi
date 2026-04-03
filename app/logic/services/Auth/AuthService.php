<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Subscription\DefaultSubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthService
{
    private const SUPPORTED_PROVIDERS = ['google', 'linkedin-openid'];

    public function __construct(
        private readonly SocialLoginAvailability $socialLoginAvailability,
    ) {}

    public function attemptLogin(string $email, string $password, bool $remember = false): array
    {
        $credentials = ['email' => $email, 'password' => $password];

        if (!Auth::attempt($credentials, $remember)) {
            Log::info('Login failed: invalid credentials', ['email' => $email]);
            return [
                'success' => false,
                'message' => 'Invalid email or password.',
            ];
        }

        $user = Auth::user();

        if ($user->status !== 'active') {
            Auth::logout();
            Log::warning('Login blocked: account suspended', ['user_id' => $user->id, 'email' => $email]);
            return [
                'success' => false,
                'message' => 'Your account has been suspended. Contact support.',
            ];
        }

        $user->forceFill(['last_login_at' => now()])->save();

        Log::info('User logged in', ['user_id' => $user->id, 'email' => $email]);
        return ['success' => true, 'user' => $user];
    }

    public function register(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            $user = User::create([
                'name'     => $name,
                'email'    => $email,
                'password' => $password,
            ]);

            Auth::login($user);

            app(DefaultSubscriptionService::class)->assignFreePlanToUser($user);

            Log::info('New user registered', ['user_id' => $user->id, 'email' => $email]);

            return $user;
        });
    }

    /**
     * Find an existing user by social provider ID or email, or create a new one.
     * Returns ['user' => User, 'is_new' => bool].
     */
    public function findOrCreateFromSocialite(string $provider, SocialiteUser $socialUser): array
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS)) {
            throw new \InvalidArgumentException("Unsupported auth provider: {$provider}");
        }

        $providerIdColumn = $this->providerColumn($provider);

        return DB::transaction(function () use ($provider, $socialUser, $providerIdColumn): array {
            $user = User::where($providerIdColumn, $socialUser->getId())->first();

            if ($user !== null) {
                $this->loginSocialUser($user, $provider, 'existing account (provider match)');
                return ['user' => $user, 'is_new' => false];
            }

            $email = $socialUser->getEmail();

            if ($email !== null) {
                $user = User::where('email', $email)->first();

                if ($user !== null) {
                    $user->update([$providerIdColumn => $socialUser->getId()]);
                    $this->loginSocialUser($user, $provider, 'existing account (email match)');
                    return ['user' => $user, 'is_new' => false];
                }
            }

            $user = User::create([
                'name'              => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                'email'             => $email,
                $providerIdColumn   => $socialUser->getId(),
                'avatar_path'       => $socialUser->getAvatar(),
                'profile_completed' => false,
            ]);

            Auth::login($user, true);

            app(DefaultSubscriptionService::class)->assignFreePlanToUser($user);

            Log::info('New user registered via social auth', [
                'user_id'  => $user->id,
                'provider' => $provider,
                'email'    => $email,
            ]);

            return ['user' => $user, 'is_new' => true];
        });
    }

    public function isSupportedProvider(string $provider): bool
    {
        return in_array($provider, self::SUPPORTED_PROVIDERS);
    }

    public function canUseSocialProvider(string $provider): bool
    {
        if (! $this->isSupportedProvider($provider)) {
            return false;
        }

        return match ($provider) {
            'google'          => $this->socialLoginAvailability->isGoogleEnabled(),
            'linkedin-openid' => $this->socialLoginAvailability->isLinkedinEnabled(),
            default           => false,
        };
    }

    public function logout(): void
    {
        $userId = Auth::id();
        Auth::logout();
        Log::info('User logged out', ['user_id' => $userId]);
    }

    private function providerColumn(string $provider): string
    {
        return match ($provider) {
            'google'          => 'google_id',
            'linkedin-openid' => 'linkedin_id',
            default           => throw new \InvalidArgumentException("No column for provider: {$provider}"),
        };
    }

    private function loginSocialUser(User $user, string $provider, string $matchType): void
    {
        if ($user->status !== 'active') {
            throw new \RuntimeException('Your account has been suspended. Contact support.');
        }

        Auth::login($user, true);

        $user->forceFill(['last_login_at' => now()])->save();

        Log::info('User logged in via social auth', [
            'user_id'    => $user->id,
            'provider'   => $provider,
            'match_type' => $matchType,
        ]);
    }
}
