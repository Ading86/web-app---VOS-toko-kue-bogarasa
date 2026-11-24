<?php
// Kolom tipe_kue dan masa_layak_jual sudah dimasukkan langsung ke create_products_table.
// Migration ini dipertahankan agar urutan file tidak berubah.
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // No-op: kolom sudah ada sejak create_products_table.
    }

    public function down(): void
    {
        // No-op.
    }
};
