<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockDistribution;
use App\Models\StockDistributionDetail;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Distribusi stok dari Cabang Pusat ke cabang (termasuk alokasi untuk penjualan Cabang Pusat sendiri).
 *
 * Alur stok (semua dalam satu database transaction, tiap perubahan lewat StockService):
 *   KIRIM    : gudang pusat  -X      |  perjalanan cabang tujuan  +X
 *   TERIMA   : perjalanan    -X      |  stok jual cabang tujuan   +R   (R = jumlah diterima, 0..X; selisih X-R tercatat, tidak masuk stok siapa pun)
 *   BATAL    : perjalanan    -X      |  gudang pusat              +X   (hanya baris yang belum diterima)
 *
 * Pusat mendapat alokasi sendiri lewat baris dengan cabang tujuan = Cabang Pusat; baris itu diperlakukan sama
 * (dikirim -> dikonfirmasi) sehingga tidak ada jalur khusus yang bisa menyimpang.
 *
 * Urutan penguncian (mencegah deadlock): baris distribusi -> sequence DST/DDT -> sequence SMV -> baris stok.
 */
class DistributionService
{
    public const MAX_LINES = 500;

    public function __construct(
        private CodeGeneratorService $codes,
        private StockService $stock,
        private ActivityLogService $audit,
        private StockBatchService $batchService,
    ) {
    }

