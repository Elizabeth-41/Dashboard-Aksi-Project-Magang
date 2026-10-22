<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | "enforce" — kebijakan ditegakkan (produksi).
    | "report"  — pelanggaran hanya dilaporkan ke console browser.
    | "off"     — tidak dikirim. Default di luar produksi, karena Vite dev
    |             server (npm run dev) dan skrip debug memakai skrip inline.
    |
    */

    'csp' => env('CSP_MODE', in_array(env('APP_ENV'), ['staging', 'production'], true) ? 'enforce' : 'off'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Hanya dikirim bila permintaan datang lewat HTTPS.
    |
    */

    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),

];
