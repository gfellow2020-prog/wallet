<?php

use App\Http\Controllers\Api\UserLookupController;
use Illuminate\Support\Facades\Route;

Route::post('/users/lookup', [UserLookupController::class, 'byExtracashNumber'])->middleware('throttle:api-lookup');

