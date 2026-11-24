<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('branch_stocks', function (Blueprint $table) {
            $table->char('id_stok', 7)->primary();               // BST0001 (maks 9.999 baris = produk x cabang)
            $table->char('branch_id', 5);                        // = branches.id_cabang
            $table->char('product_id', 7);                       // = products.id_produk

            // Tiga "kantong" stok per cabang per produk. UNSIGNED: database menolak nilai negatif.
            // MEDIUMINT UNSIGNED (maks 16.777.215) cukup untuk batas aplikasi 1.000.000 per kantong.
            $table->unsignedMediumInteger('jumlah_stok')->default(0);     // stok siap jual (dipakai POS)
            $table->unsignedMediumInteger('stok_gudang')->default(0);     // khusus Cabang Pusat: persediaan induk sumber distribusi
            $table->unsignedMediumInteger('stok_perjalanan')->default(0); // kiriman menuju cabang ini yang belum dikonfirmasi
            $table->unsignedMediumInteger('stok_minimum')->default(0);    // batas "menipis" untuk stok siap jual
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
            $table->foreign('branch_id')->references('id_cabang')->on('branches')->restrictOnDelete();
            $table->foreign('product_id')->references('id_produk')->on('products')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_stocks');
    }
};
