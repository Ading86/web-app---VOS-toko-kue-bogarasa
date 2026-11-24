<?php

use App\Http\Controllers\StockRequestController;
use Illuminate\Support\Facades\Route;

// ---- Modul Warehouse Management System: Permintaan & Distribusi Stok ----

// Form & Simpan Pengajuan Permintaan Stok (Cabang)
Route::middleware('can:stock_requests.create')->group(function () {
    Route::get('stock-requests/create', [StockRequestController::class, 'create'])->name('stock-requests.create');
    Route::post('stock-requests', [StockRequestController::class, 'store'])->name('stock-requests.store');
});

// Daftar & Detail Permintaan Stok
Route::middleware('can:stock_requests.view')->group(function () {
    Route::get('stock-requests', [StockRequestController::class, 'index'])->name('stock-requests.index');
    Route::get('stock-requests/{stockRequest}', [StockRequestController::class, 'show'])->name('stock-requests.show');
});

// Persetujuan & Penolakan Permintaan oleh Owner
Route::middleware('can:stock_requests.approve')->group(function () {
    Route::post('stock-requests/{stockRequest}/approve', [StockRequestController::class, 'approve'])->name('stock-requests.approve');
    Route::post('stock-requests/{stockRequest}/reject', [StockRequestController::class, 'reject'])->name('stock-requests.reject');
});

// Pemrosesan & Pengiriman Barang oleh Petugas Warehouse
Route::middleware('can:stock_requests.process')->group(function () {
    Route::post('stock-requests/{stockRequest}/dispatch', [StockRequestController::class, 'dispatch'])->name('stock-requests.dispatch');
});

// Konfirmasi Penerimaan Barang oleh Cabang
Route::middleware('can:stock_requests.receive')->group(function () {
    Route::post('stock-requests/{stockRequest}/receive', [StockRequestController::class, 'receive'])->name('stock-requests.receive');
});
