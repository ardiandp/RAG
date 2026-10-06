<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::middleware(['auth', 'can:manage_dashboard'])->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/conversations', [DashboardController::class, 'conversations']);
    Route::get('/conversations/{conversation}', [DashboardController::class, 'conversation']);
    Route::get('/runs', [DashboardController::class, 'runs']);
    Route::get('/tools', [DashboardController::class, 'tools']);
    Route::get('/knowledge', [DashboardController::class, 'knowledge']);
    Route::post('/knowledge', [DashboardController::class, 'storeKnowledge']);
});
