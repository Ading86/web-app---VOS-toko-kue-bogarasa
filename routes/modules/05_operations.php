<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

// ---- POS ----
Route::middleware('can:transactions.create')->group(function () {
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
});

// ---- Transaksi ----
Route::middleware('can:transactions.view')->group(function () {
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::get('transactions/{transaction}/receipt', [TransactionController::class, 'receipt'])->name('transactions.receipt');
});
Route::middleware('can:transactions.cancel')->post('transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])->name('transactions.cancel');

// ---- Persediaan ----
Route::middleware('can:inventory.view')->group(function () {
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
});
Route::middleware('can:inventory.manage')->group(function () {
    Route::get('inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
    Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::post('inventory/clear-basah', [InventoryController::class, 'clearBasah'])->name('inventory.clear-basah');
});
