<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Http\Requests\StockRequest;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\ActivityLogService;
use App\Services\StockService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isOwner = $user->isOwner();
        $branchId = $isOwner ? $request->input('branch_id') : $user->branch_id;

        // Jalankan automatic stock expiration agar stok basi tidak ditampilkan sebagai stok tersedia
        app(\App\Services\StockBatchService::class)->autoExpireStaleBatches($branchId);

        $stocks = BranchStock::query()
            ->join('products', 'products.id_produk', '=', 'branch_stocks.product_id')
            ->whereNull('products.deleted_at')
            ->select('branch_stocks.*')
            ->with(['product.category', 'branch'])
            ->when($branchId, fn ($q, $b) => $q->where('branch_stocks.branch_id', $b))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('products.nama_produk', 'like', "%{$s}%")->orWhere('products.kode_produk', 'like', "%{$s}%")))
            ->when($request->boolean('low'), fn ($q) => $q->whereColumn('branch_stocks.jumlah_stok', '<=', 'branch_stocks.stok_minimum'))
            ->orderBy('products.nama_produk')->orderBy('branch_stocks.branch_id')
            ->paginate(20)->withQueryString();

        $hasBasahStock = BranchStock::query()
            ->join('products', 'products.id_produk', '=', 'branch_stocks.product_id')
            ->leftJoin('categories', 'categories.id_kategori', '=', 'products.category_id')
            ->whereNull('products.deleted_at')
            ->when($branchId, fn ($q, $b) => $q->where('branch_stocks.branch_id', $b))
            ->where(function ($w) {
                $w->where('products.tipe_kue', Product::TIPE_BASAH)
                  ->orWhere('categories.nama_kategori', 'like', '%basah%');
            })
            ->where(function ($w) {
                $w->where('branch_stocks.jumlah_stok', '>', 0)
                  ->orWhere('branch_stocks.stok_gudang', '>', 0);
            })
            ->exists();

        return view('inventory.index', [
            'stocks' => $stocks,
            'branches' => $isOwner ? Branch::orderBy('nama_cabang')->get() : collect(),
            'showBranch' => $isOwner && ! $branchId,
            'hasBasahStock' => $hasBasahStock,
            'currentBranchId' => $branchId,
        ]);
    }

    public function movements(Request $request)
    {
        $user = $request->user();
        $isOwner = $user->isOwner();
        $branchId = $isOwner ? $request->input('branch_id') : $user->branch_id;

        $movements = StockMovement::query()->with(['product', 'user:id_user,name', 'branch:id_cabang,kode_cabang,nama_cabang'])
            ->when($branchId, fn ($q, $b) => $q->where('branch_id', $b))
            ->when($request->product_id, fn ($q, $p) => $q->where('product_id', $p))
            ->when($request->jenis, fn ($q, $j) => $q->where('jenis_pergerakan', $j))
            ->when($request->lokasi, fn ($q, $l) => $q->where('lokasi_stok', $l))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('created_at')->orderByDesc('id_pergerakan')
            ->paginate(25)->withQueryString();

        return view('inventory.movements', [
            'movements' => $movements,
            'products' => Product::orderBy('nama_produk')->get(['id_produk', 'nama_produk']),
            'branches' => $isOwner ? Branch::orderBy('nama_cabang')->get() : collect(),
            'jenisLabels' => StockMovement::JENIS_LABELS,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        return view('inventory.form', [
            'branches' => $user->isOwner() ? Branch::where('status', 'aktif')->orderBy('nama_cabang')->get() : collect(),
            'products' => Product::where('status', 'aktif')->orderBy('nama_produk')->get(['id_produk', 'nama_produk', 'kode_produk']),
            'preBranch' => $request->input('branch_id'),
            'preProduct' => $request->input('product_id'),
            'userBranchIsPusat' => (bool) $user->branch?->is_pusat,
        ]);
    }

    public function store(StockRequest $request, StockService $stock, ActivityLogService $audit)
    {
        $user = $request->user();
        $branchId = $user->isOwner() ? $request->input('branch_id') : $user->branch_id;   // kasir tidak bisa memilih cabang

        if (! $branchId) {
            return back()->withInput()->withErrors(['branch_id' => 'Pilih cabang terlebih dahulu.']);
        }

        $branch = Branch::find($branchId);
        $pid = $request->input('product_id');
        $jenis = $request->input('jenis');
        $n = (int) $request->input('jumlah');
        $ket = $request->input('keterangan');
        $nama = Product::whereKey($pid)->value('nama_produk');
        $lokasi = $request->input('lokasi') ?: BranchStock::POOL_JUAL;

        if ($lokasi === BranchStock::POOL_GUDANG && ! $branch?->is_pusat) {
            return back()->withInput()->with('error', 'Stok gudang hanya ada di Cabang Pusat.');
        }

        // Stok masuk langsung = produksi/penerimaan di Cabang Pusat (masuk ke gudang). Cabang lain menerima stok lewat distribusi.
        if ($jenis === StockMovement::MASUK && ! $branch?->is_pusat) {
            return back()->withInput()->with('error', 'Stok masuk langsung hanya untuk Cabang Pusat (masuk ke stok gudang). Cabang lain menerima stok lewat menu Distribusi Stok.');
        }

        $pool = $jenis === StockMovement::MASUK ? BranchStock::POOL_GUDANG : $lokasi;
        $poolLabel = $pool === BranchStock::POOL_GUDANG ? 'gudang' : 'stok jual';

        try {
            if ($jenis === 'minimum') {
                [$old, $new] = $stock->setMinimum($branchId, $pid, $n);
                $audit->log('stock', 'minimum_diubah', "Mengubah stok minimum {$nama}", null,
                    ['stok_minimum' => $old], ['stok_minimum' => $new, 'product_id' => $pid], $branchId);
            } else {
                $movement = match ($jenis) {
                    StockMovement::MASUK => $stock->change($branchId, $pid, $n, StockMovement::MASUK, $user->id, $ket, null, null, $pool),
                    StockMovement::KELUAR => $stock->change($branchId, $pid, -$n, StockMovement::KELUAR, $user->id, $ket, null, null, $pool),
                    StockMovement::PENYESUAIAN => $stock->setTo($branchId, $pid, $n, $user->id, $ket, $pool),
                };
                $audit->log('stock', $jenis, "Perubahan {$poolLabel} {$nama}: {$movement->stok_sebelum} → {$movement->stok_sesudah}", $movement,
                    ['stok' => $movement->stok_sebelum, 'lokasi' => $pool], ['stok' => $movement->stok_sesudah, 'lokasi' => $pool, 'keterangan' => $ket], $branchId);
            }
        } catch (StockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('inventory.index', ['branch_id' => $user->isOwner() ? $branchId : null])
            ->with('success', 'Stok berhasil diperbarui.');
    }

    public function clearBasah(Request $request, \App\Services\StockBatchService $batchService)
    {
        $user = $request->user();
        $isOwner = $user->isOwner();
        $branchId = $isOwner ? $request->input('branch_id') : $user->branch_id;
        $productId = $request->input('product_id');

        try {
            if ($productId) {
                if (! $branchId) {
                    return back()->with('error', 'Cabang tidak valid.');
                }
                $clearedQty = $batchService->clearBasahStock($branchId, $productId, $user->id);
                if ($clearedQty > 0) {
                    return back()->with('success', "Sisa stok kue basah berhasil dikosongkan ({$clearedQty} pcs).");
                }
                return back()->with('info', 'Stok kue basah sudah kosong.');
            }

            // Kosongkan seluruh sisa kue basah
            $branchesToClear = $branchId ? Branch::where('id_cabang', $branchId)->get() : Branch::all();
            $total = 0;
            foreach ($branchesToClear as $b) {
                $total += $batchService->clearAllBasahInBranch($b->id, $user->id);
            }

            if ($total > 0) {
                return back()->with('success', "Seluruh sisa stok kue basah berhasil dikosongkan ({$total} pcs).");
            }

            return back()->with('info', 'Tidak ada sisa stok kue basah yang perlu dikosongkan.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
