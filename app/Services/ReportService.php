<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Transaction;
use App\Support\DateRange;
use Illuminate\Support\Carbon;

class ReportService
{
    public const TYPES = [
        'sales_daily'    => 'Penjualan harian',
        'sales_weekly'   => 'Penjualan mingguan',
        'sales_monthly'  => 'Penjualan bulanan',
        'per_branch'     => 'Laporan per cabang',
        'top_products'   => 'Produk terlaris',
        'stock'          => 'Laporan stok',
        'stock_batch'    => 'Laporan stok batch (kue)',
        'transactions'   => 'Laporan transaksi',
        'activities'     => 'Aktivitas pengguna',
    ];

    private const CAP = 20000;

    public function __construct(private SalesStats $stats)
    {
    }

    /**
     * @return array{title:string, headings:string[], formats:string[], rows:array, summary:array<string,string>}
     */
    public function build(string $type, DateRange $range, ?string $branchId): array
    {
        return match ($type) {
            'sales_weekly'  => $this->salesRollup('Penjualan mingguan', 'Minggu mulai', 'week', $range, $branchId),
            'sales_monthly' => $this->salesRollup('Penjualan bulanan', 'Bulan', 'month', $range, $branchId),
            'per_branch'    => $this->perBranch($range, $branchId),
            'top_products'  => $this->topProducts($range, $branchId),
            'stock'         => $this->stock($branchId),
            'stock_batch'   => $this->stockBatch($range, $branchId),
            'transactions'  => $this->transactions($range, $branchId),
            'activities'    => $this->activities($range, $branchId),
            default         => $this->salesDaily($range, $branchId),
        };
    }

    private function salesDaily(DateRange $r, ?string $b): array
    {
        $rows = [];
        foreach ($this->stats->daily($b, $r->start, $r->end) as $date => $v) {
            $rows[] = [Carbon::parse($date)->format('d/m/Y'), $v['trx'], $v['qty'], $v['omzet']];
        }

        return [
            'title'    => 'Laporan penjualan harian',
            'headings' => ['Tanggal', 'Jumlah Transaksi', 'Item Terjual', 'Omzet'],
            'formats'  => ['text', 'int', 'int', 'money'],
            'rows'     => $rows,
            'summary'  => [
                'Total transaksi' => (string) array_sum(array_column($rows, 1)),
                'Total item'      => (string) array_sum(array_column($rows, 2)),
                'Total omzet'     => 'Rp ' . number_format(array_sum(array_column($rows, 3)), 0, ',', '.'),
            ],
        ];
    }

    private function salesRollup(string $title, string $colName, string $unit, DateRange $r, ?string $b): array
    {
        $rows = [];
        $totalTrx = 0;
        $totalQty = 0;
        $totalOmzet = 0.0;

        foreach ($this->stats->rollup($unit, $b, $r->start, $r->end) as $k => $v) {
            $label = $unit === 'week' ? Carbon::parse($k)->format('d/m/Y') : Carbon::parse($k . '-01')->format('F Y');
            $rows[] = [$label, $v['trx'], $v['qty'], $v['omzet']];
            $totalTrx += $v['trx'];
            $totalQty += $v['qty'];
            $totalOmzet += $v['omzet'];
        }

        return [
            'title'    => "Laporan {$title}",
            'headings' => [$colName, 'Jumlah Transaksi', 'Item Terjual', 'Omzet'],
            'formats'  => ['text', 'int', 'int', 'money'],
            'rows'     => $rows,
            'summary'  => [
                'Total transaksi' => (string) $totalTrx,
                'Total item'      => (string) $totalQty,
                'Total omzet'     => 'Rp ' . number_format($totalOmzet, 0, ',', '.'),
            ],
        ];
    }

    private function perBranch(DateRange $r, ?string $b): array
    {
        $rows = [];
        $total = 0.0;

        foreach ($this->stats->perBranch($b, $r->start, $r->end) as $v) {
            $rows[] = [$v['kode'], $v['nama'], $v['trx'], $v['qty'], $v['omzet']];
            $total += $v['omzet'];
        }

        return [
            'title'    => 'Laporan penjualan per cabang',
            'headings' => ['Kode Cabang', 'Nama Cabang', 'Transaksi', 'Item Terjual', 'Omzet'],
            'formats'  => ['text', 'text', 'int', 'int', 'money'],
            'rows'     => $rows,
            'summary'  => ['Total omzet' => 'Rp ' . number_format($total, 0, ',', '.')],
        ];
    }

