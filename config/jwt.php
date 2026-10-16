<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Access tokens (JWT, HS256)
    |--------------------------------------------------------------------------
    |
    | A dedicated secret, separate from APP_KEY. Generate one with:
    | openssl rand -base64 64
    |
    */

    'secret' => env('JWT_SECRET'),

    'ttl' => (int) env('JWT_TTL', 15 * 60),

    /*
    |--------------------------------------------------------------------------
    | Refresh tokens (opaque, one per device, rotated on every use)
    |--------------------------------------------------------------------------
    */

    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 60 * 24 * 60 * 60),

];
