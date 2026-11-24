<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])->name('password.update');

    // Semua modul aplikasi: butuh akun aktif dan password sudah diganti.
    Route::middleware([EnsureUserIsActive::class, ForcePasswordChange::class])->group(function () {
        foreach (glob(__DIR__ . '/modules/*.php') as $moduleRoutes) {
            require $moduleRoutes;
        }
    });
});