    private function topProducts(DateRange $r, ?string $b): array
    {
        $rows = array_map(fn ($x, $i) => [$i + 1, $x['nama'], $x['qty'], $x['omzet']],
            $top = $this->stats->topProducts($b, $r->start, $r->end, 100), array_keys($top));

        return [
            'title'    => 'Produk terlaris',
            'headings' => ['#', 'Produk', 'Jumlah Terjual', 'Omzet'],
            'formats'  => ['int', 'text', 'int', 'money'],
            'rows'     => $rows,
            'summary'  => [],
        ];
    }

    private function stock(?string $b): array
    {
        $rows = BranchStock::query()
            ->join('products', 'products.id_produk', '=', 'branch_stocks.product_id')->whereNull('products.deleted_at')
            ->join('branches', 'branches.id_cabang', '=', 'branch_stocks.branch_id')
            ->leftJoin('categories', 'categories.id_kategori', '=', 'products.category_id')
            ->when($b, fn ($q, $v) => $q->where('branch_stocks.branch_id', $v))
            ->orderBy('branches.kode_cabang')->orderBy('products.nama_produk')->limit(self::CAP)
            ->get(['branches.nama_cabang as cabang', 'products.kode_produk as kode', 'products.nama_produk as produk',
                'categories.nama_kategori as kategori', 'branch_stocks.jumlah_stok as stok',
                'branch_stocks.stok_minimum as minimum', 'products.tipe_kue as tipe'])
            ->map(fn ($x) => [
                $x->cabang, $x->kode, $x->produk, $x->kategori,
                ucfirst($x->tipe ?? 'standard'),
                (int) $x->stok, (int) $x->minimum,
                (int) $x->stok === 0 ? 'Habis' : ((int) $x->stok <= (int) $x->minimum ? 'Menipis' : 'Aman'),
            ])->all();

        return [
            'title'    => 'Laporan stok (kondisi saat ini)',
            'headings' => ['Cabang', 'Kode Produk', 'Produk', 'Kategori', 'Tipe', 'Stok', 'Stok Minimum', 'Status'],
            'formats'  => ['text', 'text', 'text', 'text', 'text', 'int', 'int', 'text'],
            'rows'     => $rows,
            'summary'  => ['Total item' => (string) count($rows)],
        ];
    }

    /** Laporan pergerakan batch (stok masuk, terjual, kedaluwarsa) per produk per tanggal. */
    private function stockBatch(DateRange $r, ?string $b): array
    {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();

        $rows = StockBatch::withoutGlobalScopes()
            ->join('products', 'products.id_produk', '=', 'stock_batches.product_id')
            ->join('branches', 'branches.id_cabang', '=', 'stock_batches.branch_id')
            ->leftJoin('categories', 'categories.id_kategori', '=', 'products.category_id')
            ->whereBetween('stock_batches.tanggal_produksi', [$r->start->toDateString(), $r->end->toDateString()])
            ->when($b, fn ($q, $v) => $q->where('stock_batches.branch_id', $v))
            ->orderBy('stock_batches.tanggal_produksi')->orderBy('branches.kode_cabang')->limit(self::CAP)
            ->get([
                'branches.nama_cabang as cabang',
                'products.nama_produk as produk',
                'products.tipe_kue as tipe',
                'categories.nama_kategori as kategori',
                'stock_batches.tanggal_produksi',
                'stock_batches.tanggal_kedaluwarsa',
                'stock_batches.jumlah_masuk',
                'stock_batches.jumlah_terjual',
                'stock_batches.jumlah_kadaluwarsa',
                'stock_batches.status',
            ])
            ->map(function ($x) use ($today) {
                $isExpired = Carbon::parse($x->tanggal_kedaluwarsa)->lt($today) || $x->status === 'kedaluwarsa';
                $sisaAktif = $isExpired ? 0 : max(0, (int) $x->jumlah_masuk - (int) $x->jumlah_terjual);
                $expiredQty = $isExpired ? max((int) $x->jumlah_kadaluwarsa, (int) $x->jumlah_masuk - (int) $x->jumlah_terjual) : (int) $x->jumlah_kadaluwarsa;

                return [
                    $x->cabang,
                    $x->produk,
                    $x->kategori,
                    ucfirst($x->tipe),
                    Carbon::parse($x->tanggal_produksi)->format('d/m/Y'),
                    Carbon::parse($x->tanggal_kedaluwarsa)->format('d/m/Y'),
                    (int) $x->jumlah_masuk,
                    (int) $x->jumlah_terjual,
                    $expiredQty,
                    $sisaAktif,
                    $isExpired ? 'Kedaluwarsa' : ($sisaAktif <= 0 ? 'Habis' : 'Aktif'),
                ];
            })->all();

        return [
            'title'    => 'Laporan stok batch kue',
            'headings' => ['Cabang', 'Produk', 'Kategori', 'Tipe', 'Tgl Produksi', 'Kedaluwarsa',
                           'Masuk', 'Terjual', 'Kedaluwarsa', 'Sisa Layak Jual', 'Status'],
            'formats'  => ['text', 'text', 'text', 'text', 'text', 'text', 'int', 'int', 'int', 'int', 'text'],
            'rows'     => $rows,
            'summary'  => [
                'Total batch'       => (string) count($rows),
                'Total masuk'       => number_format(array_sum(array_column($rows, 6)), 0, ',', '.'),
                'Total terjual'     => number_format(array_sum(array_column($rows, 7)), 0, ',', '.'),
                'Total kedaluwarsa' => number_format(array_sum(array_column($rows, 8)), 0, ',', '.'),
            ],
        ];
    }

