<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('stock_requests')) {
            Schema::create('stock_requests', function (Blueprint $table) {
                $table->char('id_permintaan', 7)->primary();
                $table->char('branch_id', 5);
                $table->char('user_id', 6);
                $table->date('fecha_permintaan');
                $table->string('status', 20)->default('diajukan');
                $table->string('catatan_cabang', 255)->nullable();
                $table->char('approved_by', 6)->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('catatan_owner', 255)->nullable();
                $table->char('processed_by', 6)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->char('distribution_id', 7)->nullable();
                $table->timestamps();

                $table->foreign('branch_id')->references('id_cabang')->on('branches')->restrictOnDelete();
                $table->foreign('user_id')->references('id_user')->on('users')->restrictOnDelete();
                $table->foreign('approved_by')->references('id_user')->on('users')->nullOnDelete();
                $table->foreign('processed_by')->references('id_user')->on('users')->nullOnDelete();
                $table->foreign('distribution_id')->references('id_distribusi')->on('stock_distributions')->nullOnDelete();

                $table->index(['branch_id', 'status']);
                $table->index('fecha_permintaan');
            });
        } else {
            Schema::table('stock_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('stock_requests', 'branch_id')) {
                    $table->char('branch_id', 5)->after('id_permintaan');
                }
                if (! Schema::hasColumn('stock_requests', 'user_id')) {
                    $table->char('user_id', 6)->after('branch_id');
                }
                if (! Schema::hasColumn('stock_requests', 'fecha_permintaan')) {
                    $table->date('fecha_permintaan')->after('user_id');
                }
                if (! Schema::hasColumn('stock_requests', 'catatan_cabang')) {
                    $table->string('catatan_cabang', 255)->nullable()->after('status');
                }
                if (! Schema::hasColumn('stock_requests', 'approved_by')) {
                    $table->char('approved_by', 6)->nullable()->after('catatan_cabang');
                }
                if (! Schema::hasColumn('stock_requests', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }
                if (! Schema::hasColumn('stock_requests', 'catatan_owner')) {
                    $table->string('catatan_owner', 255)->nullable()->after('approved_at');
                }
                if (! Schema::hasColumn('stock_requests', 'processed_by')) {
                    $table->char('processed_by', 6)->nullable()->after('catatan_owner');
                }
                if (! Schema::hasColumn('stock_requests', 'processed_at')) {
                    $table->timestamp('processed_at')->nullable()->after('processed_by');
                }
                if (! Schema::hasColumn('stock_requests', 'distribution_id')) {
                    $table->char('distribution_id', 7)->nullable()->after('processed_at');
                }
            });
        }

        if (! Schema::hasTable('stock_request_details')) {
            Schema::create('stock_request_details', function (Blueprint $table) {
                $table->char('id_detail_permintaan', 9)->primary();
                $table->char('request_id', 7);
                $table->char('product_id', 7);
                $table->unsignedMediumInteger('jumlah_diminta');
                $table->unsignedMediumInteger('jumlah_disetujui');
                $table->unsignedMediumInteger('jumlah_kirim')->nullable();
                $table->unsignedMediumInteger('jumlah_terima')->nullable();
                $table->string('catatan_selisih', 255)->nullable();
                $table->timestamps();

                $table->foreign('request_id')->references('id_permintaan')->on('stock_requests')->cascadeOnDelete();
                $table->foreign('product_id')->references('id_produk')->on('products')->restrictOnDelete();
                $table->unique(['request_id', 'product_id']);
            });
        } else {
            // Tabel sudah ada, pastikan kolom yang dibutuhkan ada
            Schema::table('stock_request_details', function (Blueprint $table) {
                if (! Schema::hasColumn('stock_request_details', 'jumlah_diminta')) {
                    $table->unsignedMediumInteger('jumlah_diminta')->after('product_id');
                }
                if (! Schema::hasColumn('stock_request_details', 'jumlah_disetujui')) {
                    $table->unsignedMediumInteger('jumlah_disetujui')->after('jumlah_diminta');
                }
                if (! Schema::hasColumn('stock_request_details', 'jumlah_kirim')) {
                    $table->unsignedMediumInteger('jumlah_kirim')->nullable()->after('jumlah_disetujui');
                }
                if (! Schema::hasColumn('stock_request_details', 'jumlah_terima')) {
                    $table->unsignedMediumInteger('jumlah_terima')->nullable()->after('jumlah_kirim');
                }
                if (! Schema::hasColumn('stock_request_details', 'catatan_selisih')) {
                    $table->string('catatan_selisih', 255)->nullable()->after('jumlah_terima');
                }
            });
        }

        if (Schema::hasTable('stock_distributions') && ! Schema::hasColumn('stock_distributions', 'request_id')) {
            Schema::table('stock_distributions', function (Blueprint $table) {
                $table->char('request_id', 7)->nullable()->after('branch_asal_id');
                $table->foreign('request_id')->references('id_permintaan')->on('stock_requests')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_distributions', 'request_id')) {
            Schema::table('stock_distributions', function (Blueprint $table) {
                $table->dropForeign(['request_id']);
                $table->dropColumn('request_id');
            });
        }
    }
};