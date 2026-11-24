<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->char('id_detail', 9)->primary();              // TDT000001 (maks 999.999 detail)
            $table->char('transaction_id', 9)->index();           // = transactions.id_transaksi
            $table->char('product_id', 7)->index();               // = products.id_produk
            $table->string('nama_produk', 100);                   // snapshot
            $table->decimal('harga_satuan', 10, 2);               // snapshot (sama dengan products.harga)
            $table->unsignedSmallInteger('jumlah');               // maks 65.535; aplikasi membatasi 9.999 per produk per transaksi
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->foreign('transaction_id')->references('id_transaksi')->on('transactions')->restrictOnDelete();
            $table->foreign('product_id')->references('id_produk')->on('products')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
    }
};
