<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('stock_requests', 'fecha_permintaan') && ! Schema::hasColumn('stock_requests', 'tanggal_permintaan')) {
            Schema::table('stock_requests', function (Blueprint $table) {
                $table->renameColumn('fecha_permintaan', 'tanggal_permintaan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_requests', 'tanggal_permintaan') && ! Schema::hasColumn('stock_requests', 'fecha_permintaan')) {
            Schema::table('stock_requests', function (Blueprint $table) {
                $table->renameColumn('tanggal_permintaan', 'fecha_permintaan');
            });
        }
    }
};