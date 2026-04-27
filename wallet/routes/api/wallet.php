<?php

use App\Http\Controllers\Api\WalletApiController;
use App\Http\Controllers\Api\PushTokenController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferencesController;
use Illuminate\Support\Facades\Route;

Route::get('/me', [WalletApiController::class, 'me']);
Route::post('/me/profile-photo', [WalletApiController::class, 'updateProfilePhoto']);
Route::post('/me/push-tokens', [PushTokenController::class, 'store']);
Route::delete('/me/push-tokens/{id}', [PushTokenController::class, 'destroy']);
Route::get('/notifications', [NotificationController::class, 'index']);
Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
Route::get('/notifications/preferences', [NotificationPreferencesController::class, 'show']);
Route::post('/notifications/preferences', [NotificationPreferencesController::class, 'update']);

Route::get('/wallet/transactions', [WalletApiController::class, 'transactions']);
Route::post('/wallet/deposit', [WalletApiController::class, 'deposit'])->middleware('throttle:api-money-write');
Route::get('/wallet/deposits', [WalletApiController::class, 'deposits']);
Route::post('/wallet/withdraw', [WalletApiController::class, 'withdraw'])->middleware(['kyc', 'throttle:api-money-write']);
Route::get('/wallet/withdrawals', [WalletApiController::class, 'withdrawals']);
Route::post('/wallet/name-lookup', [WalletApiController::class, 'nameLookup'])->middleware('throttle:api-lookup');
Route::post('/wallet/send', [WalletApiController::class, 'sendMoney'])->middleware(['throttle:api-money-write', 'idempotent.money']);
Route::post('/wallet/send/otp/request', [WalletApiController::class, 'requestSendMoneyOtp'])->middleware(['throttle:api-money-write']);
Route::post('/wallet/send/otp/verify', [WalletApiController::class, 'verifySendMoneyOtp'])->middleware(['throttle:api-money-write', 'idempotent.money']);
Route::post('/wallet/request-money', [WalletApiController::class, 'requestMoney'])->middleware('throttle:api-money-write');

/* ── Lenco Utilities ─── */
Route::get('/lenco/banks', [WalletApiController::class, 'lencoBanks']);
Route::post('/lenco/resolve-account', [WalletApiController::class, 'lencoResolveAccount']);

/* ── Payout Accounts ─── */
Route::get('/payout-accounts', [WalletApiController::class, 'payoutAccounts']);
Route::post('/payout-accounts', [WalletApiController::class, 'storePayoutAccount']);
Route::delete('/payout-accounts/{id}', [WalletApiController::class, 'destroyPayoutAccount']);

/* ── QR Payments ─── */
Route::get('/qr-code', [WalletApiController::class, 'getUserQrCode']);
Route::post('/wallet/payment-qr-preview', [WalletApiController::class, 'previewPaymentQr'])->middleware('throttle:api-lookup');
Route::post('/qr-pay', [WalletApiController::class, 'processQrPayment'])->middleware(['throttle:api-money-write', 'idempotent.money']);

