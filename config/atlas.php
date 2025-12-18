<?php

$allowedRedirectHosts = array_filter(array_map('trim', explode(
    ',',
    env('ATLAS_ALLOWED_REDIRECT_HOSTS', '')
)));

return [

    /*
    |--------------------------------------------------------------------------
    | PIO Identity endpoint
    |--------------------------------------------------------------------------
    */

    'pio_url' => env('ATLAS_PIO_URL', 'https://pio.oremis.fr'),
    'pio_me'  => env('ATLAS_PIO_ME_ENDPOINT', '/api/me'),

    /*
    |--------------------------------------------------------------------------
    | Laravel authentication guard to use
    |--------------------------------------------------------------------------
    */

    'guard' => env('ATLAS_GUARD', 'web'),

    /*
    |--------------------------------------------------------------------------
    | Google OAuth2 parameters
    |--------------------------------------------------------------------------
    */

    'google_params' => [
        'hd' => 'oremis.fr',
        'prompt' => 'select_account',
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect URLs
    |--------------------------------------------------------------------------
    */

    'redirect_after_login' => '/',
    'redirect_after_logout' => '/',
    'allowed_redirect_hosts' => $allowedRedirectHosts ?: ['*.oremis.fr'], // hosts allowed for absolute redirect_to URLs

    /*
    |--------------------------------------------------------------------------
    | Local user model
    |--------------------------------------------------------------------------
    */

    'user_model' => App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | User status field
    |--------------------------------------------------------------------------
    */

    'status_field' => 'status',
    'suspended_values' => ['suspended', 'inactive'],

    /*
    |--------------------------------------------------------------------------
    | Status cache (seconds)
    |--------------------------------------------------------------------------
    */

    'status_cache_ttl' => 120,
];
