<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manajemen stok berbasis batch untuk kue basah dan kue kering.
 *
 * Aturan bisnis:
 *  - Zona waktu: Asia/Jakarta untuk pergantian tanggal operasional.
 *  - Gudang Pusat menerima stok kue basah & kering dari produksi/pemasok.
 *  - Kue basah  : masa berlaku stok maksimal 1 hari (hanya tanggal produksi).
 *                 Ketika tanggal operasional berganti, stok hari sebelumnya otomatis TIDAK tersedia untuk penjualan maupun distribusi.
 *                 Sisa stok dapat dikosongkan menggunakan tombol Clear pada pengelolaan stok.
 *  - Kue kering : masa layak jual maksimal 5 hari sejak tanggal produksi.
 *                 Stok yang masih layak jual tetap tersedia saat tanggal berganti dan diakumulasi (FIFO).
 *                 Batch yang kedaluwarsa otomatis tidak tersedia untuk transaksi maupun distribusi.
 *  - Standard   : tidak menggunakan sistem batch; stok dikelola BranchStock biasa.
 *
 * Urutan kunci (mencegah deadlock):
 *   sequence SMV → sequence SBT → baris branch_stocks → baris stock_batches
 */
class StockBatchService
{
    public function __construct(
        private CodeGeneratorService $codes,
        private StockService $stock,
        private ActivityLogService $audit,
    ) {}

    /** Tanggal hari ini dalam format Y-m-d di zona waktu Asia/Jakarta. */
    public function todayString(): string
    {
        return StockBatch::todayString();
    }

