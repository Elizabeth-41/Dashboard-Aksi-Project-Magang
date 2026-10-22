<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // SPA disajikan dari origin yang sama; hanya origin aplikasi sendiri
    // (dan FRONTEND_URL bila SPA suatu saat dipisah) yang boleh memanggil API.
    'allowed_origins' => array_values(array_unique(array_filter([
        rtrim((string) env('APP_URL'), '/'),
        rtrim((string) env('FRONTEND_URL'), '/'),
    ]))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
