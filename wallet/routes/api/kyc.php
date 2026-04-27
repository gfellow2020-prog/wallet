<?php

use App\Http\Controllers\Api\KycController;
use Illuminate\Support\Facades\Route;

Route::prefix('kyc')->group(function () {
    Route::post('/', [KycController::class, 'submit']);
    Route::get('/status', [KycController::class, 'status']);
});

