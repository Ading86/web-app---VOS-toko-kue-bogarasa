<?php

use App\Http\Controllers\StockBatchController;
use App\Http\Controllers\StockReceiptController;
use Illuminate\Support\Facades\Route;

// ---- Penerimaan Stok Gudang Pusat (Hanya akun dengan hak akses inventory.manage) ----
Route::middleware('can:inventory.manage')->group(function () {
    Route::get('stock-receipts', [StockReceiptController::class, 'index'])->name('stock-receipts.index');
    Route::get('stock-receipts/create', [StockReceiptController::class, 'create'])->name('stock-receipts.create');
    Route::post('stock-receipts', [StockReceiptController::class, 'store'])->name('stock-receipts.store');
    Route::get('stock-receipts/{stockReceipt}', [StockReceiptController::class, 'show'])->name('stock-receipts.show');
});

// ---- Kompatibilitas Batch Stok Legacy ----
Route::middleware('can:inventory.manage')->group(function () {
    Route::get('stock-batches/create', [StockBatchController::class, 'create'])->name('stock-batches.create');
    Route::post('stock-batches', [StockBatchController::class, 'store'])->name('stock-batches.store');
});

Route::middleware('can:inventory.view')->group(function () {
    Route::get('stock-batches', [StockBatchController::class, 'index'])->name('stock-batches.index');
});
