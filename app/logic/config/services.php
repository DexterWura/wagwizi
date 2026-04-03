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

    'linkedin-openid' => [
        'client_id'     => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect'      => env('LINKEDIN_AUTH_REDIRECT_URI', '/auth/linkedin/callback'),
    ],

];
