<?php

use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\MerchantController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('admin')->group(function () {
    Route::get('/kyc', [KycController::class, 'index'])->middleware('can:kyc.view');
    Route::post('/kyc/{kyc}/review', [KycController::class, 'review'])->middleware('can:kyc.review');

    Route::post('/merchants', [MerchantController::class, 'store'])->middleware('can:merchants.create');
    Route::patch('/merchants/{merchant}', [MerchantController::class, 'update'])->middleware('can:merchants.update');
});

