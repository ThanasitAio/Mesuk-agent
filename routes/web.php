<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\LogController;

// Redirect root to dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// --- Auth Routes ---
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth.agent');

// --- Protected Routes ---
Route::middleware('auth.agent')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Agents CRUD
    Route::resource('agents', AgentController::class)->except(['show']);

    // Logs
    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
});
