<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PlayerSyncController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\RoomApiController;
use App\Http\Middleware\ValidateJwtSecret;

// Health check endpoints
Route::get('/status', [StatusController::class, 'getStatus']);
Route::get('/health', [StatusController::class, 'getStatus']);

// Client initialization token endpoint
Route::post('/token', function () {
    return response()->json([
        'success' => true,
        'token' => env('JWT_SECRET', 'deceit_offline_super_secret_jwt_key_2026_998877665544332211'),
    ]);
});

// Public Auth Endpoints
Route::post('/auth/login', [AuthController::class, 'login']);

// Multiplayer Online Room Endpoints (Accessible by mobile app & web clients)
Route::prefix('rooms')->group(function () {
    Route::post('/create', [RoomApiController::class, 'create']);
    Route::post('/join', [RoomApiController::class, 'join']);

    Route::post('/{roomCode}/start', [RoomApiController::class, 'start']);
    Route::post('/{roomCode}/ready', [RoomApiController::class, 'ready']);
    Route::post('/{roomCode}/action', [RoomApiController::class, 'action']);
    Route::post('/{roomCode}/vote', [RoomApiController::class, 'vote']);
    Route::post('/{roomCode}/add-bot', [RoomApiController::class, 'addBot']);
    Route::post('/{roomCode}/remove-bot', [RoomApiController::class, 'removeBot']);
    Route::post('/{roomCode}/kick-player', [RoomApiController::class, 'kickPlayer']);
    Route::post('/{roomCode}/chat', [RoomApiController::class, 'chat']);
    Route::post('/{roomCode}/leave', [RoomApiController::class, 'leave']);
    Route::post('/{roomCode}/settings', [RoomApiController::class, 'settings']);
    Route::post('/{roomCode}/veto', [RoomApiController::class, 'veto']);

    Route::get('/{roomCode}', [RoomApiController::class, 'show']);
    Route::get('/{roomCode}/sync', [RoomApiController::class, 'sync']);
});

// Player profile sync, Issues & Feedback Endpoints (with fallback / JWT authentication)
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
