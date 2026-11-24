<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Satu baris = satu kegiatan distribusi (satu "surat jalan") dari Cabang Pusat.
        Schema::create('stock_distributions', function (Blueprint $table) {
            $table->char('id_distribusi', 7)->primary();          // DST0001 (maks 9.999 distribusi)
            $table->char('branch_asal_id', 5);                   // = branches.id_cabang (Cabang Pusat saat dikirim)
            $table->char('user_id', 6);                           // = users.id_user (pembuat)
            $table->char('idempotency_key', 36)->unique();        // UUID, cegah distribusi ganda (double submit)
            $table->string('status', 10)->default('dikirim');     // dikirim | selesai | dibatalkan
            $table->dateTime('waktu_kirim');
            $table->string('catatan', 150)->nullable();
            $table->char('cancelled_by', 6)->nullable();          // = users.id_user
            $table->dateTime('cancelled_at')->nullable();
            $table->string('alasan_batal', 150)->nullable();
            $table->timestamps();

            $table->index(['status', 'waktu_kirim']);
            $table->index('branch_asal_id');
            $table->foreign('branch_asal_id')->references('id_cabang')->on('branches')->restrictOnDelete();
            $table->foreign('user_id')->references('id_user')->on('users')->restrictOnDelete();
            $table->foreign('cancelled_by')->references('id_user')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_distributions');
    }
};
