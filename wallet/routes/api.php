<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MediaController;
use Illuminate\Support\Facades\Route;

/*
| Versioned API (preferred for new clients): /api/v1/...
| Legacy aliases (unchanged): /api/...
*/
$registerApiRoutes = function (): void {
    // Public auth routes
    require __DIR__.'/api/auth.php';

    // Public: categories (active only)
    Route::get('/categories', [CategoryController::class, 'index']);

    // Public media optimizations (no auth; safe for /storage/* public URLs)
    Route::get('/media/image', [MediaController::class, 'image']);

    // Protected (Sanctum token)
    Route::middleware(['auth:sanctum', 'api.log_context_auth', 'not_suspended'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        require __DIR__.'/api/wallet.php';
        require __DIR__.'/api/marketplace.php';
        require __DIR__.'/api/kyc.php';
        require __DIR__.'/api/rewards.php';
        require __DIR__.'/api/users.php';
        require __DIR__.'/api/messaging.php';

        // Admin (also protected by auth:sanctum above)
        require __DIR__.'/api/admin.php';

        // KYC-gated placeholder group
        Route::middleware('kyc')->group(function () {
            // Phase 14: Withdrawals will be registered here
        });
    });
};

Route::prefix('v1')->group($registerApiRoutes);
$registerApiRoutes();
