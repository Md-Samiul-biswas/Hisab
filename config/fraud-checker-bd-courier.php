<?php

$pathaoUser = env('PATHAO_USER') ?: env('PATHAO_USERNAME') ?: 'budgetchoicebd@gmail.com';
$pathaoPass = env('PATHAO_PASSWORD') ?: 'Tabiasami3@';

return [
    'pathao' => [
        'user' => $pathaoUser,
        'password' => $pathaoPass,
    ],

    'redx' => [
        'phone' => env('REDX_PHONE') ?: '',
        'password' => env('REDX_PASSWORD') ?: '',
    ],

    'steadfast' => [
        'user' => env('STEADFAST_USER') ?: '',
        'password' => env('STEADFAST_PASSWORD') ?: '',
    ],

    'paperfly' => [
        'user' => env('PAPERFLY_USER') ?: '',
        'password' => env('PAPERFLY_PASSWORD') ?: '',
    ],

    'carrybee' => [
        'phone' => env('CARRYBEE_PHONE') ?: '',
        'password' => env('CARRYBEE_PASSWORD') ?: '',
    ],
];
