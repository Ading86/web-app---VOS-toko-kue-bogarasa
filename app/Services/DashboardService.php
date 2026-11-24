<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Support\DateRange;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function __construct(private SalesStats $stats)
    {
    }

    /** Owner: semua cabang / cabang terpilih. Kasir: selalu cabangnya sendiri. */
    public function effectiveBranch(User $user, ?string $requested): ?string
    {
        if (! $user->isOwner()) {
            return $user->branch_id;
        }

        return ($requested && Branch::whereKey($requested)->exists()) ? $requested : null;
    }

    public function build(User $user, DateRange $range, ?string $requestedBranch): array
    {
        $branchId = $this->effectiveBranch($user, $requestedBranch);
        $isOwner = $user->isOwner();

        $kpi = $this->stats->kpi($branchId, $range->start, $range->end);
        $daily = $this->stats->daily($branchId, $range->start, $range->end);
        $weekly = $this->stats->weekly($branchId);
        $monthly = $this->stats->monthly($branchId);
        $top = $this->stats->topProducts($branchId, $range->start, $range->end, 8);
        $perBranch = $isOwner ? $this->stats->perBranch($branchId, $range->start, $range->end) : [];

        $stockAgg = BranchStock::query()
            ->join('products', 'products.id_produk', '=', 'branch_stocks.product_id')->whereNull('products.deleted_at')
            ->when($branchId, fn ($q, $b) => $q->where('branch_stocks.branch_id', $b))
            ->selectRaw('branch_stocks.branch_id as bid, SUM(branch_stocks.jumlah_stok) as total_stok, SUM(CASE WHEN branch_stocks.jumlah_stok <= branch_stocks.stok_minimum THEN 1 ELSE 0 END) as menipis')
            ->groupBy('branch_stocks.branch_id')->get()->keyBy('bid');

        $stok = Branch::query()->when($branchId, fn ($q, $b) => $q->where('id_cabang', $b))->orderBy('kode_cabang')->get()
            ->map(fn ($b) => [
                'kode' => $b->kode_cabang, 'nama' => $b->nama_cabang,
                'total_stok' => (int) ($stockAgg[$b->id]->total_stok ?? 0), 'menipis' => (int) ($stockAgg[$b->id]->menipis ?? 0),
            ])->all();

        $lowItems = BranchStock::query()
            ->join('products', 'products.id_produk', '=', 'branch_stocks.product_id')->whereNull('products.deleted_at')
            ->join('branches', 'branches.id_cabang', '=', 'branch_stocks.branch_id')
            ->when($branchId, fn ($q, $b) => $q->where('branch_stocks.branch_id', $b))
            ->whereColumn('branch_stocks.jumlah_stok', '<=', 'branch_stocks.stok_minimum')
            ->orderBy('branch_stocks.jumlah_stok')->limit(8)
            ->get(['products.nama_produk as produk', 'branches.nama_cabang as cabang', 'branch_stocks.jumlah_stok as stok', 'branch_stocks.stok_minimum as minimum'])
            ->map(fn ($r) => ['produk' => $r->produk, 'cabang' => $r->cabang, 'stok' => (int) $r->stok, 'minimum' => (int) $r->minimum])->all();

        $canViewTrx = $user->can('transactions.view');
        $recent = Transaction::query()->with(['cashier:id_user,name', 'branch:id_cabang,nama_cabang'])
            ->when($branchId, fn ($q, $b) => $q->where('branch_id', $b))
            ->orderByDesc('waktu_transaksi')->limit(8)->get()
            ->map(fn ($t) => [
                'kode' => $t->kode_transaksi, 'waktu' => $t->waktu_transaksi->format('d/m H:i'),
                'cabang' => $t->branch?->nama_cabang, 'kasir' => $t->cashier?->name, 'total' => (float) $t->total, 'status' => $t->status,
                'url' => $canViewTrx ? route('transactions.show', $t) : null,
            ])->all();

        $activities = ActivityLog::query()->with(['user:id_user,name', 'branch:id_cabang,nama_cabang'])
            ->when(! $isOwner, fn ($q) => $q->where('user_id', $user->id))          // kasir: hanya aktivitasnya sendiri
            ->when($isOwner && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('created_at')->orderByDesc('id_log')->limit(8)->get()
            ->map(fn ($a) => [
                'waktu' => $a->created_at?->format('d/m H:i'), 'user' => $a->user?->name ?? '-', 'cabang' => $a->branch?->nama_cabang ?? '-',
                'aktivitas' => $a->aktivitas, 'deskripsi' => $a->description,
            ])->all();

        $labelDaily = fn (string $d) => Carbon::parse($d)->format('d/m');

        return [
            'label' => $range->label(),
            'version' => $this->version($user, $requestedBranch),
            'generated_at' => now()->format('H:i:s'),
            'kpi' => $kpi + [
                'produk_aktif' => Product::where('status', 'aktif')->count(),
                'stok_menipis' => array_sum(array_column($stok, 'menipis')),
            ],
            'stok' => $stok,
            'low_items' => $lowItems,
            'top' => $top,
            'recent' => $recent,
            'branches' => $perBranch,
            'activities' => $activities,
            'charts' => [
                'daily' => ['labels' => array_map($labelDaily, array_keys($daily)), 'omzet' => array_column($daily, 'omzet'), 'trx' => array_column($daily, 'trx')],
                'weekly' => ['labels' => array_map(fn ($d) => 'Mgg ' . Carbon::parse($d)->format('d/m'), array_keys($weekly)), 'omzet' => array_column($weekly, 'omzet'), 'trx' => array_column($weekly, 'trx')],
                'monthly' => ['labels' => array_map(fn ($m) => Carbon::parse($m . '-01')->format('M Y'), array_keys($monthly)), 'omzet' => array_column($monthly, 'omzet'), 'trx' => array_column($monthly, 'trx')],
                'branches' => ['labels' => array_column($perBranch, 'nama'), 'omzet' => array_column($perBranch, 'omzet'), 'trx' => array_column($perBranch, 'trx')],
                'top' => ['labels' => array_column($top, 'nama'), 'qty' => array_column($top, 'qty')],
            ],
        ];
    }

    /** Penanda perubahan data (untuk polling ringan): berubah saat ada transaksi/pembatalan/stok/aktivitas baru. */
    public function version(User $user, ?string $requestedBranch): string
    {
        $branchId = $this->effectiveBranch($user, $requestedBranch);
        $isOwner = $user->isOwner();

        $parts = [
            Transaction::query()->when($branchId, fn ($q, $b) => $q->where('branch_id', $b))->max('updated_at'),
            StockMovement::query()->when($branchId, fn ($q, $b) => $q->where('branch_id', $b))->max('created_at'),
            ActivityLog::query()->when(! $isOwner, fn ($q) => $q->where('user_id', $user->id))
                ->when($isOwner && $branchId, fn ($q) => $q->where('branch_id', $branchId))->max('created_at'),
        ];

        return md5(implode('|', array_map(fn ($p) => (string) $p, $parts)));
    }
}
