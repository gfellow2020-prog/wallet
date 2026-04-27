<?php

use App\Http\Controllers\Api\BuyRequestController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductCommentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductLikeController;
use App\Http\Controllers\Api\ProductSaleController;
use Illuminate\Support\Facades\Route;

/* ── Merchants ─── */
Route::get('/merchants', [MerchantController::class, 'index']);
Route::get('/merchants/{merchant}', [MerchantController::class, 'show']);

/* ── Orders ─── */
Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:api-money-write');
Route::get('/orders/{order}', [OrderController::class, 'show']);

/* ── Products ─── */
Route::get('/products/nearby', [ProductController::class, 'nearby']);
Route::get('/products/mine', [ProductController::class, 'mine']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::post('/products', [ProductController::class, 'store']);
Route::patch('/products/{product}', [ProductController::class, 'update']);
Route::post('/products/{product}/update', [ProductController::class, 'update']); // multipart method-spoof
Route::delete('/products/{product}', [ProductController::class, 'destroy']);
Route::post('/products/{product}/buy', [ProductSaleController::class, 'buy'])->middleware(['throttle:api-money-write', 'idempotent.money']);

/* ── Cart & Checkout ─── */
Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart', [CartController::class, 'store']);
Route::patch('/cart/{item}', [CartController::class, 'update']);
Route::delete('/cart/{item}', [CartController::class, 'destroy']);
Route::delete('/cart', [CartController::class, 'clear']);
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware(['throttle:api-money-write', 'idempotent.money']);

/* ── Buy-for-Me ─── */
Route::get('/buy-requests/mine', [BuyRequestController::class, 'mine']);
Route::get('/buy-requests/incoming', [BuyRequestController::class, 'incoming']);
Route::get('/buy-requests/incoming/count', [BuyRequestController::class, 'incomingCount']);
Route::post('/buy-requests', [BuyRequestController::class, 'store'])->middleware('throttle:api-buy-request-create');
Route::get('/buy-requests/{token}', [BuyRequestController::class, 'show']);
Route::delete('/buy-requests/{token}', [BuyRequestController::class, 'destroy']);
Route::post('/buy-requests/{token}/fulfill', [BuyRequestController::class, 'fulfill'])->middleware(['throttle:api-money-write', 'idempotent.money']);

/* ── Product Comments & Likes ─── */
Route::get('/products/{product}/comments', [ProductCommentController::class, 'index']);
Route::post('/products/{product}/comments', [ProductCommentController::class, 'store']);
Route::delete('/products/{product}/comments/{comment}', [ProductCommentController::class, 'destroy']);
Route::get('/products/{product}/like', [ProductLikeController::class, 'status']);
Route::post('/products/{product}/like', [ProductLikeController::class, 'toggle']);

