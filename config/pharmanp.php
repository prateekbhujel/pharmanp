<?php

return [
    'product' => [
        'name' => 'PharmaNP',
        'developer_name' => 'Pratik Bhujel',
        'developer_email' => 'prateekbhujelpb@gmail.com',
        'release_channel' => env('PHARMANP_RELEASE_CHANNEL', 'Stable'),
        'repository' => 'https://github.com/prateekbhujel/pharmanp',
    ],

    'version' => env('PHARMANP_VERSION'),

    'developer_guide' => [
        'access_code' => '9862500130',
    ],

    'jwt' => [
        'issuer' => env('PHARMANP_JWT_ISSUER', env('APP_URL', 'pharmanp')),
        'audience' => env('PHARMANP_JWT_AUDIENCE', 'pharmanp-api'),
        'secret' => env('PHARMANP_JWT_SECRET'),
        'ttl_minutes' => (int) env('PHARMANP_JWT_TTL', 60 * 24),
    ],
];
