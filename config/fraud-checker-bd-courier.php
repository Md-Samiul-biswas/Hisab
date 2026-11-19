<?php

return [
    'pathao' => [
        'user' => env('PATHAO_USER', env('PATHAO_USERNAME', 'budgetchoicebd@gmail.com')),
        'password' => env('PATHAO_PASSWORD', 'Tabiasami3@'),
    ],

    'redx' => [
        'phone' => env('REDX_PHONE'),
        'password' => env('REDX_PASSWORD'),
    ],

    'steadfast' => [
        'user' => env('STEADFAST_USER'),
        'password' => env('STEADFAST_PASSWORD'),
    ],

    'paperfly' => [
        'user' => env('PAPERFLY_USER'),
        'password' => env('PAPERFLY_PASSWORD'),
    ],

    'carrybee' => [
        'phone' => env('CARRYBEE_PHONE'),
        'password' => env('CARRYBEE_PASSWORD'),
    ],
];
