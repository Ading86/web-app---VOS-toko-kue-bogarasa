<?php
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // stock_receipts dan stock_receipt_details sudah dibuat di migration 000006.
        // Migration ini dipertahankan agar urutan file tidak berubah.
        // No-op.
    }

    public function down(): void
    {
        // No-op.
    }
};
