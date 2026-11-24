<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->char('id_transaksi', 9)->primary();           // TRX000001 (maks 999.999 transaksi)
            $table->string('kode_transaksi', 24)->unique();       // BRN01-20261031-0001 (maks 10+1+8+1+4 = 24)
            $table->char('branch_id', 5);                         // = branches.id_cabang
            $table->char('user_id', 6);                           // = users.id_user (kasir)
            $table->char('idempotency_key', 36)->unique();        // UUID, cegah transaksi ganda
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('status', 10)->default('selesai');     // selesai | dibatalkan
            $table->dateTime('waktu_transaksi');
            $table->char('cancelled_by', 6)->nullable();          // = users.id_user
            $table->dateTime('cancelled_at')->nullable();
            $table->string('alasan_batal', 150)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'waktu_transaksi']);
            $table->index(['status', 'waktu_transaksi']);
            $table->foreign('branch_id')->references('id_cabang')->on('branches')->restrictOnDelete();
            $table->foreign('user_id')->references('id_user')->on('users')->restrictOnDelete();
            $table->foreign('cancelled_by')->references('id_user')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
