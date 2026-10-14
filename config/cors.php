<?php

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Hanya alamat frontend kita yang boleh mengakses API
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Kita pakai token Bearer, bukan cookie, jadi tidak perlu credentials
    'supports_credentials' => false,
];