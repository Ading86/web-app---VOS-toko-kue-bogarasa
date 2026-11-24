<?php

use App\Http\Controllers\DistributionController;
use Illuminate\Support\Facades\Route;

// ---- Distribusi stok dari Cabang Pusat ----
// Urutan penting: 'distributions/create' harus didaftarkan sebelum 'distributions/{distribution}'.
Route::middleware('can:distributions.create')->group(function () {
    Route::get('distributions/create', [DistributionController::class, 'create'])->name('distributions.create');
    Route::post('distributions', [DistributionController::class, 'store'])->name('distributions.store');
});

Route::middleware('can:distributions.view')->group(function () {
    Route::get('distributions', [DistributionController::class, 'index'])->name('distributions.index');
    Route::get('distributions/{distribution}', [DistributionController::class, 'show'])->name('distributions.show');
});

Route::middleware('can:distributions.receive')->post('distributions/{distribution}/receive', [DistributionController::class, 'receive'])->name('distributions.receive');
Route::middleware('can:distributions.cancel')->post('distributions/{distribution}/cancel', [DistributionController::class, 'cancel'])->name('distributions.cancel');
