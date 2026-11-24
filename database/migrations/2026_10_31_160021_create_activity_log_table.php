<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->char('id_log', 9)->primary();                 // LOG000001 (maks 999.999 aktivitas)
            $table->char('user_id', 6)->nullable();               // = users.id_user
            $table->char('branch_id', 5)->nullable();             // = branches.id_cabang
            $table->string('log_name', 20);                       // auth, user, permission, branch, product, stock, transaction, distribution, ...
            $table->string('aktivitas', 30);                      // login, created, updated, cancelled, received, minimum_diubah, ...
            $table->string('description', 250);
            $table->string('subject_type', 30)->nullable();       // nama tabel subjek (maks 'stock_distribution_details' = 26)
            $table->string('subject_id', 9)->nullable();          // id subjek (maks 9 karakter)
            $table->json('nilai_sebelum')->nullable();
            $table->json('nilai_sesudah')->nullable();
            $table->string('ip_address', 45)->nullable();         // IPv6 terpanjang
            $table->string('user_agent', 250)->nullable();        // dipotong 250 oleh ActivityLogService
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index('user_id');
            $table->index('branch_id');
            $table->index(['log_name', 'aktivitas']);
            $table->index(['subject_type', 'subject_id']);
            $table->foreign('user_id')->references('id_user')->on('users')->restrictOnDelete();
            $table->foreign('branch_id')->references('id_cabang')->on('branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
