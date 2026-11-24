<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('daily_closings');
    }

    public function down(): void
    {
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->char('id', 9)->primary();
            $table->char('branch_id', 5);
            $table->date('tanggal');
            $table->string('status', 10)->default('pending');
            $table->unsignedMediumInteger('total_sisa_basah')->default(0);
            $table->unsignedMediumInteger('total_dibagikan')->default(0);
            $table->unsignedMediumInteger('total_dibuang')->default(0);
            $table->char('user_id', 6)->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->string('catatan', 250)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'tanggal'], 'dc_branch_date_unique');
            $table->index(['branch_id', 'status']);
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });
    }
};
