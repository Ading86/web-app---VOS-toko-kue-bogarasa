<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Satu batch = satu lot produksi atau penerimaan stok untuk satu produk di satu cabang.
     *
     * Kue basah : masa_layak_jual = 1 hari → kedaluwarsa pada hari berikutnya.
     * Kue kering: masa_layak_jual = N hari → kedaluwarsa setelah N hari.
     */
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->char('id_batch', 9)->primary();               // SBT000001 (maks 999.999 batch)
            $table->char('branch_id', 5);                         // = branches.id_cabang
            $table->char('product_id', 7);                        // = products.id_produk
            $table->char('user_id', 6);                           // = users.id_user (yang mencatat)

            $table->date('tanggal_produksi');                     // tanggal produksi / penerimaan batch ini
            $table->date('tanggal_kedaluwarsa');                  // tanggal_produksi + masa_layak_jual produk

            $table->unsignedMediumInteger('jumlah_masuk');        // total stok masuk batch ini
            $table->unsignedMediumInteger('jumlah_terjual')->default(0);     // berkurang tiap penjualan
            $table->unsignedMediumInteger('jumlah_dibagikan')->default(0);   // dicatat saat penutupan harian
            $table->unsignedMediumInteger('jumlah_kadaluwarsa')->default(0); // kue kering yang dibuang

            // aktif     : masih dalam masa layak, tersedia untuk dijual
            // kedaluwarsa: sudah melewati tanggal kedaluwarsa
            $table->string('status', 12)->default('aktif');

            $table->string('keterangan', 150)->nullable();

            // Referensi ke pergerakan stok masuk yang membuat batch ini.
            $table->char('movement_id', 9)->nullable();           // = stock_movements.id_pergerakan

            $table->timestamps();

            $table->index(['branch_id', 'product_id', 'status', 'tanggal_kedaluwarsa'], 'sb_active_lookup');
            $table->index(['branch_id', 'tanggal_produksi'], 'sb_daily_closing');
            $table->index('product_id');

            $table->foreign('branch_id')->references('id_cabang')->on('branches')->restrictOnDelete();
            $table->foreign('product_id')->references('id_produk')->on('products')->restrictOnDelete();
            $table->foreign('user_id')->references('id_user')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
