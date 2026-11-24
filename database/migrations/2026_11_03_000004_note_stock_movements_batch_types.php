<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Memperpanjang reference_id di stock_movements agar bisa menampung ID stock_batches (SBT000001 = 9 karakter)
     * dan daily_closings (DCL000001 = 9 karakter).
     * Kolom sebelumnya: CHAR(9) → sudah cukup; tidak perlu diubah (kedua prefix baru juga 9 karakter).
     * Migration ini HANYA menambahkan index baru untuk lookup batch.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Index tambahan: memudahkan query "semua pergerakan milik batch tertentu"
            // reference_type = 'stock_batches', reference_id = batch_id
            // Index yang ada ['reference_type', 'reference_id'] sudah mencukupi; tidak ada perubahan kolom.
        });
    }

    public function down(): void
    {
        // Tidak ada perubahan yang perlu di-rollback.
    }
};
