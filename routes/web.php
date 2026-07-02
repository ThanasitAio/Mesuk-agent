<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyBillingController;
use App\Http\Controllers\RentalRateController;

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

    // Logs
    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/bank', [ProfileController::class, 'updateBank'])->name('profile.bank');
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('profile.photo');
    Route::get('/avatar/{agentCode}', [ProfileController::class, 'viewAvatar'])->name('agent.avatar');

    // Property Billing Management
    Route::get('/properties', [PropertyBillingController::class, 'index'])->name('properties.index');
    Route::get('/properties/{property}', [PropertyBillingController::class, 'show'])->name('properties.show');
    Route::post('/billing/{record}/slip', [PropertyBillingController::class, 'uploadSlip'])->name('billing.slip.upload');
    Route::get('/billing/{record}/slip', [PropertyBillingController::class, 'viewSlip'])->name('billing.slip.view');
    Route::delete('/billing/{record}/slip', [PropertyBillingController::class, 'cancelSlip'])->name('billing.slip.cancel');
    Route::post('/properties/{property}/toggle-prepay', [PropertyBillingController::class, 'togglePrePay'])->name('properties.togglePrePay');
    Route::get('/invoices/{invoice}/print', [PropertyBillingController::class, 'printInvoice'])->name('invoices.print');

    // Rental Rate Overview
    Route::get('/rental-rates', [RentalRateController::class, 'index'])->name('rental-rates.index');

    // Deploy / System (เฉพาะ agent_code 0000390)
    Route::get('/deploy', [DeployController::class, 'show'])->name('deploy.show');
    Route::post('/deploy', [DeployController::class, 'run'])->name('deploy.run');
    Route::post('/deploy/migrate', [DeployController::class, 'runMigrations'])->name('deploy.migrate');
});
