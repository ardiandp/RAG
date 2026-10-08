<?php

use App\Http\Controllers\AgentsController;
use App\Http\Controllers\AuditLogsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/chat', [DashboardController::class, 'chat']);
    Route::get('/chat/{conversation}', [DashboardController::class, 'chatThread']);
    Route::post('/chat', [DashboardController::class, 'chatStore']);
    Route::get('/conversations', [DashboardController::class, 'conversations']);
    Route::get('/conversations/{conversation}', [DashboardController::class, 'conversation']);
    Route::get('/runs', [DashboardController::class, 'runs']);
    Route::get('/runs/{run}', [DashboardController::class, 'runShow']);
    Route::get('/tools', [DashboardController::class, 'tools']);
    Route::get('/knowledge', [DashboardController::class, 'knowledge']);
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::get('/audit-logs', [AuditLogsController::class, 'index']);
    Route::get('/agents', [AgentsController::class, 'index']);

    Route::middleware('can:manage_dashboard')->group(function () {
        Route::get('/agents/create', [AgentsController::class, 'create']);
        Route::get('/agents/{agent}/edit', [AgentsController::class, 'edit']);
        Route::post('/knowledge', [DashboardController::class, 'storeKnowledge']);
        Route::delete('/knowledge/{document}', [DashboardController::class, 'destroyDocument'])->name('knowledge.destroy');
        Route::post('/agents', [AgentsController::class, 'store']);
        Route::patch('/agents/{agent}/toggle', [AgentsController::class, 'toggle']);
        Route::put('/agents/{agent}', [AgentsController::class, 'update']);
        Route::delete('/agents/{agent}', [AgentsController::class, 'destroy']);
    });
});