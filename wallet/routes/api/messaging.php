<?php

use App\Http\Controllers\Api\MessagingController;
use Illuminate\Support\Facades\Route;

Route::get('/conversations', [MessagingController::class, 'conversations']);
Route::post('/conversations/direct', [MessagingController::class, 'directConversation'])->middleware('throttle:20,1');
Route::get('/conversations/{conversation}/messages', [MessagingController::class, 'messages']);
Route::post('/conversations/{conversation}/messages', [MessagingController::class, 'sendMessage'])->middleware('throttle:30,1');
Route::post('/conversations/{conversation}/read', [MessagingController::class, 'markRead'])->middleware('throttle:60,1');

Route::post('/users/{user}/block', [MessagingController::class, 'blockUser'])->middleware('throttle:20,1');
Route::delete('/users/{user}/block', [MessagingController::class, 'unblockUser'])->middleware('throttle:20,1');

Route::post('/messages/{message}/report', [MessagingController::class, 'reportMessage'])->middleware('throttle:10,1');
Route::get('/message-attachments/{attachment}', [MessagingController::class, 'downloadAttachment'])->middleware('throttle:120,1');

