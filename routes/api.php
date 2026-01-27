<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AIAnalysisController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\EmailVerificationOtpController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LogoutController::class, 'destroy'])->middleware('auth:sanctum');

Route::get('/email/verification/status', [EmailVerificationOtpController::class, 'status']);
Route::post('/email/verification/request', [EmailVerificationOtpController::class, 'request'])
    ->middleware('throttle:3,1');
Route::post('/email/verification/verify', [EmailVerificationOtpController::class, 'verify'])
    ->middleware('throttle:10,1');
Route::post('/email/verification/code', [EmailVerificationOtpController::class, 'code'])
    ->middleware('throttle:30,1');

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/conversations/inbox', [ConversationController::class, 'inbox']);
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'sendReply']);
    Route::post('/v1/ai/inbox', [AIAnalysisController::class, 'inbox'])
        ->middleware('role:agent,lead');
    Route::post('/v1/ai/summary', [AIAnalysisController::class, 'summary'])
        ->middleware('role:agent,lead');
    Route::post('/v1/ai/reply', [AIAnalysisController::class, 'reply'])
        ->middleware('role:agent,lead');
});
