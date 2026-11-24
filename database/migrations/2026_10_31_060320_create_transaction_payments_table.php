<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->char('id_pembayaran', 9)->primary();          // PAY000001 (maks 999.999 pembayaran)
            $table->char('transaction_id', 9)->index();           // = transactions.id_transaksi
            $table->string('metode', 10);                         // tunai | transfer | qris
            $table->decimal('jumlah_bayar', 12, 2);
            $table->decimal('kembalian', 12, 2)->default(0);
            $table->string('referensi', 50)->nullable();          // no. referensi transfer/QRIS
            $table->timestamps();

            $table->foreign('transaction_id')->references('id_transaksi')->on('transactions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_payments');
    }
};
