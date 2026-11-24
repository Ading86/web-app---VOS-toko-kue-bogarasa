<?php
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // stock_receipts sudah mencakup kolom tanggal_produksi dan tanggal_kedaluwarsa
        // langsung di create_stock_distribution_details migration yang baru.
        // No-op.
    }

    public function down(): void
    {
        // No-op.
    }
};
