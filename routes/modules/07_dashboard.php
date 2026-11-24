<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:dashboard.view')->get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Monitoring real-time (polling AJAX): cek versi ringan, ambil data penuh hanya jika berubah.
Route::middleware('can:vos.view')->group(function () {
    Route::get('dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('dashboard/version', [DashboardController::class, 'version'])->name('dashboard.version');
});
