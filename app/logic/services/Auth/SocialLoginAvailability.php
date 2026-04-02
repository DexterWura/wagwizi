<?php

namespace App\Services\Auth;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

class SocialLoginAvailability
{
    public function googleCredentialsConfigured(): bool
    {
        return $this->credentialsFilled(config('services.google', []));
    }

    public function linkedinCredentialsConfigured(): bool
    {
        return $this->credentialsFilled(config('services.linkedin-openid', []));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function credentialsFilled(array $config): bool
    {
        $id = $config['client_id'] ?? null;
        $secret = $config['client_secret'] ?? null;

        return is_string($id) && $id !== ''
            && is_string($secret) && $secret !== '';
    }

    public function isGoogleEnabled(): bool
    {
        if (! $this->googleCredentialsConfigured()) {
            return false;
        }

        return $this->siteAllows('social_login_google');
    }

    public function isLinkedinEnabled(): bool
    {
        if (! $this->linkedinCredentialsConfigured()) {
            return false;
        }

        return $this->siteAllows('social_login_linkedin');
    }

    private function siteAllows(string $key): bool
    {
        try {
            if (! Schema::hasTable('site_settings')) {
                return true;
            }
            $v = SiteSetting::get($key, '1');

            return $v === '1' || $v === 1 || $v === true;
        } catch (\Throwable) {
            return true;
        }
    }
}
