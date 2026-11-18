<?php

use Illuminate\Support\Facades\Route;

Route::get('/app-mode.json', function () {
    return response()->json([
        'mode' => 'cpanel-php',
        'framework' => 'laravel',
        'ownerEmail' => 'samiulbiswas016@gmail.com',
    ]);
});

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api/).*$');