    /** Tanggal hari ini sebagai objek Carbon di zona waktu Asia/Jakarta. */
    public function todayCarbon(): Carbon
    {
        return StockBatch::todayJakarta();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // STOK MASUK (BATCH)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Catat stok masuk sebagai batch baru (kue basah atau kue kering).
     * Juga menambah jumlah_stok di BranchStock agar sinkron dengan stok fisik.
     *
     * @throws \App\Exceptions\StockException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function receiveBatch(
        string $branchId,
        string $productId,
        int $jumlah,
        Carbon $tanggalProduksi,
        string $userId,
        ?string $keterangan = null,
    ): StockBatch {
        $product = Product::withTrashed()->findOrFail($productId);

        if (! $product->usesBatchStock()) {
            throw ValidationException::withMessages([
                'product_id' => "Produk [{$product->nama_produk}] bertipe standard; gunakan menu Persediaan biasa.",
            ]);
        }

        // Sebelum mencatat batch baru, jalankan pembersihan otomatis untuk batch yang sudah kedaluwarsa
        $this->autoExpireStaleBatches($branchId, $userId);

        $masaLayak   = ($product->tipe_kue === Product::TIPE_BASAH) ? 1 : max(1, (int) ($product->masa_layak_jual ?: 5));
        $kedaluwarsa = $tanggalProduksi->copy()->addDays($masaLayak - 1); // inklusif

        $pusat = Branch::pusat();
        $isPusat = ($pusat && $branchId === $pusat->id);
        $pool = $isPusat ? BranchStock::POOL_GUDANG : BranchStock::POOL_JUAL;

        return DB::transaction(function () use (
            $branchId, $productId, $jumlah, $tanggalProduksi, $kedaluwarsa, $userId, $keterangan, $product, $pool
        ) {
            // 1) Catat pergerakan stok
            $movement = $this->stock->change(
                $branchId, $productId, $jumlah,
                StockMovement::BATCH_MASUK, $userId,
                $keterangan ?? "Batch masuk {$tanggalProduksi->format('d/m/Y')}",
                'stock_batches', null,
                $pool,
            );

            // 2) Buat baris batch baru
            $batchId = $this->codes->forTable('stock_batches');
            $batch   = new StockBatch([
                'branch_id'           => $branchId,
                'product_id'          => $productId,
                'user_id'             => $userId,
                'tanggal_produksi'    => $tanggalProduksi->toDateString(),
                'tanggal_kedaluwarsa' => $kedaluwarsa->toDateString(),
                'jumlah_masuk'        => $jumlah,
                'status'              => StockBatch::STATUS_AKTIF,
                'keterangan'          => $keterangan,
                'movement_id'         => $movement->id,
            ]);
            $batch->id_batch = $batchId;
            StockBatch::withoutEvents(fn () => $batch->save());

            // 3) Update reference_id di movement → arahkan ke ID batch
            DB::table('stock_movements')->where('id_pergerakan', $movement->id_pergerakan)
              ->update(['reference_id' => $batchId]);

            $this->audit->log(
                'stock_batch', 'batch_masuk',
                "Batch masuk {$product->nama_produk}: {$jumlah} pcs, kedaluwarsa {$kedaluwarsa->format('d/m/Y')}",
                $batch, null,
                ['jumlah' => $jumlah, 'tanggal_produksi' => $tanggalProduksi->toDateString(), 'tanggal_kedaluwarsa' => $kedaluwarsa->toDateString()],
                $branchId,
            );

            return $batch;
        }, 3);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PENJUALAN VIA BATCH (dipanggil TransactionService saat checkout)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Kurangi batch FIFO untuk produk batch saat checkout.
     * Hanya batch yang masih layak jual (aktif & tanggal_kedaluwarsa >= hari ini di Asia/Jakarta)
     * yang boleh digunakan.
     *
     * @throws InsufficientStockException bila stok batch layak jual tidak mencukupi
     */
    public function deductBatchForSale(
        string $branchId,
        string $productId,
        int $qty,
        string $transactionId,
        string $userId,
    ): void {
        $today = $this->todayString();
        $pusat = Branch::pusat();
        $isPusat = ($pusat && $branchId === $pusat->id);

        // Lock semua batch aktif produk ini di cabang ini yang masih layak jual, FIFO (kedaluwarsa terlama duluan)
        $batches = StockBatch::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('status', StockBatch::STATUS_AKTIF)
            ->whereDate('tanggal_kedaluwarsa', '>=', $today)
            ->when($isPusat, function ($q) {
                // Di cabang pusat, penjualan POS hanya memotong batch yang dialokasikan untuk etalase/jual
                $q->where('keterangan', 'like', 'Distribusi diterima%');
            })
            ->orderBy('tanggal_kedaluwarsa')
            ->orderBy('id_batch')
            ->lockForUpdate()
            ->get();

        $available = $batches->sum('jumlah_sisa');
        if ($available < $qty) {
            $name = Product::withTrashed()->whereKey($productId)->value('nama_produk') ?? $productId;
            throw new InsufficientStockException($name, $available, $qty);
        }

        $remaining = $qty;
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, $batch->jumlah_sisa);
            DB::table('stock_batches')
              ->where('id_batch', $batch->id_batch)
              ->update(['jumlah_terjual' => $batch->jumlah_terjual + $take, 'updated_at' => now()]);
            $remaining -= $take;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PEMBATALAN TRANSAKSI (kembalikan ke batch LIFO)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Kembalikan stok batch LIFO saat transaksi penjualan dibatalkan.
     */
    public function restoreBatchOnCancel(
        string $branchId,
        string $productId,
        int $qty,
    ): void {
        $batches = StockBatch::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('status', StockBatch::STATUS_AKTIF)
            ->where('jumlah_terjual', '>', 0)
            ->orderByDesc('tanggal_kedaluwarsa')
            ->orderByDesc('id_batch')
            ->lockForUpdate()
            ->get();

        $remaining = $qty;
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $restore = min($remaining, $batch->jumlah_terjual);
            DB::table('stock_batches')
              ->where('id_batch', $batch->id_batch)
              ->update(['jumlah_terjual' => $batch->jumlah_terjual - $restore, 'updated_at' => now()]);
            $remaining -= $restore;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // INTEGRASI DISTRIBUSI (Gudang Pusat -> Cabang Tujuan)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Hitung stok gudang pusat yang aktif & masih layak kirim (belum kedaluwarsa).
     */
    public function availableGudangQty(string $pusatId, string $productId): int
    {
        $today = $this->todayString();

        return (int) StockBatch::withoutGlobalScopes()
            ->where('branch_id', $pusatId)
            ->where('product_id', $productId)
            ->where('status', StockBatch::STATUS_AKTIF)
            ->whereDate('tanggal_kedaluwarsa', '>=', $today)
            ->where(function ($q) {
                // Hanya batch gudang pusat (bukan alokasi cabang pusat sendiri)
                $q->whereNull('keterangan')
                  ->orWhere('keterangan', 'not like', 'Distribusi diterima%');
            })
            ->selectRaw('SUM(jumlah_masuk - jumlah_terjual - jumlah_kadaluwarsa) as sisa')
            ->value('sisa');
    }

    /**
     * Potong batch di Gudang Pusat secara FIFO saat membuat distribusi.
     * Mengembalikan informasi tanggal batch terlama yang dipotong.
     *
     * @return array{tanggal_produksi: string, tanggal_kedaluwarsa: string}
     * @throws ValidationException
     */
    public function deductGudangForDistribution(string $pusatId, string $productId, int $qty): array
    {
        $today = $this->todayString();

        $batches = StockBatch::withoutGlobalScopes()
            ->where('branch_id', $pusatId)
            ->where('product_id', $productId)
            ->where('status', StockBatch::STATUS_AKTIF)
            ->whereDate('tanggal_kedaluwarsa', '>=', $today)
            ->where(function ($q) {
                // Hanya batch gudang pusat (bukan alokasi cabang pusat sendiri)
                $q->whereNull('keterangan')
                  ->orWhere('keterangan', 'not like', 'Distribusi diterima%');
            })
            ->orderBy('tanggal_kedaluwarsa')
            ->orderBy('id_batch')
            ->lockForUpdate()
            ->get();

        $available = $batches->sum('jumlah_sisa');
        if ($available < $qty) {
            $name = Product::withTrashed()->whereKey($productId)->value('nama_produk') ?? $productId;
            throw ValidationException::withMessages([
                'items' => "Stok gudang produk [{$name}] yang masih layak jual tidak mencukupi untuk didistribusikan (tersedia: {$available}, diminta: {$qty}).",
            ]);
        }

        $remaining = $qty;
        $firstBatch = $batches->first();
        $tglProduksi = $firstBatch?->tanggal_produksi?->toDateString() ?? $today;
        $tglKedaluwarsa = $firstBatch?->tanggal_kedaluwarsa?->toDateString() ?? $today;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, $batch->jumlah_sisa);
            DB::table('stock_batches')
              ->where('id_batch', $batch->id_batch)
              ->update(['jumlah_terjual' => $batch->jumlah_terjual + $take, 'updated_at' => now()]);
            $remaining -= $take;
        }

        return [
            'tanggal_produksi'    => $tglProduksi,
            'tanggal_kedaluwarsa' => $tglKedaluwarsa,
        ];
    }

    /**
     * Buat / catat batch di Cabang Tujuan saat mengonfirmasi penerimaan distribusi.
     */
    public function receiveDistributedBatch(
        string $branchId,
        string $productId,
        int $qty,
        ?Carbon $tglProduksi,
        ?Carbon $tglKedaluwarsa,
        string $userId,
        string $distId,
    ): ?StockBatch {
        $product = Product::withTrashed()->find($productId);
        if (! $product || ! $product->usesBatchStock()) {
            return null;
        }

        $today = $this->todayCarbon();
        $prodDate = $tglProduksi ?? $today;
        $expDate  = $tglKedaluwarsa;

        if (! $expDate) {
            $masa = ($product->tipe_kue === Product::TIPE_BASAH) ? 1 : max(1, (int) ($product->masa_layak_jual ?: 5));
            $expDate = $prodDate->copy()->addDays($masa - 1);
        }

        $batchId = $this->codes->forTable('stock_batches');
        $batch   = new StockBatch([
            'branch_id'           => $branchId,
            'product_id'          => $productId,
            'user_id'             => $userId,
            'tanggal_produksi'    => $prodDate->toDateString(),
            'tanggal_kedaluwarsa' => $expDate->toDateString(),
            'jumlah_masuk'        => $qty,
            'status'              => StockBatch::STATUS_AKTIF,
            'keterangan'          => "Distribusi diterima {$distId}",
        ]);
        $batch->id_batch = $batchId;
        StockBatch::withoutEvents(fn () => $batch->save());

        return $batch;
    }

    /**
     * Kembalikan batch ke Gudang Pusat saat distribusi dibatalkan.
     */
    public function restoreGudangOnCancelDistribution(string $pusatId, string $productId, int $qty): void
    {
        $batches = StockBatch::withoutGlobalScopes()
            ->where('branch_id', $pusatId)
            ->where('product_id', $productId)
            ->where('status', StockBatch::STATUS_AKTIF)
            ->where('jumlah_terjual', '>', 0)
            ->orderByDesc('tanggal_kedaluwarsa')
            ->orderByDesc('id_batch')
            ->lockForUpdate()
            ->get();

        $remaining = $qty;
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $restore = min($remaining, $batch->jumlah_terjual);
            DB::table('stock_batches')
              ->where('id_batch', $batch->id_batch)
              ->update(['jumlah_terjual' => $batch->jumlah_terjual - $restore, 'updated_at' => now()]);
            $remaining -= $restore;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FITUR TOMBOL CLEAR KUE BASAH
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Kosongkan sisa stok kue basah di cabang atau gudang menggunakan tombol Clear.
     */
    public function clearBasahStock(string $branchId, string $productId, string $userId): int
    {
        $product = Product::withTrashed()->with('category')->findOrFail($productId);

        if (! $product->isBasah()) {
            throw ValidationException::withMessages(['product_id' => 'Tombol Clear hanya berlaku untuk produk kue basah.']);
        }

        return DB::transaction(function () use ($branchId, $productId, $userId, $product) {
            $pusat = Branch::pusat();
            $isPusat = ($pusat && $branchId === $pusat->id);

            // 1. Kosongkan stok fisik di BranchStock
            $stock = BranchStock::withoutGlobalScopes()->where('branch_id', $branchId)->where('product_id', $productId)->lockForUpdate()->first();
            $clearedQty = 0;

            if ($stock) {
                if ($stock->jumlah_stok > 0) {
                    $qty = (int) $stock->jumlah_stok;
                    $clearedQty += $qty;
                    $this->stock->changeTrusted(
                        $branchId, $productId, -$qty,
                        StockMovement::KEDALUWARSA, $userId,
                        "Clear stok kue basah {$product->nama_produk}",
                        'products', $productId,
                        BranchStock::POOL_JUAL
                    );
                }
                if ($isPusat && $stock->stok_gudang > 0) {
                    $qtyGudang = (int) $stock->stok_gudang;
                    $clearedQty += $qtyGudang;
                    $this->stock->changeTrusted(
                        $branchId, $productId, -$qtyGudang,
                        StockMovement::KEDALUWARSA, $userId,
                        "Clear stok gudang kue basah {$product->nama_produk}",
                        'products', $productId,
                        BranchStock::POOL_GUDANG
                    );
                }
            }

            // 2. Tandai semua batch kue basah aktif di cabang ini menjadi kedaluwarsa
            $batches = StockBatch::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->where('status', StockBatch::STATUS_AKTIF)
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                $sisa = $batch->jumlah_sisa;
                DB::table('stock_batches')->where('id_batch', $batch->id_batch)->update([
                    'jumlah_kadaluwarsa' => $batch->jumlah_kadaluwarsa + $sisa,
                    'status'             => StockBatch::STATUS_KEDALUWARSA,
                    'updated_at'         => now(),
                ]);
            }

            $this->audit->log(
                'inventory',
                'clear_basah',
                "Mengosongkan sisa stok kue basah {$product->nama_produk}: {$clearedQty} pcs di " . (Branch::whereKey($branchId)->value('nama_cabang') ?? $branchId),
                null,
                null,
                ['branch_id' => $branchId, 'product_id' => $productId, 'cleared_qty' => $clearedQty],
                $branchId,
            );

            return $clearedQty;
        }, 3);
    }

    /**
     * Kosongkan seluruh sisa stok produk kue basah yang ada di cabang.
     */
    public function clearAllBasahInBranch(string $branchId, string $userId): int
    {
        $basahProducts = Product::where('status', 'aktif')->with('category')->get()->filter->isBasah();
        $total = 0;
        foreach ($basahProducts as $p) {
            $total += $this->clearBasahStock($branchId, $p->id_produk, $userId);
        }

        return $total;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AUTOMATIC STOCK EXPIRATION
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Mekanisme otomatisasi ekspirasi stok:
     * Menandai batch yang telah melewati masa layak jual (tanggal_kedaluwarsa < hari ini di Asia/Jakarta)
     * menjadi 'kedaluwarsa', memotong sisa stok dari BranchStock, dan mencatat riwayat pergerakan stok.
     *
     * Berjalan otomatis tanpa intervensi pegawai.
     *
     * @return int Total pcs stok kedaluwarsa yang diproses keluar
     */
    public function autoExpireStaleBatches(?string $branchId = null, ?string $userId = null): int
    {
        $today = $this->todayString();
        $pusat = Branch::pusat();

        $staleBatches = StockBatch::withoutGlobalScopes()
            ->with('product')
            ->when($branchId, fn ($q, $b) => $q->where('branch_id', $b))
            ->where('status', StockBatch::STATUS_AKTIF)
            ->whereDate('tanggal_kedaluwarsa', '<', $today)
            ->orderBy('tanggal_kedaluwarsa')
            ->lockForUpdate()
            ->get();

        if ($staleBatches->isEmpty()) {
            return 0;
        }

        $totalExpiredPcs = 0;
        $fallbackUserId  = $userId ?? User::role('Owner')->value('id_user') ?? 'USR001';

        foreach ($staleBatches as $batch) {
            $sisa = $batch->jumlah_sisa;

            if ($sisa > 0) {
                $isBasah = ($batch->product?->tipe_kue === Product::TIPE_BASAH);
                $isPusat = ($pusat && $batch->branch_id === $pusat->id);
                // Di cabang pusat, jika batch merupakan penerimaan distribusi (alokasi cabang sendiri),
                // maka batch tersebut berada di kantong jual. Batch masuk gudang berada di kantong gudang.
                // Di cabang lain, batch selalu berada di kantong jual.
                $isAlokasiJualPusat = $isPusat && str_starts_with((string) ($batch->keterangan ?? ''), 'Distribusi diterima');
                $pool = ($isPusat && ! $isAlokasiJualPusat) ? BranchStock::POOL_GUDANG : BranchStock::POOL_JUAL;

                $keterangan = $isBasah
                    ? "Kedaluwarsa otomatis kue basah (produksi {$batch->tanggal_produksi->format('d/m/Y')})"
                    : "Kedaluwarsa otomatis kue kering (lewat masa layak jual {$batch->tanggal_kedaluwarsa->format('d/m/Y')})";

                // Hitung sisa fisik yang ada di kantong BranchStock untuk mencegah pengurangan melebihi stok yang ada
                $column = BranchStock::columnFor($pool);
                $stockRow = BranchStock::withoutGlobalScopes()
                    ->where('branch_id', $batch->branch_id)
                    ->where('product_id', $batch->product_id)
                    ->lockForUpdate()
                    ->first();

                $currentStock = $stockRow ? (int) $stockRow->{$column} : 0;
                $deduct = max(0, min($sisa, $currentStock));

                // 1) Kurangi stok di BranchStock hanya sebesar yang tersedia (tidak boleh menghasilkan stok negatif)
                if ($deduct > 0) {
                    $this->stock->changeTrusted(
                        $batch->branch_id,
                        $batch->product_id,
                        -$deduct,
                        StockMovement::KEDALUWARSA,
                        $fallbackUserId,
                        $keterangan,
                        'stock_batches',
                        $batch->id_batch,
                        $pool,
                    );
                }

                // 2) Update batch status & jumlah_kadaluwarsa
                DB::table('stock_batches')->where('id_batch', $batch->id_batch)->update([
                    'jumlah_kadaluwarsa' => $batch->jumlah_kadaluwarsa + $sisa,
                    'status'             => StockBatch::STATUS_KEDALUWARSA,
                    'updated_at'         => now(),
                ]);

                $totalExpiredPcs += $sisa;
            } else {
                // Semua barang sudah terjual habis, cukup ubah status
                DB::table('stock_batches')->where('id_batch', $batch->id_batch)->update([
                    'status'     => StockBatch::STATUS_KEDALUWARSA,
                    'updated_at' => now(),
                ]);
            }
        }

        if ($totalExpiredPcs > 0) {
            $this->audit->log(
                'stock_batch', 'auto_expire',
                "Ekspirasi otomatis stok: {$totalExpiredPcs} pcs melewati masa layak jual",
                null, null,
                ['total_expired' => $totalExpiredPcs, 'tanggal' => $today],
                $branchId,
            );
        }

        return $totalExpiredPcs;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // UTILITAS KETERSEDIAAN STOK
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Hitung stok batch yang tersedia (layak jual) untuk satu produk di satu cabang
     * berdasarkan zona waktu Asia/Jakarta.
     */
    public function availableQty(string $branchId, string $productId): int
    {
        $today = $this->todayString();
        $pusat = Branch::pusat();
        $isPusat = ($pusat && $branchId === $pusat->id);

        return (int) StockBatch::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('status', StockBatch::STATUS_AKTIF)
            ->whereDate('tanggal_kedaluwarsa', '>=', $today)
            ->when($isPusat, function ($q) {
                $q->where('keterangan', 'like', 'Distribusi diterima%');
            })
            ->selectRaw('SUM(jumlah_masuk - jumlah_terjual - jumlah_kadaluwarsa) as sisa')
            ->value('sisa');
    }
}
