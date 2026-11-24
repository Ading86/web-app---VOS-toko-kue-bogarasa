<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Http\Requests\DistributionRequest;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockDistribution;
use App\Models\StockDistributionDetail;
use App\Services\DistributionService;
use Illuminate\Http\Request;

class DistributionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isOwner = $user->isOwner();
        $branchId = $user->branch_id;
        $isPrivileged = $isOwner || ($user->can('distributions.create') && $user->branch?->is_pusat);

        $distributions = StockDistribution::query()->visibleTo($user)
            ->with(['sourceBranch:id_cabang,kode_cabang,nama_cabang', 'creator:id_user,name'])
            ->withCount([
                'details' => fn ($q) => $q->when(! $isPrivileged && $branchId, fn ($sub) => $sub->where('branch_tujuan_id', $branchId)),
                'details as pending_count' => fn ($q) => $q->where('status', StockDistributionDetail::DIKIRIM)
                    ->when(! $isPrivileged && $branchId, fn ($sub) => $sub->where('branch_tujuan_id', $branchId)),
            ])
            ->when($request->search, fn ($q, $s) => $q->where('id_distribusi', 'like', "%{$s}%"))
            ->when($request->status, function ($q, $s) use ($isPrivileged, $branchId) {
                if ($isPrivileged || ! $branchId) {
                    $q->where('status', $s);
                } else {
                    if ($s === StockDistribution::DIKIRIM) {
                        $q->whereHas('details', fn ($d) => $d->where('branch_tujuan_id', $branchId)->where('status', StockDistributionDetail::DIKIRIM));
                    } elseif ($s === StockDistribution::SELESAI) {
                        $q->whereDoesntHave('details', fn ($d) => $d->where('branch_tujuan_id', $branchId)->where('status', StockDistributionDetail::DIKIRIM))
                          ->whereHas('details', fn ($d) => $d->where('branch_tujuan_id', $branchId)->where('status', StockDistributionDetail::DITERIMA));
                    } elseif ($s === StockDistribution::DIBATALKAN) {
                        $q->whereDoesntHave('details', fn ($d) => $d->where('branch_tujuan_id', $branchId)->where('status', '!=', StockDistributionDetail::DIBATALKAN));
                    }
                }
            })
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('waktu_kirim', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('waktu_kirim', '<=', $d))
            ->orderByDesc('waktu_kirim')->orderByDesc('id_distribusi')
            ->paginate(20)->withQueryString();

        return view('distributions.index', [
            'distributions' => $distributions,
            'isPrivileged' => $isPrivileged,
        ]);
    }

    public function create(Request $request, \App\Services\StockBatchService $batchService)
    {
        $user = $request->user();
        $pusat = Branch::pusat();

        if (! $pusat || $pusat->status !== 'aktif') {
            return redirect()->route('distributions.index')->with('error', 'Cabang Pusat belum ditetapkan atau tidak aktif.');
        }
        abort_unless($user->isOwner() || $user->branch_id === $pusat->id, 403);

        $batchService->autoExpireStaleBatches($pusat->id);

        $products = Product::where('status', 'aktif')->orderBy('nama_produk')->get(['id_produk', 'nama_produk', 'kode_produk', 'tipe_kue']);
        $rawGudang = BranchStock::withoutGlobalScopes()->where('branch_id', $pusat->id)->pluck('stok_gudang', 'product_id');

        $gudang = [];
        foreach ($products as $p) {
            if ($p->usesBatchStock()) {
                $gudang[$p->id] = $batchService->availableGudangQty($pusat->id, $p->id);
            } else {
                $gudang[$p->id] = (int) ($rawGudang[$p->id] ?? 0);
            }
        }

        return view('distributions.create', [
            'pusat' => $pusat,
            'branches' => Branch::where('status', 'aktif')->orderByDesc('is_pusat')->orderBy('kode_cabang')->get(),
            'products' => $products,
            'gudang' => $gudang,
        ]);
    }

    public function store(DistributionRequest $request, DistributionService $service)
    {
        try {
            $dist = $service->create($request->user(), $request->items(), $request->input('catatan'), $request->input('idempotency_key'));
        } catch (StockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('distributions.show', $dist)->with('success', "Distribusi {$dist->id} dikirim. Stok kini berstatus dalam perjalanan sampai dikonfirmasi cabang tujuan.");
    }

    public function show(Request $request, StockDistribution $distribution)
    {
        $user = $request->user();

        abort_unless(StockDistribution::query()->visibleTo($user)->whereKey($distribution->getKey())->exists(), 404);

        $privileged = $distribution->isPrivilegedFor($user);

        $details = $distribution->details()
            ->with(['product', 'destination:id_cabang,kode_cabang,nama_cabang,is_pusat', 'receiver:id_user,name'])
            ->when(! $privileged, fn ($q) => $q->where('branch_tujuan_id', $user->branch_id))   // cabang tujuan hanya melihat barisnya sendiri
            ->orderBy('branch_tujuan_id')->orderBy('product_id')->get();

        $distribution->load(['sourceBranch', 'creator:id_user,name', 'cancelledBy:id_user,name']);

        return view('distributions.show', [
            'dist' => $distribution,
            'groups' => $details->groupBy('branch_tujuan_id'),
            'privileged' => $privileged,
            'canReceive' => $user->can('distributions.receive'),
            'canCancel' => $user->can('distributions.cancel') && $privileged && $distribution->status === StockDistribution::DIKIRIM,
        ]);
    }

    public function receive(Request $request, StockDistribution $distribution, DistributionService $service)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'string', 'exists:branches,id_cabang'],
            'terima' => ['required', 'array'],
            'terima.*' => ['nullable', 'integer', 'min:0', 'max:' . BranchStock::MAX_STOK],
            'catatan' => ['nullable', 'string', 'max:150'],
        ]);

        abort_unless(StockDistribution::query()->visibleTo($request->user())->whereKey($distribution->getKey())->exists(), 404);

        try {
            $service->receive($distribution, $data['branch_id'], $data['terima'], $data['catatan'] ?? null, $request->user());
        } catch (StockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('distributions.show', $distribution)->with('success', 'Penerimaan dikonfirmasi. Stok masuk ke stok jual cabang tujuan.');
    }

    public function cancel(Request $request, StockDistribution $distribution, DistributionService $service)
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:3', 'max:150']]);

        abort_unless(StockDistribution::query()->visibleTo($request->user())->whereKey($distribution->getKey())->exists(), 404);

        try {
            $service->cancel($distribution, $request->user(), $data['alasan']);
        } catch (StockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('distributions.show', $distribution)->with('success', 'Kiriman yang masih dalam perjalanan dibatalkan dan stok kembali ke gudang pusat.');
    }
}