    private function transactions(DateRange $r, ?string $b): array
    {
        $list = Transaction::query()->with(['cashier:id_user,name', 'branch:id_cabang,nama_cabang', 'payments:id_pembayaran,transaction_id,metode'])
            ->whereBetween('waktu_transaksi', [$r->start, $r->end])
            ->when($b, fn ($q, $v) => $q->where('branch_id', $v))
            ->orderByDesc('waktu_transaksi')->limit(self::CAP)->get();

        $rows = $list->map(fn ($t) => [
            $t->kode_transaksi, $t->waktu_transaksi->format('d/m/Y H:i'), $t->branch?->nama_cabang, $t->cashier?->name,
            strtoupper((string) $t->payments->first()?->metode), (float) $t->total, ucfirst($t->status),
        ])->all();

        $valid = $list->where('status', Transaction::STATUS_SELESAI);

        return [
            'title'    => 'Laporan transaksi',
            'headings' => ['Kode', 'Waktu', 'Cabang', 'Kasir', 'Metode', 'Total', 'Status'],
            'formats'  => ['text', 'text', 'text', 'text', 'text', 'money', 'text'],
            'rows'     => $rows,
            'summary'  => [
                'Transaksi selesai'                    => (string) $valid->count(),
                'Omzet (selesai saja)'                 => 'Rp ' . number_format((float) $valid->sum('total'), 0, ',', '.'),
                'Transaksi dibatalkan (tidak dihitung)' => (string) $list->where('status', Transaction::STATUS_DIBATALKAN)->count(),
            ],
        ];
    }

    private function activities(DateRange $r, ?string $b): array
    {
        $rows = ActivityLog::query()->with(['user:id_user,name', 'branch:id_cabang,nama_cabang'])
            ->whereBetween('created_at', [$r->start, $r->end])
            ->when($b, fn ($q, $v) => $q->where('branch_id', $v))
            ->orderByDesc('created_at')->limit(self::CAP)->get()
            ->map(fn ($a) => [$a->created_at?->format('d/m/Y H:i:s'), $a->user?->name ?? '-', $a->branch?->nama_cabang ?? '-', $a->log_name, $a->aktivitas, $a->description])->all();

        return [
            'title'    => 'Laporan aktivitas pengguna',
            'headings' => ['Waktu', 'Pengguna', 'Cabang', 'Modul', 'Aktivitas', 'Deskripsi'],
            'formats'  => ['text', 'text', 'text', 'text', 'text', 'text'],
            'rows'     => $rows,
            'summary'  => ['Jumlah aktivitas' => (string) count($rows)],
        ];
    }
}
