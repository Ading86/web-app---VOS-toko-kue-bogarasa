<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Header Penerimaan Stok Gudang Pusat
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->char('id_penerimaan', 9)->primary();          // RCV000001 (maks 999.999 penerimaan)
            $table->string('nomor_penerimaan', 30)->unique();
            $table->char('branch_id', 5);                         // = branches.id_cabang
            $table->char('user_id', 6);                           // = users.id_user
            $table->date('tanggal_penerimaan');
            $table->string('catatan', 250)->nullable();
            $table->unsignedSmallInteger('total_item')->default(0);
            $table->unsignedMediumInteger('total_kuantitas')->default(0);
            $table->timestamps();

            $table->foreign('branch_id')->references('id_cabang')->on('branches')->onDelete('restrict');
            $table->foreign('user_id')->references('id_user')->on('users')->onDelete('restrict');
            $table->index(['branch_id', 'tanggal_penerimaan']);
        });

        // 2. Detail Item Penerimaan Stok
        Schema::create('stock_receipt_details', function (Blueprint $table) {
            $table->char('id_detail_penerimaan', 9)->primary();   // RDT000001 (maks 999.999 detail)
            $table->char('receipt_id', 9);                        // = stock_receipts.id_penerimaan
            $table->char('product_id', 7);                        // = products.id_produk
            $table->unsignedMediumInteger('jumlah');
            $table->unsignedSmallInteger('masa_layak_jual');
            $table->date('tanggal_kedaluwarsa');
            $table->char('batch_id', 9)->nullable();              // = stock_batches.id_batch
            $table->timestamps();

            $table->foreign('receipt_id')->references('id_penerimaan')->on('stock_receipts')->onDelete('cascade');
            $table->foreign('product_id')->references('id_produk')->on('products')->onDelete('restrict');
            $table->foreign('batch_id')->references('id_batch')->on('stock_batches')->onDelete('set null');
            $table->index('receipt_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receipt_details');
        Schema::dropIfExists('stock_receipts');
    }
};
