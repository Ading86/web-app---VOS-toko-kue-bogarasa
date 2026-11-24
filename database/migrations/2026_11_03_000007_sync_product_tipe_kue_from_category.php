<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sinkronisasi produk berdasarkan nama kategori jika tipe_kue masih standard
        $basahCategoryIds = Category::where('nama_kategori', 'like', '%basah%')->pluck('id_kategori');
        if ($basahCategoryIds->isNotEmpty()) {
            DB::table('products')
                ->whereIn('category_id', $basahCategoryIds)
                ->where('tipe_kue', Product::TIPE_STANDARD)
                ->update([
                    'tipe_kue'        => Product::TIPE_BASAH,
                    'masa_layak_jual' => 1,
                    'updated_at'      => now(),
                ]);
        }

        $keringCategoryIds = Category::where('nama_kategori', 'like', '%kering%')->pluck('id_kategori');
        if ($keringCategoryIds->isNotEmpty()) {
            DB::table('products')
                ->whereIn('category_id', $keringCategoryIds)
                ->where('tipe_kue', Product::TIPE_STANDARD)
                ->update([
                    'tipe_kue'        => Product::TIPE_KERING,
                    'masa_layak_jual' => 5,
                    'updated_at'      => now(),
                ]);
        }
    }

    public function down(): void
    {
        //
    }
};