    /**
     * @param  array<int, array{product_id:string, branch_id:string, qty:int|string}>  $items
     * @throws \App\Exceptions\StockException
     */
    public function create(User $user, array $items, ?string $catatan, string $idempotencyKey): StockDistribution
    {
        $pusat = Branch::pusat();

        if (! $pusat || $pusat->status !== 'aktif') {
            throw ValidationException::withMessages(['branch' => 'Cabang Pusat belum ditetapkan atau tidak aktif.']);
        }

        $this->assertPusatStaff($user, $pusat->id);

        // Submit ulang (double click / retry): kembalikan distribusi yang sama.
        if ($existing = $this->findByKey($idempotencyKey, $user)) {
            return $existing;
        }

        try {
            return DB::transaction(fn () => $this->doCreate($user, $pusat, $items, $catatan, $idempotencyKey), 3);
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23000', 23000], true) && ($existing = $this->findByKey($idempotencyKey, $user))) {
                return $existing;
            }
            throw $e;
        }
    }

    private function doCreate(User $user, Branch $pusat, array $items, ?string $catatan, string $key): StockDistribution
    {
        // 1) Normalisasi: gabungkan baris (produk, cabang) yang sama; urutkan agar urutan penguncian selalu sama.
        $lines = [];
        foreach ($items as $item) {
            $pid = (string) ($item['product_id'] ?? '');
            $bid = (string) ($item['branch_id'] ?? '');
            $qty = (int) ($item['qty'] ?? 0);

            if ($pid === '' || $bid === '' || $qty < 1) {
                continue;
            }

            $k = "{$pid}|{$bid}";
            $lines[$k] = ['product_id' => $pid, 'branch_id' => $bid, 'qty' => ($lines[$k]['qty'] ?? 0) + $qty];
        }
        ksort($lines);

        if (! $lines) {
            throw ValidationException::withMessages(['items' => 'Isi minimal satu jumlah kirim untuk satu produk dan satu cabang.']);
        }
        if (count($lines) > self::MAX_LINES) {
            throw ValidationException::withMessages(['items' => 'Maksimal ' . self::MAX_LINES . ' baris per distribusi. Pecah menjadi beberapa distribusi.']);
        }

        // 2) Produk harus aktif; cabang tujuan harus ada dan aktif.
        $productIds = collect($lines)->pluck('product_id')->unique()->values();
        $branchIds = collect($lines)->pluck('branch_id')->unique()->values();

        $products = Product::whereIn('id_produk', $productIds)->where('status', 'aktif')->get()->keyBy('id_produk');
        if ($products->count() !== $productIds->count()) {
            throw ValidationException::withMessages(['items' => 'Ada produk yang tidak ditemukan atau sudah nonaktif.']);
        }
        $branches = Branch::whereIn('id_cabang', $branchIds)->where('status', 'aktif')->get()->keyBy('id_cabang');
        if ($branches->count() !== $branchIds->count()) {
            throw ValidationException::withMessages(['items' => 'Ada cabang tujuan yang tidak ditemukan atau tidak aktif.']);
        }

        // 3) Hitung total per produk
        $totals = [];
        foreach ($lines as $l) {
            $totals[$l['product_id']] = ($totals[$l['product_id']] ?? 0) + $l['qty'];
        }
        ksort($totals);

        // 4) Jalankan pembersihan batch kedaluwarsa di pusat & potong batch gudang FIFO
        $this->batchService->autoExpireStaleBatches($pusat->id, $user->id);
        $batchInfos = [];
        foreach ($totals as $pid => $qty) {
            $prod = $products[$pid];
            if ($prod->usesBatchStock()) {
                $batchInfos[$pid] = $this->batchService->deductGudangForDistribution($pusat->id, $pid, $qty);
            }
        }

        // 5) Header + baris.
        $dist = new StockDistribution([
            'branch_asal_id' => $pusat->id, 'user_id' => $user->id, 'idempotency_key' => $key,
            'status' => StockDistribution::DIKIRIM, 'waktu_kirim' => now(),
            'catatan' => $catatan !== null && trim($catatan) !== '' ? mb_substr(trim($catatan), 0, 150) : null,
        ]);
        $dist->id = $this->codes->forTable('stock_distributions');
        $dist->save();

        foreach ($lines as $l) {
            $info = $batchInfos[$l['product_id']] ?? null;
            StockDistributionDetail::create([
                'distribution_id'     => $dist->id,
                'product_id'          => $l['product_id'],
                'branch_tujuan_id'    => $l['branch_id'],
                'jumlah_dikirim'      => $l['qty'],
                'tanggal_produksi'    => $info['tanggal_produksi'] ?? null,
                'tanggal_kedaluwarsa' => $info['tanggal_kedaluwarsa'] ?? null,
                'status'              => StockDistributionDetail::DIKIRIM,
            ]);
        }

        // 6) Stok: gudang pusat berkurang (satu pergerakan per produk), lalu "dalam perjalanan" cabang tujuan bertambah.
        foreach ($totals as $pid => $qty) {
            $this->stock->changeTrusted($pusat->id, $pid, -$qty, StockMovement::DIST_KELUAR, $user->id,
                "Distribusi {$dist->id}", 'stock_distributions', $dist->id, BranchStock::POOL_GUDANG);
        }
        foreach ($lines as $l) {
            $this->stock->changeTrusted($l['branch_id'], $l['product_id'], $l['qty'], StockMovement::DIST_JALAN, $user->id,
                "Dalam perjalanan {$dist->id}", 'stock_distributions', $dist->id, BranchStock::POOL_PERJALANAN);
        }

        $this->audit->log('distribution', 'created',
            "Membuat distribusi {$dist->id}: " . count($lines) . ' baris ke ' . $branchIds->count() . ' cabang',
            $dist, null,
            ['status' => $dist->status, 'jumlah_baris' => count($lines), 'total_unit' => array_sum($totals), 'catatan' => $dist->catatan],
            $pusat->id, $user);

        return $dist;
    }

    /**
     * Konfirmasi penerimaan SEMUA baris yang masih dalam perjalanan untuk satu cabang tujuan.
     *
     * @param  array<string, int|string>  $received  [detail_id => jumlah diterima]
     * @throws \App\Exceptions\StockException
     */
    public function receive(StockDistribution $distribution, string $branchId, array $received, ?string $catatan, User $user): StockDistribution
    {
        if (! $user->isOwner() && $user->branch_id !== $branchId) {
            throw new AuthorizationException('Anda hanya dapat mengonfirmasi kiriman untuk cabang Anda sendiri.');
        }

        return DB::transaction(function () use ($distribution, $branchId, $received, $catatan, $user) {
            $d = StockDistribution::whereKey($distribution->getKey())->lockForUpdate()->firstOrFail();

            $lines = StockDistributionDetail::query()
                ->where('distribution_id', $d->id)->where('branch_tujuan_id', $branchId)
                ->where('status', StockDistributionDetail::DIKIRIM)
                ->with('product')->orderBy('product_id')->lockForUpdate()->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['terima' => 'Tidak ada kiriman untuk cabang ini yang menunggu konfirmasi.']);
            }

            $note = trim((string) $catatan);
            $plan = [];

            foreach ($lines as $line) {
                $name = $line->product?->nama_produk ?? $line->product_id;
                $raw = $received[$line->id] ?? null;

                if ($raw === null || ! ctype_digit((string) $raw)) {
                    throw ValidationException::withMessages(['terima' => "Jumlah diterima untuk {$name} wajib diisi (angka 0 atau lebih)."]);
                }

                $qty = (int) $raw;

                if ($qty > $line->jumlah_dikirim) {
                    throw ValidationException::withMessages(['terima' => "Jumlah diterima {$name} tidak boleh melebihi yang dikirim ({$line->jumlah_dikirim})."]);
                }
                if ($qty < $line->jumlah_dikirim && $note === '') {
                    throw ValidationException::withMessages(['catatan' => 'Catatan wajib diisi bila jumlah diterima kurang dari yang dikirim.']);
                }

                $plan[] = [$line, $qty];
            }

            $summary = [];
            foreach ($plan as [$line, $qty]) {
                // Kiriman keluar dari "perjalanan"; hanya jumlah yang benar-benar diterima masuk ke stok jual.
                $this->stock->changeTrusted($branchId, $line->product_id, -$line->jumlah_dikirim, StockMovement::DIST_TERIMA, $user->id,
                    "Penerimaan {$d->id}", 'stock_distributions', $d->id, BranchStock::POOL_PERJALANAN);

                if ($qty > 0) {
                    $this->stock->changeTrusted($branchId, $line->product_id, $qty, StockMovement::DIST_TERIMA, $user->id,
                        "Penerimaan {$d->id}", 'stock_distributions', $d->id, BranchStock::POOL_JUAL);

                    // Jika produk bertipe batch, buat batch di cabang penerima
                    if ($line->product?->usesBatchStock()) {
                        $this->batchService->receiveDistributedBatch(
                            $branchId,
                            $line->product_id,
                            $qty,
                            $line->tanggal_produksi ? Carbon::parse($line->tanggal_produksi) : null,
                            $line->tanggal_kedaluwarsa ? Carbon::parse($line->tanggal_kedaluwarsa) : null,
                            $user->id,
                            $d->id
                        );
                    }
                }

                $line->update([
                    'jumlah_diterima' => $qty, 'status' => StockDistributionDetail::DITERIMA,
                    'diterima_oleh' => $user->id, 'waktu_diterima' => now(),
                    'catatan_selisih' => $qty < $line->jumlah_dikirim ? mb_substr($note, 0, 150) : null,
                ]);

                // Update linked StockRequestDetail jika distribusi ini berasal dari permintaan stok
                if ($d->request_id) {
                    $srDetail = \App\Models\StockRequestDetail::where('request_id', $d->request_id)
                        ->where('product_id', $line->product_id)
                        ->first();
                    if ($srDetail) {
                        $srDetail->update([
                            'jumlah_terima'   => $qty,
                            'catatan_selisih' => $qty < $line->jumlah_dikirim ? mb_substr($note, 0, 255) : null,
                        ]);
                    }
                }

                $summary[] = ['product_id' => $line->product_id, 'dikirim' => $line->jumlah_dikirim, 'diterima' => $qty];
            }

            $before = $d->status;
            $this->refreshStatus($d);

            // Update linked StockRequest status to SELESAI if all items received
            if ($d->request_id) {
                $sr = StockRequest::whereKey($d->request_id)->first();
                if ($sr && $sr->isDispatched()) {
                    $sr->update(['status' => StockRequest::STATUS_SELESAI]);
                }
            }

            $this->audit->log('distribution', 'received',
                "Konfirmasi penerimaan {$d->id} di " . (Branch::whereKey($branchId)->value('nama_cabang') ?? $branchId),
                $d, ['status' => $before], ['status' => $d->status, 'baris' => $summary],
                $branchId, $user);

            return $d;
        }, 3);
    }

    /**
     * Batalkan semua baris yang MASIH dalam perjalanan: stok kembali ke gudang pusat.
     * Baris yang sudah dikonfirmasi tidak ikut dibatalkan (stoknya sudah di cabang tujuan).
     */
    public function cancel(StockDistribution $distribution, User $user, string $alasan): StockDistribution
    {
        return DB::transaction(function () use ($distribution, $user, $alasan) {
            $d = StockDistribution::whereKey($distribution->getKey())->lockForUpdate()->firstOrFail();

            $this->assertPusatStaff($user, $d->branch_asal_id);

            if ($d->status !== StockDistribution::DIKIRIM) {
                throw ValidationException::withMessages(['status' => 'Hanya distribusi yang masih dalam perjalanan yang dapat dibatalkan.']);
            }

            $lines = StockDistributionDetail::query()
                ->where('distribution_id', $d->id)->where('status', StockDistributionDetail::DIKIRIM)
                ->orderBy('product_id')->orderBy('branch_tujuan_id')->lockForUpdate()->get();

            $totals = [];
            foreach ($lines as $line) {
                $this->stock->changeTrusted($line->branch_tujuan_id, $line->product_id, -$line->jumlah_dikirim, StockMovement::DIST_BATAL, $user->id,
                    "Pembatalan {$d->id}", 'stock_distributions', $d->id, BranchStock::POOL_PERJALANAN);

                $totals[$line->product_id] = ($totals[$line->product_id] ?? 0) + $line->jumlah_dikirim;
            }
            ksort($totals);

            foreach ($totals as $pid => $qty) {
                $this->stock->changeTrusted($d->branch_asal_id, $pid, $qty, StockMovement::DIST_BATAL, $user->id,
                    "Pembatalan {$d->id}", 'stock_distributions', $d->id, BranchStock::POOL_GUDANG);

                if (Product::withTrashed()->find($pid)?->usesBatchStock()) {
                    $this->batchService->restoreGudangOnCancelDistribution($d->branch_asal_id, $pid, $qty);
                }
            }

            foreach ($lines as $line) {
                $line->update(['status' => StockDistributionDetail::DIBATALKAN]);
            }

            $d->update(['cancelled_by' => $user->id, 'cancelled_at' => now(), 'alasan_batal' => mb_substr($alasan, 0, 150)]);

            $before = $d->status;
            $this->refreshStatus($d);

            $this->audit->log('distribution', 'cancelled',
                "Membatalkan distribusi {$d->id}: {$alasan}",
                $d, ['status' => $before], ['status' => $d->status, 'alasan' => $alasan, 'baris_dibatalkan' => $lines->count(), 'total_unit_kembali' => array_sum($totals)],
                $d->branch_asal_id, $user);

            return $d;
        }, 3);
    }

    /** Status header diturunkan dari status baris: ada yang menunggu => dikirim; selain itu selesai/dibatalkan. */
    private function refreshStatus(StockDistribution $d): void
    {
        $base = StockDistributionDetail::query()->where('distribution_id', $d->id);

        if ((clone $base)->where('status', StockDistributionDetail::DIKIRIM)->exists()) {
            $status = StockDistribution::DIKIRIM;
        } elseif ((clone $base)->where('status', StockDistributionDetail::DITERIMA)->exists()) {
            $status = StockDistribution::SELESAI;
        } else {
            $status = StockDistribution::DIBATALKAN;
        }

        $d->update(['status' => $status]);
    }

    private function assertPusatStaff(User $user, string $pusatBranchId): void
    {
        if (! $user->isOwner() && $user->branch_id !== $pusatBranchId) {
            throw new AuthorizationException('Hanya Owner atau akun Cabang Pusat yang dapat mengelola pengiriman distribusi.');
        }
    }

    private function findByKey(string $key, User $user): ?StockDistribution
    {
        $existing = StockDistribution::query()->where('idempotency_key', $key)->first();

        if ($existing && $existing->user_id !== $user->id) {
            throw ValidationException::withMessages(['idempotency_key' => 'Kunci distribusi tidak valid.']);
        }

        return $existing;
    }
}
