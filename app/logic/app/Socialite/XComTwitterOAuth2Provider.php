<?php

namespace App\Socialite;

use Laravel\Socialite\Two\TwitterProvider;

/**
 * Uses x.com for the OAuth 2 authorize step so the flow matches the user's X session domain.
 */
class XComTwitterOAuth2Provider extends TwitterProvider
{
    public function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://x.com/i/oauth2/authorize', $state);
    }
}
