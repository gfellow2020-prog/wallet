<?php

use App\Http\Controllers\Api\RewardsController;
use Illuminate\Support\Facades\Route;

Route::prefix('rewards')->group(function () {
    Route::post('/check-in', [RewardsController::class, 'checkIn'])->middleware('throttle:api-rewards-checkin');
    Route::get('/summary', [RewardsController::class, 'summary']);
    Route::get('/missions', [RewardsController::class, 'missions']);
    Route::post('/missions/{mission}/claim', [RewardsController::class, 'claim']);
});

