<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ProfileController;

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

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/bank', [ProfileController::class, 'updateBank'])->name('profile.bank');
});
