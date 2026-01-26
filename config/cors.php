<?php

return [
    'paths' => [
        'api/*',
        'broadcasting/auth',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'https://money-frontend-swart.vercel.app',
        'https://engaged-launch-locale-social.trycloudflare.com',
    ],

    'allowed_origins_patterns' => [
        '/^https:\/\/.*\.trycloudflare\.com$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
