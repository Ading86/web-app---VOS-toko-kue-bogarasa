<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('code_sequences', function (Blueprint $table) {
            // Kunci: prefix ID ('PRD', 'TRX', ...) atau 'RCPT:BRN01:20261031' (19 karakter) untuk nomor struk harian.
            $table->string('sequence_key', 30)->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_sequences');
    }
};
