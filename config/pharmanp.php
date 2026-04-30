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
];
