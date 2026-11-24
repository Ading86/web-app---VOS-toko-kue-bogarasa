<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('stock_distributions') && ! Schema::hasColumn('stock_distributions', 'request_id')) {
            Schema::table('stock_distributions', function (Blueprint $table) {
                $table->char('request_id', 7)->nullable()->after('branch_asal_id');
                $table->foreign('request_id')->references('id_permintaan')->on('stock_requests')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_distributions', 'request_id')) {
            Schema::table('stock_distributions', function (Blueprint $table) {
                $table->dropForeign(['request_id']);
                $table->dropColumn('request_id');
            });
        }
    }
};