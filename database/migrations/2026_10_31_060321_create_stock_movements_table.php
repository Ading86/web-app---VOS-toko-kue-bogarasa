<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->char('id_pergerakan', 9)->primary();          // SMV000001 (maks 999.999 pergerakan)
            $table->char('branch_id', 5);                         // = branches.id_cabang
            $table->char('product_id', 7);                        // = products.id_produk
            $table->char('user_id', 6);                           // = users.id_user
            // Kantong stok yang berubah: jual | gudang | perjalanan (kolom branch_stocks terkait).
            $table->string('lokasi_stok', 10)->default('jual');
            // stok_masuk | stok_keluar | penyesuaian | penjualan | pembatalan |
            // distribusi_keluar | distribusi_jalan | distribusi_terima | distribusi_batal (terpanjang 17)
            $table->string('jenis_pergerakan', 20);
            $table->mediumInteger('jumlah');                      // bertanda: + menambah, - mengurangi (batas aplikasi 1.000.000)
            $table->unsignedMediumInteger('stok_sebelum');        // nilai kantong stok sebelum
            $table->unsignedMediumInteger('stok_sesudah');        // nilai kantong stok sesudah
            $table->string('keterangan', 150)->nullable();
            $table->string('reference_type', 30)->nullable();     // nama tabel sumber (maks 'stock_distributions' = 19)
            $table->string('reference_id', 9)->nullable();        // id sumber (maks 9 karakter)
            $table->timestamp('created_at')->nullable();          // tanpa updated_at: riwayat immutable

            $table->index(['branch_id', 'product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->foreign('branch_id')->references('id_cabang')->on('branches')->restrictOnDelete();
            $table->foreign('product_id')->references('id_produk')->on('products')->restrictOnDelete();
            $table->foreign('user_id')->references('id_user')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
