<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile
    |--------------------------------------------------------------------------
    |
    | Site and secret keys from the Cloudflare dashboard. When both are set,
    | Turnstile is enabled on protected forms and verified server-side.
    |
    | Test keys (always pass): site 1x00000000000000000AA, secret 1x0000000000000000000000000000000AA
    |
    */

    'site_key' => env('TURNSTILE_SITE_KEY'),

    'secret_key' => env('TURNSTILE_SECRET_KEY'),

    'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',

    'enabled' => filled(env('TURNSTILE_SITE_KEY')) && filled(env('TURNSTILE_SECRET_KEY')),

];
