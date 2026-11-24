<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// ---- Cabang ----
Route::middleware('can:branches.view')->get('branches', [BranchController::class, 'index'])->name('branches.index');
Route::middleware('can:branches.manage')->group(function () {
    Route::get('branches/create', [BranchController::class, 'create'])->name('branches.create');
    Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
    Route::get('branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
    Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::post('branches/{branch}/toggle', [BranchController::class, 'toggle'])->name('branches.toggle');
});

// ---- Kategori ----
Route::middleware('can:categories.view')->get('categories', [CategoryController::class, 'index'])->name('categories.index');
Route::middleware('can:categories.manage')->group(function () {
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});

// ---- Produk ----
Route::middleware('can:products.view')->get('products', [ProductController::class, 'index'])->name('products.index');
Route::middleware('can:products.create')->group(function () {
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
});
Route::middleware('can:products.edit')->group(function () {
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::post('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');
});
Route::middleware('can:products.delete')->delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
