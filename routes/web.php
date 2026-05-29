<?php

use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

use App\Http\Controllers\Admin\DashboardController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function (): void {
    Route::get('settings', [SettingsController::class, 'index'])->name('admin.settings.index');
    Route::post('settings', [SettingsController::class, 'update'])->name('admin.settings.update');
    Route::get('settings/connection-test', [SettingsController::class, 'testConnection'])->name('admin.settings.test');
    Route::get('settings/diagnostics', [SettingsController::class, 'diagnostics'])->name('admin.settings.diagnostics');
});

require __DIR__.'/settings.php';
