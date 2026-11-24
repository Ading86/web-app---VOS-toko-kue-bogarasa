<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            // Pastikan kolom yang dibutuhkan oleh model ada
            if (! Schema::hasColumn('stock_requests', 'branch_id')) {
                $table->char('branch_id', 5)->after('id_permintaan');
            }
            if (! Schema::hasColumn('stock_requests', 'user_id')) {
                $table->char('user_id', 6)->after('branch_id');
            }
            if (! Schema::hasColumn('stock_requests', 'fecha_permintaan')) {
                $table->date('fecha_permintaan')->after('user_id');
            }
            if (! Schema::hasColumn('stock_requests', 'catatan_cabang')) {
                $table->string('catatan_cabang', 255)->nullable()->after('status');
            }
            if (! Schema::hasColumn('stock_requests', 'approved_by')) {
                $table->char('approved_by', 6)->nullable()->after('catatan_cabang');
            }
            if (! Schema::hasColumn('stock_requests', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('stock_requests', 'catatan_owner')) {
                $table->string('catatan_owner', 255)->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('stock_requests', 'processed_by')) {
                $table->char('processed_by', 6)->nullable()->after('catatan_owner');
            }
            if (! Schema::hasColumn('stock_requests', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('processed_by');
            }
            if (! Schema::hasColumn('stock_requests', 'distribution_id')) {
                $table->char('distribution_id', 7)->nullable()->after('processed_at');
            }
        });
    }

    public function down(): void
    {
        // Tidak perlu rollback, kolom lama masih diperlukan oleh aplikasi lain
    }
};