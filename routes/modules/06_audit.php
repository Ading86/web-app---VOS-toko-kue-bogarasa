<?php

use App\Http\Controllers\AuditController;
use Illuminate\Support\Facades\Route;

// Hanya baca. Sengaja TIDAK ada route ubah/hapus audit log.
Route::middleware('can:auditlogs.view')->group(function () {
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('audit/{activityLog}', [AuditController::class, 'show'])->name('audit.show');
});
