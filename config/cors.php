<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'broadcasting/auth',
        // ✅ ADDED FOR FILE DOWNLOADS
        'storage/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://172.24.24.69:3000', // frontend on phone
        // ✅ ADD YOUR PRODUCTION DOMAIN HERE
        // 'https://yourdomain.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Authorization',
        // ✅ ADDED FOR DOWNLOADS
        'Content-Disposition',
        'Content-Type',
        'Content-Length',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];
