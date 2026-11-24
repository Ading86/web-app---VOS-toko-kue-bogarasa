<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Pastikan kolom yang dibutuhkan oleh model ada dengan nama yang benar
        Schema::table('stock_request_details', function (Blueprint $table) {
            if (Schema::hasColumn('stock_request_details', 'jumlah_diminta') && ! Schema::hasColumn('stock_request_details', 'jumlah_minta')) {
                $table->renameColumn('jumlah_diminta', 'jumlah_minta');
            }
            if (Schema::hasColumn('stock_request_details', 'jumlah_disetujui') && ! Schema::hasColumn('stock_request_details', 'jumlah_setuju')) {
                $table->renameColumn('jumlah_disetujui', 'jumlah_setuju');
            }
            if (Schema::hasColumn('stock_request_details', 'jumlah_dikirim') && ! Schema::hasColumn('stock_request_details', 'jumlah_kirim')) {
                $table->renameColumn('jumlah_dikirim', 'jumlah_kirim');
            }
            if (Schema::hasColumn('stock_request_details', 'jumlah_diterima') && ! Schema::hasColumn('stock_request_details', 'jumlah_terima')) {
                $table->renameColumn('jumlah_diterima', 'jumlah_terima');
            }
        });
    }

    public function down(): void
    {
        // Kembalikan nama kolom asal jika perlu
        Schema::table('stock_request_details', function (Blueprint $table) {
            if (Schema::hasColumn('stock_request_details', 'jumlah_minta')) {
                $table->renameColumn('jumlah_minta', 'jumlah_diminta');
            }
            if (Schema::hasColumn('stock_request_details', 'jumlah_setuju')) {
                $table->renameColumn('jumlah_setuju', 'jumlah_disetujui');
            }
            if (Schema::hasColumn('stock_request_details', 'jumlah_kirim')) {
                $table->renameColumn('jumlah_kirim', 'jumlah_dikirim');
            }
            if (Schema::hasColumn('stock_request_details', 'jumlah_terima')) {
                $table->renameColumn('jumlah_terima', 'jumlah_diterima');
            }
        });
    }
};