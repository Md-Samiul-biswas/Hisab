<?php

use App\Http\Controllers\AiOrderController;
use App\Http\Controllers\AppAuthController;
use App\Http\Controllers\CourierSettingsController;
use App\Http\Controllers\PathaoCourierController;
use App\Http\Controllers\SteadfastCourierController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

// Self-hosted Auth API (Zero Firebase)
Route::match(['get', 'post'], '/auth.php', [AppAuthController::class, 'handle']);
Route::get('/auth/status', [AppAuthController::class, 'status']);
Route::post('/auth/setup', [AppAuthController::class, 'setup']);
Route::post('/auth/login', [AppAuthController::class, 'login']);
Route::post('/auth/change-password', [AppAuthController::class, 'changePassword']);

// Real-time MySQL Sync API (Zero Firebase)
Route::match(['get', 'post'], '/sync.php', [SyncController::class, 'handle']);
Route::match(['get', 'post'], '/sync/{action?}', [SyncController::class, 'handle']);

// Courier Settings & Integration APIs
Route::get('/courier/settings', [CourierSettingsController::class, 'show']);
Route::post('/courier/settings', [CourierSettingsController::class, 'store']);

Route::prefix('/courier/pathao')->group(function () {
    Route::post('/test-connection', [PathaoCourierController::class, 'testConnection']);
    Route::post('/cities', [PathaoCourierController::class, 'cities']);
    Route::post('/zones', [PathaoCourierController::class, 'zones']);
    Route::post('/create-order', [PathaoCourierController::class, 'createOrder']);
    Route::post('/webhook', [PathaoCourierController::class, 'webhook']);
});

Route::post('/courier/steadfast/create-order', [SteadfastCourierController::class, 'createOrder']);

// AI Order, Receipt & Business Advisor Endpoints
Route::post('/ai-extract-order', [AiOrderController::class, 'extractOrder']);
Route::post('/ai-extract-expenses', [AiOrderController::class, 'extractExpenses']);
Route::post('/chat', [AiOrderController::class, 'chat']);
