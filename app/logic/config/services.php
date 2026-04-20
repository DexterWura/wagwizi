<?php

return [

    /*
     * Paynow Zimbabwe: send `currency` on initiate transaction so the hosted page
     * does not let the payer pick a cheaper FX option. Disable if Paynow rejects the field.
     */
    'paynow' => [
        'send_currency_field' => filter_var(env('PAYNOW_SEND_CURRENCY_FIELD', true), FILTER_VALIDATE_BOOL),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_AUTH_REDIRECT_URI', '/auth/google/callback'),
    ],

    // Socialite uses services.* (not platforms.*). Without oauth => 2, Twitter falls back to OAuth1 and errors.
    'twitter' => [
        'client_id'     => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect'      => env('TWITTER_REDIRECT_URI', '/accounts/twitter/callback'),
        'oauth'         => 2,
    ],

    'facebook' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect'      => env('FACEBOOK_REDIRECT_URI', '/accounts/facebook/callback'),
    ],

    'facebook-pages' => [
        'client_id'     => env('FACEBOOK_PAGES_CLIENT_ID', env('FACEBOOK_CLIENT_ID')),
        'client_secret' => env('FACEBOOK_PAGES_CLIENT_SECRET', env('FACEBOOK_CLIENT_SECRET')),
        'redirect'      => env('FACEBOOK_PAGES_REDIRECT_URI', '/accounts/facebook_pages/callback'),
    ],

    'linkedin-openid' => [
        'client_id'     => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect'      => env('LINKEDIN_AUTH_REDIRECT_URI', '/auth/linkedin-openid/callback'),
    ],

    'linkedin-pages-openid' => [
        'client_id'     => env('LINKEDIN_PAGES_CLIENT_ID', env('LINKEDIN_CLIENT_ID')),
        'client_secret' => env('LINKEDIN_PAGES_CLIENT_SECRET', env('LINKEDIN_CLIENT_SECRET')),
        'redirect'      => env('LINKEDIN_PAGES_REDIRECT_URI', '/accounts/linkedin_pages/callback'),
    ],

];
