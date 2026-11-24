<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->char('id_produk', 7)->primary();              // PRD0001 (maks 9.999 produk)
            $table->string('kode_produk', 20)->unique();          // awalnya = id_produk; boleh diganti Owner
            $table->char('category_id', 5);                      // = categories.id_kategori
            $table->string('nama_produk', 100);
            $table->string('deskripsi', 500)->nullable();
            $table->decimal('harga', 10, 2);                     // sampai 99.999.999,99
            $table->string('gambar', 100)->nullable();            // path relatif di disk public
            $table->string('status', 10)->default('aktif');
            $table->string('tipe_kue', 10)->default('standard'); // basah | kering | standard
            $table->unsignedSmallInteger('masa_layak_jual')->default(0); // hari; 0 = tanpa batas
            $table->timestamps();
            $table->softDeletes();

            $table->index('nama_produk');
            $table->index(['category_id', 'status']);
            $table->foreign('category_id')->references('id_kategori')->on('categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
