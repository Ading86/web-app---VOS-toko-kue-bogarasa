<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Http\Requests\StockRequestFormRequest;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\StockRequestDetail;
use App\Services\StockRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StockRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $requests = StockRequest::query()->visibleTo($user)
            ->with([
                'branch:id_cabang,kode_cabang,nama_cabang',
                'requester:id_user,name',
                'approver:id_user,name',
                'processor:id_user,name',
            ])
            ->withCount('details')
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($w) => $w->where('id_permintaan', 'like', "%{$s}%")
                    ->orWhere('catatan_cabang', 'like', "%{$s}%")
                    ->orWhere('catatan_owner', 'like', "%{$s}%"));
            })
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->branch_id && ($user->isOwner() || $user->isWarehouse()), fn ($q, $b) => $q->where('branch_id', $b))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('tanggal_permintaan', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('tanggal_permintaan', '<=', $d))
            ->orderByDesc('created_at')
            ->orderByDesc('id_permintaan')
            ->paginate(15)
            ->withQueryString();

        $branches = ($user->isOwner() || $user->isWarehouse())
            ? Branch::where('status', 'aktif')->orderBy('nama_cabang')->get(['id_cabang', 'nama_cabang', 'is_pusat'])
            : collect();

        return view('stock-requests.index', [
            'requests' => $requests,
            'branches' => $branches,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        abort_unless($user->can('stock_requests.create'), 403, 'Anda tidak memiliki hak akses untuk mengajukan permintaan stok.');

        $isOwner = $user->isOwner();
        $branchId = $isOwner ? $request->query('branch_id', $user->branch_id) : $user->branch_id;

        if (! $branchId) {
            $firstBranch = Branch::where('status', 'aktif')->where('is_pusat', false)->first();
            $branchId = $firstBranch?->id_cabang;
        }

        $branch = Branch::whereKey($branchId)->first();
        if (! $branch) {
            return redirect()->route('stock-requests.index')->with('error', 'Cabang pemohon tidak ditemukan.');
        }

        $products = Product::where('status', 'aktif')
            ->with('category:id_kategori,nama_kategori')
            ->orderBy('nama_produk')
            ->get(['id_produk', 'category_id', 'nama_produk', 'kode_produk', 'tipe_kue', 'harga']);

        // Ambil stok siap jual cabang saat ini
        $branchStocks = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->pluck('jumlah_stok', 'product_id');

        $branches = $isOwner
            ? Branch::where('status', 'aktif')->orderBy('nama_cabang')->get(['id_cabang', 'nama_cabang', 'is_pusat'])
            : collect([$branch]);

        return view('stock-requests.create', [
            'branch'       => $branch,
            'products'     => $products,
            'branchStocks' => $branchStocks,
            'branches'     => $branches,
            'isOwner'      => $isOwner,
        ]);
    }

    public function store(StockRequestFormRequest $request, StockRequestService $service)
    {
        try {
            $stockRequest = $service->create(
                user:            $request->user(),
                items:           $request->input('items', []),
                catatan:         $request->input('catatan'),
                targetBranchId:  $request->input('branch_id'),
            );
        } catch (StockException|ValidationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-requests.show', $stockRequest)
            ->with('success', "Permintaan stok {$stockRequest->id} berhasil diajukan dan sedang menunggu persetujuan Owner.");
    }

    public function show(Request $request, StockRequest $stockRequest)
    {
        $user = $request->user();

        // Keamanan akses
        abort_unless(
            $user->isOwner() || $user->isWarehouse() || ($user->branch_id && $user->branch_id === $stockRequest->branch_id),
            403,
            'Anda tidak memiliki akses ke permintaan stok ini.'
        );

        $stockRequest->load([
            'branch:id_cabang,kode_cabang,nama_cabang,is_pusat',
            'requester:id_user,name',
            'approver:id_user,name',
            'processor:id_user,name',
            'distribution.sourceBranch',
            'details.product.category',
        ]);

        $pusat = Branch::pusat();
        // Stok Gudang Pusat saat ini untuk referensi Owner & Warehouse
        $gudangStocks = $pusat
            ? BranchStock::withoutGlobalScopes()->where('branch_id', $pusat->id)->pluck('stok_gudang', 'product_id')
            : collect();

        // Stok Cabang saat ini untuk referensi
        $cabangStocks = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $stockRequest->branch_id)
            ->pluck('jumlah_stok', 'product_id');

        return view('stock-requests.show', [
            'request'      => $stockRequest,
            'gudangStocks' => $gudangStocks,
            'cabangStocks' => $cabangStocks,
            'pusat'        => $pusat,
        ]);
    }

    public function approve(Request $request, StockRequest $stockRequest, StockRequestService $service)
    {
        $user = $request->user();
        abort_unless($user->can('stock_requests.approve') || $user->isOwner(), 403, 'Hanya Owner yang dapat menyetujui permintaan stok.');

        $request->validate([
            'approved_qtys'   => ['required', 'array'],
            'approved_qtys.*' => ['required', 'integer', 'min:0', 'max:' . BranchStock::MAX_STOK],
            'catatan_owner'   => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $service->approve(
                request:       $stockRequest,
                owner:         $user,
                approvedQtys:  $request->input('approved_qtys', []),
                catatan:       $request->input('catatan_owner'),
            );
        } catch (StockException|ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-requests.show', $stockRequest)
            ->with('success', "Permintaan stok {$stockRequest->id} berhasil disetujui dan siap diproses kirim oleh Warehouse.");
    }

    public function reject(Request $request, StockRequest $stockRequest, StockRequestService $service)
    {
        $user = $request->user();
        abort_unless($user->can('stock_requests.approve') || $user->isOwner(), 403, 'Hanya Owner yang dapat menolak permintaan stok.');

        $request->validate([
            'alasan_penolakan' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'alasan_penolakan.required' => 'Alasan penolakan wajib diisi.',
            'alasan_penolakan.min'      => 'Alasan penolakan minimal 3 karakter.',
        ]);

        try {
            $service->reject(
                request: $stockRequest,
                owner:   $user,
                alasan:  $request->input('alasan_penolakan'),
            );
        } catch (StockException|ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-requests.show', $stockRequest)
            ->with('success', "Permintaan stok {$stockRequest->id} telah ditolak.");
    }

    public function dispatch(Request $request, StockRequest $stockRequest, StockRequestService $service)
    {
        $user = $request->user();
        abort_unless($user->can('stock_requests.process') || $user->isOwner(), 403, 'Hanya Owner yang dapat memproses pengiriman.');

        $request->validate([
            'catatan_pengiriman' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $dist = $service->processDispatch(
                request:       $stockRequest,
                warehouseUser: $user,
                catatan:       $request->input('catatan_pengiriman'),
            );
        } catch (StockException|ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-requests.show', $stockRequest)
            ->with('success', "Kue berhasil diproses dan dikirim ke cabang tujuan (No. Distribusi: {$dist->id}). Stok Gudang Pusat telah dipotong.");
    }

    public function receive(Request $request, StockRequest $stockRequest, StockRequestService $service)
    {
        $user = $request->user();
        abort_unless(
            $user->can('stock_requests.receive') || $user->isOwner() || ($user->branch_id && $user->branch_id === $stockRequest->branch_id),
            403,
            'Anda tidak memiliki akses untuk mengonfirmasi penerimaan stok cabang ini.'
        );

        $request->validate([
            'received_qtys'   => ['required', 'array'],
            'catatan_selisih' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $service->receive(
                request:      $stockRequest,
                user:         $user,
                receivedQtys: $request->input('received_qtys', []),
                catatan:      $request->input('catatan_selisih'),
            );
        } catch (StockException|ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-requests.show', $stockRequest)
            ->with('success', "Penerimaan kue untuk permintaan {$stockRequest->id} berhasil dikonfirmasi. Stok etalase telah diperbarui.");
    }
}
