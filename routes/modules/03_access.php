<?php

use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:users.view')->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
});

Route::middleware('can:users.manage')->group(function () {
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
});

Route::middleware('can:permissions.manage')->group(function () {
    Route::resource('roles', RoleController::class)->except('show');
});
