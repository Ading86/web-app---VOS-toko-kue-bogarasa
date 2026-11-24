<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Http\Requests\StockBatchRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockBatch;
use App\Services\ActivityLogService;
use App\Services\StockBatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StockBatchController extends Controller
{
    // ── Daftar batch ──────────────────────────────────────────────────────

    public function index(Request $request, StockBatchService $batchService)
    {
        $user     = $request->user();
        $isOwner  = $user->isOwner();
        $branchId = $isOwner ? $request->input('branch_id') : $user->branch_id;

        // Otomatis tandai batch yang sudah melewati masa layak jual sebagai kedaluwarsa
        $batchService->autoExpireStaleBatches($branchId, $user->id);

        $batches = StockBatch::query()
            ->with(['product.category', 'branch', 'user:id_user,name'])
            ->when($branchId, fn ($q, $b) => $q->where('branch_id', $b))
            ->when($request->product_id, fn ($q, $p) => $q->where('product_id', $p))
            ->when($request->tipe, fn ($q, $t) => $q->whereHas('product', fn ($p) => $p->where('tipe_kue', $t)))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('tanggal_produksi', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('tanggal_produksi', '<=', $d))
            ->orderByDesc('tanggal_produksi')->orderByDesc('id_batch')
            ->paginate(25)->withQueryString();

        return view('stock-batches.index', [
            'batches'  => $batches,
            'branches' => $isOwner ? Branch::orderBy('nama_cabang')->get() : collect(),
            'products' => Product::where('status', 'aktif')
                ->whereIn('tipe_kue', [Product::TIPE_BASAH, Product::TIPE_KERING])
                ->orderBy('nama_produk')->get(['id_produk', 'nama_produk', 'tipe_kue']),
            'tipeOptions' => [
                Product::TIPE_BASAH  => Product::TIPE_OPTIONS[Product::TIPE_BASAH],
                Product::TIPE_KERING => Product::TIPE_OPTIONS[Product::TIPE_KERING],
            ],
        ]);
    }

    // ── Form tambah batch ──────────────────────────────────────────────────

    public function create(Request $request)
    {
        $user = $request->user();

        return view('stock-batches.create', [
            'branches' => $user->isOwner()
                ? Branch::where('status', 'aktif')->orderBy('nama_cabang')->get()
                : collect(),
            'products' => Product::where('status', 'aktif')
                ->whereIn('tipe_kue', [Product::TIPE_BASAH, Product::TIPE_KERING])
                ->orderBy('nama_produk')->get(['id_produk', 'nama_produk', 'kode_produk', 'tipe_kue', 'masa_layak_jual']),
            'preBranch'  => $request->input('branch_id'),
            'preProduct' => $request->input('product_id'),
            'today'      => Carbon::now('Asia/Jakarta')->toDateString(),
        ]);
    }

    // ── Simpan batch ───────────────────────────────────────────────────────

    public function store(StockBatchRequest $request, StockBatchService $batchService, ActivityLogService $audit)
    {
        $user     = $request->user();
        $branchId = $user->isOwner() ? $request->input('branch_id') : $user->branch_id;

        if (! $branchId) {
            return back()->withInput()->withErrors(['branch_id' => 'Pilih cabang terlebih dahulu.']);
        }

        try {
            $batch = $batchService->receiveBatch(
                branchId:        $branchId,
                productId:       $request->input('product_id'),
                jumlah:          (int) $request->input('jumlah'),
                tanggalProduksi: Carbon::parse($request->input('tanggal_produksi'), 'Asia/Jakarta'),
                userId:          $user->id,
                keterangan:      $request->input('keterangan'),
            );
        } catch (StockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('stock-batches.index', ['branch_id' => $user->isOwner() ? $branchId : null])
            ->with('success', "Batch stok {$batch->product->nama_produk} berhasil dicatat ({$batch->jumlah_masuk} pcs, kedaluwarsa {$batch->tanggal_kedaluwarsa->format('d/m/Y')}).");
    }
}
