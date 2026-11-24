<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->char('id_cabang', 5)->primary();              // BRN01 (maks 99 cabang)
            $table->string('kode_cabang', 10)->unique();          // dipakai di kode struk; awalnya = id_cabang
            $table->string('nama_cabang', 60);
            $table->string('alamat', 150)->nullable();
            $table->string('nomor_telepon', 13)->nullable();      // hanya digit, maks 13 digit (standar nomor Indonesia)
            $table->boolean('is_pusat')->default(false);          // penanda Cabang Pusat distribusi
            // Penjaga di level database: hanya boleh ada SATU cabang pusat.
            // Kolom virtual bernilai 1 untuk cabang pusat dan NULL untuk lainnya; UNIQUE mengizinkan banyak NULL
            // tetapi menolak dua nilai 1.
            $table->unsignedTinyInteger('pusat_unik')->nullable()
                ->virtualAs('CASE WHEN is_pusat = 1 THEN 1 ELSE NULL END')->unique();
            $table->string('status', 10)->default('aktif');       // aktif | nonaktif
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
