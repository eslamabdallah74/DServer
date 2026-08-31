<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminDashboardController;

Route::get('/', [AdminDashboardController::class, 'showLogin']);
Route::get('/admin/login', [AdminDashboardController::class, 'showLogin']);

Route::get('/admin/dashboard', [AdminDashboardController::class, 'dashboard'])->name('admin.dashboard');
Route::get('/admin/players', [AdminDashboardController::class, 'players']);
Route::post('/admin/players/{id}/coins', [AdminDashboardController::class, 'updateCoins']);
Route::get('/admin/issues', [AdminDashboardController::class, 'issues']);
