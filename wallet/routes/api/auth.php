<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:api-register');
Route::post('/register/otp/verify', [AuthController::class, 'verifyRegisterOtp'])->middleware('throttle:api-register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api-login');
Route::post('/login/otp/verify', [AuthController::class, 'verifyLoginOtp'])->middleware('throttle:api-login');
Route::post('/nrc/verify', [AuthController::class, 'verifyNrc'])->middleware('throttle:5,1');

// Password reset (mobile): identifier -> OTP -> reset -> auto-login
Route::post('/password/otp/request', [AuthController::class, 'requestPasswordResetOtp'])->middleware('throttle:api-login');
Route::post('/password/otp/verify', [AuthController::class, 'verifyPasswordResetOtp'])->middleware('throttle:api-login');
Route::post('/password/reset', [AuthController::class, 'resetPasswordWithSession'])->middleware('throttle:api-login');

