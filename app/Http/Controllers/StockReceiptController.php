<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Http\Requests\StockReceiptRequest;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Services\StockReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StockReceiptController extends Controller
{
    // ── Daftar Riwayat Penerimaan ──────────────────────────────────────────

    public function index(Request $request)
    {
        $user  = $request->user();
        $pusat = Branch::pusat();

        abort_unless($user->isOwner() || ($pusat && $user->branch_id === $pusat->id), 403, 'Akses penerimaan stok Gudang Pusat hanya untuk akun Cabang Pusat atau Owner.');

        $receipts = StockReceipt::query()
            ->with(['user:id_user,name', 'branch:id_cabang,nama_cabang'])
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($w) => $w->where('nomor_penerimaan', 'like', "%{$s}%")
                                       ->orWhere('catatan', 'like', "%{$s}%"));
            })
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('tanggal_penerimaan', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('tanggal_penerimaan', '<=', $d))
            ->orderByDesc('tanggal_penerimaan')
            ->orderByDesc('id_penerimaan')
            ->paginate(20)
            ->withQueryString();

        return view('stock-receipts.index', compact('receipts'));
    }

    // ── Form Input Penerimaan Baru ─────────────────────────────────────────

    public function create(Request $request, StockReceiptService $receiptService)
    {
        $user  = $request->user();
        $pusat = Branch::pusat();

        if (! $pusat || $pusat->status !== 'aktif') {
            return redirect()->route('dashboard')->with('error', 'Cabang Pusat belum ditetapkan atau tidak aktif.');
        }

        abort_unless($user->isOwner() || $user->branch_id === $pusat->id, 403, 'Penerimaan stok Gudang Pusat hanya untuk akun Cabang Pusat atau Owner.');

        $today = Carbon::now('Asia/Jakarta');

        // Ambil produk aktif
        $products = Product::where('status', 'aktif')
            ->with('category:id_kategori,nama_kategori')
            ->orderBy('nama_produk')
            ->get(['id_produk', 'category_id', 'nama_produk', 'kode_produk', 'tipe_kue', 'masa_layak_jual']);

        // Stok gudang pusat saat ini
        $gudangStocks = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $pusat->id)
            ->pluck('stok_gudang', 'product_id');

        $previewNomor = $receiptService->generateReceiptNumber($today);

        return view('stock-receipts.create', [
            'pusat'           => $pusat,
            'products'        => $products,
            'gudangStocks'    => $gudangStocks,
            'today'           => $today->toDateString(),
            'defaultTanggal'  => $today->toDateString(),
            'nomorPenerimaan' => $previewNomor,
            'previewNomor'    => $previewNomor,
        ]);
    }

    // ── Simpan Penerimaan ──────────────────────────────────────────────────

    public function store(StockReceiptRequest $request, StockReceiptService $service)
    {
        try {
            $tanggal = Carbon::parse($request->input('tanggal_penerimaan'), 'Asia/Jakarta');
            $receipt = $service->receive(
                user:              $request->user(),
                tanggalPenerimaan: $tanggal,
                catatan:           $request->input('catatan'),
                items:             $request->input('items', []),
            );
        } catch (StockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('stock-receipts.show', $receipt->id)
            ->with('success', "Penerimaan stok {$receipt->nomor_penerimaan} berhasil disimpan. Stok Gudang Pusat telah bertambah ({$receipt->total_kuantitas} pcs).");
    }

    // ── Detail Riwayat Penerimaan ──────────────────────────────────────────

    public function show(Request $request, StockReceipt $stockReceipt)
    {
        $user  = $request->user();
        $pusat = Branch::pusat();

        abort_unless($user->isOwner() || ($pusat && $user->branch_id === $pusat->id), 403, 'Akses penerimaan stok Gudang Pusat hanya untuk akun Cabang Pusat atau Owner.');

        $stockReceipt->load([
            'branch',
            'user:id_user,name',
            'details.product.category',
            'details.batch',
        ]);

        return view('stock-receipts.show', [
            'stockReceipt' => $stockReceipt,
            'receipt' => $stockReceipt,
        ]);
    }
}
