<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PlayerSyncController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Middleware\ValidateJwtSecret;

// Health check status endpoint
Route::get('/status', [StatusController::class, 'getStatus']);

// Public Auth Endpoints
Route::post('/auth/login', [AuthController::class, 'login']);

// Authenticated API Endpoints
Route::middleware([ValidateJwtSecret::class])->group(function () {
    Route::post('/sync/player', [PlayerSyncController::class, 'sync']);
    Route::get('/sync/player/{id}', [PlayerSyncController::class, 'getPlayer']);
    Route::post('/players', [PlayerSyncController::class, 'upsertPlayer']);
    Route::get('/players', [PlayerSyncController::class, 'getPlayers']);

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/issues', [IssueController::class, 'logIssue']);
    Route::post('/feedback', [IssueController::class, 'submitFeedback']);
});
