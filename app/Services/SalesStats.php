<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Semua angka penjualan dihitung dari transaksi berstatus 'selesai' saja,
 * sehingga transaksi yang dibatalkan otomatis tidak ikut terhitung.
 * Filter cabang selalu eksplisit (tidak hanya mengandalkan global scope).
 */
class SalesStats
{
    public function base(?string $branchId, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return Transaction::query()
            ->where('status', Transaction::STATUS_SELESAI)
            ->whereBetween('waktu_transaksi', [$start, $end])
            ->when($branchId, fn ($q, $b) => $q->where('branch_id', $b));
    }

    public function detailBase(?string $branchId, CarbonInterface $start, CarbonInterface $end): QueryBuilder
    {
        return DB::table('transaction_details as d')
            ->join('transactions as t', 't.id_transaksi', '=', 'd.transaction_id')
            ->where('t.status', Transaction::STATUS_SELESAI)
            ->whereBetween('t.waktu_transaksi', [$start, $end])
            ->when($branchId, fn ($q, $b) => $q->where('t.branch_id', $b));
    }

    /** @return array{omzet:float,transaksi:int,terjual:int,rata:float} */
    public function kpi(?string $branchId, CarbonInterface $start, CarbonInterface $end): array
    {
        $omzet = (float) $this->base($branchId, $start, $end)->sum('total');
        $count = (int) $this->base($branchId, $start, $end)->count();
        $qty = (int) $this->detailBase($branchId, $start, $end)->sum('d.jumlah');

        return ['omzet' => $omzet, 'transaksi' => $count, 'terjual' => $qty, 'rata' => $count ? round($omzet / $count, 2) : 0.0];
    }

    /** @return array<string, array{omzet:float,trx:int,qty:int}> key Y-m-d, semua hari dalam rentang terisi */
    public function daily(?string $branchId, CarbonInterface $start, CarbonInterface $end): array
    {
        $trx = $this->base($branchId, $start, $end)
            ->selectRaw('DATE(waktu_transaksi) as tgl, COUNT(*) as trx, SUM(total) as omzet')
            ->groupByRaw('DATE(waktu_transaksi)')->get()->keyBy('tgl');

        $qty = $this->detailBase($branchId, $start, $end)
            ->selectRaw('DATE(t.waktu_transaksi) as tgl, SUM(d.jumlah) as qty')
            ->groupByRaw('DATE(t.waktu_transaksi)')->get()->keyBy('tgl');

        $out = [];
        foreach (CarbonPeriod::create(Carbon::instance($start)->startOfDay(), Carbon::instance($end)->startOfDay()) as $day) {
            $k = $day->format('Y-m-d');
            $out[$k] = [
                'omzet' => (float) ($trx[$k]->omzet ?? 0),
                'trx' => (int) ($trx[$k]->trx ?? 0),
                'qty' => (int) ($qty[$k]->qty ?? 0),
            ];
        }

        return $out;
    }

    /** Gulung data harian menjadi mingguan ('week', kunci = tanggal awal minggu) atau bulanan ('month', kunci = Y-m). */
    public function rollup(array $daily, string $by): array
    {
        $out = [];
        foreach ($daily as $date => $v) {
            $d = Carbon::parse($date);
            $k = $by === 'week' ? $d->copy()->startOfWeek()->format('Y-m-d') : $d->format('Y-m');
            $out[$k] ??= ['omzet' => 0.0, 'trx' => 0, 'qty' => 0];
            $out[$k]['omzet'] += $v['omzet'];
            $out[$k]['trx'] += $v['trx'];
            $out[$k]['qty'] += $v['qty'];
        }

        return $out;
    }

    public function weekly(?string $branchId, int $weeks = 12): array
    {
        $start = Carbon::now()->startOfWeek()->subWeeks($weeks - 1);

        return $this->rollup($this->daily($branchId, $start, Carbon::now()->endOfDay()), 'week');
    }

    public function monthly(?string $branchId, int $months = 12): array
    {
        $start = Carbon::now()->startOfMonth()->subMonthsNoOverflow($months - 1);

        return $this->rollup($this->daily($branchId, $start, Carbon::now()->endOfDay()), 'month');
    }

    /** @return array<int, array{product_id:string,nama:string,qty:int,omzet:float}> */
    public function topProducts(?string $branchId, CarbonInterface $start, CarbonInterface $end, int $limit = 8): array
    {
        return $this->detailBase($branchId, $start, $end)
            ->selectRaw('d.product_id, MAX(d.nama_produk) as nama, SUM(d.jumlah) as qty, SUM(d.subtotal) as omzet')
            ->groupBy('d.product_id')->orderByDesc('qty')->limit($limit)->get()
            ->map(fn ($r) => ['product_id' => $r->product_id, 'nama' => $r->nama, 'qty' => (int) $r->qty, 'omzet' => (float) $r->omzet])
            ->all();
    }

    /** Perbandingan performa cabang. */
    public function perBranch(?string $branchId, CarbonInterface $start, CarbonInterface $end): array
    {
        $trx = $this->base($branchId, $start, $end)
            ->selectRaw('branch_id, COUNT(*) as trx, SUM(total) as omzet')->groupBy('branch_id')->get()->keyBy('branch_id');
        $qty = $this->detailBase($branchId, $start, $end)
            ->selectRaw('t.branch_id as branch_id, SUM(d.jumlah) as qty')->groupBy('t.branch_id')->get()->keyBy('branch_id');

        return Branch::query()->when($branchId, fn ($q, $b) => $q->where('id_cabang', $b))->orderBy('kode_cabang')->get()
            ->map(function ($b) use ($trx, $qty) {
                $t = (int) ($trx[$b->id]->trx ?? 0);
                $o = (float) ($trx[$b->id]->omzet ?? 0);

                return [
                    'branch_id' => $b->id, 'kode' => $b->kode_cabang, 'nama' => $b->nama_cabang,
                    'trx' => $t, 'qty' => (int) ($qty[$b->id]->qty ?? 0), 'omzet' => $o, 'rata' => $t ? round($o / $t, 2) : 0.0,
                ];
            })->all();
    }
}
