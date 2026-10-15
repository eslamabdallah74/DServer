<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminDashboardController;

// Public Root Redirect
Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// Root Health Check endpoint
Route::get('/health', [\App\Http\Controllers\Api\StatusController::class, 'getStatus']);
Route::get('/server/health', [\App\Http\Controllers\Api\StatusController::class, 'getStatus']);

// App Links & AdMob verification static endpoints
Route::get('/.well-known/assetlinks.json', function () {
    return response()->json([
        [
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => 'com.maskat.deceit',
                'sha256_cert_fingerprints' => [
                    '14:6D:E2:59:7A:B4:42:25:A3:34:6A:B9:BC:3C:93:33:DE:7E:6D:A9:7A:BC:B0:1B:3E:47:04:E2:E3:64:14:4E'
                ]
            ]
        ]
    ]);
});

foreach (['/app-ads.txt', '/ads.txt', '/server/app-ads.txt', '/server/ads.txt'] as $adsUri) {
    Route::get($adsUri, function () {
        return response("google.com, pub-6269931885550269, DIRECT, f08c47fec0942fa0\n", 200, [
            'Content-Type' => 'text/plain',
        ]);
    });
}

// Admin Authentication
Route::get('/admin/login', [AdminDashboardController::class, 'showLogin'])->name('admin.login');
Route::get('/login', [AdminDashboardController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [AdminDashboardController::class, 'login'])->name('admin.login.submit');
Route::post('/admin/logout', [AdminDashboardController::class, 'logout'])->name('admin.logout');

// Authenticated Admin Dashboard Routes
Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'dashboard']);
    Route::get('/dashboard', [AdminDashboardController::class, 'dashboard'])->name('admin.dashboard');
    
    // Live Match & Rooms Operations
    Route::get('/rooms', [AdminDashboardController::class, 'rooms'])->name('admin.rooms');
    Route::post('/rooms/{roomCode}/add-bot', [AdminDashboardController::class, 'addBotToRoom'])->name('admin.rooms.addBot');
    Route::post('/rooms/{roomCode}/next-phase', [AdminDashboardController::class, 'nextPhase'])->name('admin.rooms.nextPhase');
    Route::post('/rooms/{roomCode}/destroy', [AdminDashboardController::class, 'destroyRoom'])->name('admin.rooms.destroy');
    Route::delete('/rooms/{roomCode}', [AdminDashboardController::class, 'destroyRoom']);
    Route::post('/rooms/{roomCode}/players/{playerId}/kick', [AdminDashboardController::class, 'kickRoomPlayer'])->name('admin.rooms.kickPlayer');
    Route::delete('/rooms/{roomCode}/players/{playerId}', [AdminDashboardController::class, 'kickRoomPlayer']);

    // Global Live Broadcast Announcement
    Route::post('/broadcast', [AdminDashboardController::class, 'broadcast'])->name('admin.broadcast');

    // Game Analytics
    Route::get('/analytics', [AdminDashboardController::class, 'analytics'])->name('admin.analytics');

    // Players Directory & Economy
    Route::get('/players', [AdminDashboardController::class, 'players'])->name('admin.players');
    Route::post('/players/{id}/coins', [AdminDashboardController::class, 'updateCoins'])->name('admin.players.coins');
    Route::post('/players/{id}/ban', [AdminDashboardController::class, 'toggleBan'])->name('admin.players.ban');

    // Issues, Feedback & Audits
    Route::get('/issues', [AdminDashboardController::class, 'issues'])->name('admin.issues');
    Route::get('/feedback', [AdminDashboardController::class, 'feedback'])->name('admin.feedback');
    Route::get('/audit-logs', [AdminDashboardController::class, 'auditLogs'])->name('admin.auditLogs');
});
