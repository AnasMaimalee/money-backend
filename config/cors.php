<?php
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',             // local dev
        'https://nor-slots-starsmerchant-carb.trycloudflare.com',
        'http://172.24.24.69:3000'
    ],

    'allowed_origins_patterns' => [
        '/^https:\/\/.*\.loca\.lt$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization'],

    'max_age' => 0,

    'supports_credentials' => false,

];
