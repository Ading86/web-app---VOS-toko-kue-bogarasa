<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Http\Requests\CheckoutRequest;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockBatch;
use App\Services\StockBatchService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PosController extends Controller
{
    public function index(Request $request, StockBatchService $batchService)
    {
        $user = $request->user();

        if (! $user->branch_id) {
            return redirect('/dashboard')->with('error', 'POS hanya untuk akun yang ditugaskan ke cabang (Kasir).');
        }

        // Jalankan automatic stock expiration untuk cabang ini (sinkronisasi stok basi)
        $batchService->autoExpireStaleBatches($user->branch_id, $user->id);

        // Stok jual dari BranchStock (untuk produk standard)
        $stocks = BranchStock::where('branch_id', $user->branch_id)->pluck('jumlah_stok', 'product_id');

        // Untuk produk batch (kue basah & kue kering): hitung stok HANYA dari batch yang masih aktif dan layak jual di Asia/Jakarta
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $batchAvailable = StockBatch::withoutGlobalScopes()
            ->where('branch_id', $user->branch_id)
            ->where('status', StockBatch::STATUS_AKTIF)
            ->whereDate('tanggal_kedaluwarsa', '>=', $today)
            ->selectRaw('product_id, SUM(jumlah_masuk - jumlah_terjual - jumlah_kadaluwarsa) as sisa')
            ->groupBy('product_id')
            ->pluck('sisa', 'product_id')
            ->map(fn ($v) => max(0, (int) $v));

        $products = Product::where('status', 'aktif')->orderBy('nama_produk')->get()->map(function ($p) use ($stocks, $batchAvailable) {
            // Stok yang tampil di POS:
            // - Produk batch → stok batch layak jual (kue basah hari kemarin otomatis = 0)
            // - Produk standard → BranchStock jumlah_stok biasa
            $stok = $p->usesBatchStock()
                ? (int) ($batchAvailable[$p->id] ?? 0)
                : (int) ($stocks[$p->id] ?? 0);

            return [
                'id'          => $p->id,
                'kode'        => $p->kode_produk,
                'nama'        => $p->nama_produk,
                'harga'       => (float) $p->harga,
                'gambar'      => $p->gambar ? asset('storage/' . $p->gambar) : null,
                'kategori_id' => $p->category_id,
                'stok'        => $stok,
                'tipe_kue'    => $p->tipe_kue,
            ];
        });

        $categories = Category::orderBy('nama_kategori')->get(['id_kategori', 'nama_kategori']);

        return view('pos.index', compact('products', 'categories'));
    }

    public function checkout(CheckoutRequest $request, TransactionService $service)
    {
        try {
            $trx = $service->checkout(
                $request->user(),
                $request->items,
                $request->metode,
                (float) $request->jumlah_bayar,
                $request->referensi,
                $request->idempotency_key,
            );

            return response()->json([
                'ok'       => true,
                'kode'     => $trx->kode_transaksi,
                'redirect' => route('transactions.show', $trx->id),
            ]);
        } catch (StockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
