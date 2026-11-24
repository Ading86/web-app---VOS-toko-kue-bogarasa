<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Satu baris = satu produk untuk satu cabang tujuan dalam satu distribusi.
        Schema::create('stock_distribution_details', function (Blueprint $table) {
            $table->char('id_detail_distribusi', 9)->primary();   // DDT000001 (maks 999.999 baris)
            $table->char('distribution_id', 7);                   // = stock_distributions.id_distribusi
            $table->char('product_id', 7);                        // = products.id_produk
            $table->char('branch_tujuan_id', 5);                  // = branches.id_cabang (boleh Cabang Pusat = alokasi penjualan sendiri)
            $table->unsignedMediumInteger('jumlah_dikirim');
            $table->date('tanggal_produksi')->nullable();
            $table->date('tanggal_kedaluwarsa')->nullable();
            $table->unsignedMediumInteger('jumlah_diterima')->nullable();  // NULL = belum dikonfirmasi
            $table->string('status', 10)->default('dikirim');     // dikirim | diterima | dibatalkan
            $table->char('diterima_oleh', 6)->nullable();         // = users.id_user
            $table->dateTime('waktu_diterima')->nullable();
            $table->string('catatan_selisih', 150)->nullable();   // wajib bila jumlah_diterima < jumlah_dikirim
            $table->timestamps();

            $table->unique(['distribution_id', 'product_id', 'branch_tujuan_id'], 'sdd_unique_line');
            $table->index(['branch_tujuan_id', 'status']);
            $table->index('product_id');
            $table->foreign('distribution_id')->references('id_distribusi')->on('stock_distributions')->restrictOnDelete();
            $table->foreign('product_id')->references('id_produk')->on('products')->restrictOnDelete();
            $table->foreign('branch_tujuan_id')->references('id_cabang')->on('branches')->restrictOnDelete();
            $table->foreign('diterima_oleh')->references('id_user')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_distribution_details');
    }
};
