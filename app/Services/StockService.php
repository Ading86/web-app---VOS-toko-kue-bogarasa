<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\StockLimitException;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan stok. Setiap perubahan:
 *  - berjalan dalam database transaction,
 *  - mengunci baris stok (SELECT ... FOR UPDATE),
 *  - menolak stok negatif dan stok di atas BranchStock::MAX_STOK,
 *  - tercatat di stock_movements (lengkap dengan kantong stok yang berubah).
 * Urutan kunci SELALU: sequence MOV dulu, baru baris stok (mencegah deadlock antar alur).
 *
 * Tiga kantong stok (lihat BranchStock::POOL_*): jual (default), gudang (khusus Cabang Pusat), perjalanan.
 */
class StockService
{
    public function __construct(private CodeGeneratorService $codes)
    {
    }

    /** Tambah ($delta > 0) atau kurangi ($delta < 0) stok. Akun cabang hanya boleh mengubah cabangnya sendiri. */
    public function change(
        string $branchId, string $productId, int $delta, string $jenis, string $userId,
        ?string $keterangan = null, ?string $refType = null, ?string $refId = null,
        string $pool = BranchStock::POOL_JUAL
    ): StockMovement {
        $this->assertBranchAllowed($branchId);

        return $this->mutate($branchId, $productId, fn (int $before) => $delta, $jenis, $userId, $keterangan, $refType, $refId, $pool);
    }

    /**
     * Sama seperti change() tetapi TANPA pemeriksaan cabang akun. Hanya dipakai DistributionService,
     * yang sudah memeriksa hak pengirim/penerima sendiri (distribusi memang menyentuh cabang lain).
     */
    public function changeTrusted(
        string $branchId, string $productId, int $delta, string $jenis, string $userId,
        ?string $keterangan = null, ?string $refType = null, ?string $refId = null,
        string $pool = BranchStock::POOL_JUAL
    ): StockMovement {
        return $this->mutate($branchId, $productId, fn (int $before) => $delta, $jenis, $userId, $keterangan, $refType, $refId, $pool);
    }

    /** Koreksi: set stok menjadi nilai absolut. Selisih dicatat sebagai movement bertanda. */
    public function setTo(
        string $branchId, string $productId, int $newQty, string $userId, ?string $keterangan = null,
        string $pool = BranchStock::POOL_JUAL
    ): StockMovement {
        $this->assertBranchAllowed($branchId);

        return $this->mutate($branchId, $productId, fn (int $before) => $newQty - $before,
            StockMovement::PENYESUAIAN, $userId, $keterangan, null, null, $pool);
    }

    /** Ubah stok minimum (bukan pergerakan stok). @return array{0:int,1:int} [sebelum, sesudah] */
    public function setMinimum(string $branchId, string $productId, int $minimum): array
    {
        $this->assertBranchAllowed($branchId);

        return DB::transaction(function () use ($branchId, $productId, $minimum) {
            $stock = $this->lockRow($branchId, $productId);
            $before = (int) $stock->stok_minimum;
            $stock->stok_minimum = $minimum;
            $stock->save();

            return [$before, $minimum];
        }, 3);
    }

    private function mutate(
        string $branchId, string $productId, callable $deltaFor, string $jenis, string $userId,
        ?string $keterangan, ?string $refType, ?string $refId, string $pool
    ): StockMovement {
        $column = BranchStock::columnFor($pool);   // melempar InvalidArgumentException bila kantong tidak dikenal

        return DB::transaction(function () use ($branchId, $productId, $deltaFor, $jenis, $userId, $keterangan, $refType, $refId, $pool, $column) {
            $movementId = $this->codes->forTable('stock_movements');   // 1) kunci sequence
            $stock = $this->lockRow($branchId, $productId);            // 2) kunci baris stok

            if ($pool === BranchStock::POOL_GUDANG && ! Branch::whereKey($branchId)->where('is_pusat', true)->exists()) {
                throw new \DomainException('Stok gudang hanya dimiliki Cabang Pusat.');
            }

            $before = (int) $stock->{$column};
            $delta = (int) $deltaFor($before);
            $after = $before + $delta;

            if ($after < 0) {
                throw new InsufficientStockException($this->productName($productId), $before, abs($delta), $this->poolLabel($pool));
            }
            if ($after > BranchStock::MAX_STOK) {
                throw new StockLimitException($this->productName($productId), BranchStock::MAX_STOK);
            }

            $stock->{$column} = $after;
            $stock->save();

            $movement = new StockMovement([
                'branch_id' => $branchId, 'product_id' => $productId, 'user_id' => $userId,
                'lokasi_stok' => $pool, 'jenis_pergerakan' => $jenis, 'jumlah' => $delta,
                'stok_sebelum' => $before, 'stok_sesudah' => $after,
                'keterangan' => $keterangan === null ? null : mb_substr($keterangan, 0, 150),
                'reference_type' => $refType, 'reference_id' => $refId,
            ]);
            $movement->id = $movementId;

            // Tanpa event: id sudah ditetapkan, dan hook BelongsToBranch tidak boleh menimpa branch_id
            // (pada distribusi, pengirim di Cabang Pusat menulis pergerakan milik cabang tujuan).
            // Pembatasan hak akun cabang sudah dilakukan assertBranchAllowed()/DistributionService.
            StockMovement::withoutEvents(fn () => $movement->save());

            return $movement;
        }, 3);
    }

    private function lockRow(string $branchId, string $productId): BranchStock
    {
        $find = fn () => BranchStock::withoutGlobalScopes()
            ->where('branch_id', $branchId)->where('product_id', $productId)->lockForUpdate()->first();

        $stock = $find();

        if (! $stock) {
            try {
                // Baris stok normalnya dibuat saat produk/cabang dibuat; ini hanya jaring pengaman.
                $row = new BranchStock(['branch_id' => $branchId, 'product_id' => $productId]);
                $row->id = $this->codes->forTable('branch_stocks');
                BranchStock::withoutEvents(fn () => $row->save());
            } catch (QueryException $e) {
                // baris sudah dibuat proses lain (unique branch+product): abaikan, baca ulang
            }
            $stock = $find();
        }

        return $stock;
    }

    private function assertBranchAllowed(string $branchId): void
    {
        $user = auth()->user();

        if ($user && ! $user->isOwner() && $user->branch_id !== $branchId) {
            throw new AuthorizationException('Anda tidak berhak mengubah stok cabang lain.');
        }
    }

    private function productName(string $productId): string
    {
        return Product::withTrashed()->whereKey($productId)->value('nama_produk') ?? $productId;
    }

    private function poolLabel(string $pool): string
    {
        return match ($pool) {
            BranchStock::POOL_GUDANG => 'gudang pusat',
            BranchStock::POOL_PERJALANAN => 'stok dalam perjalanan',
            default => 'stok jual',
        };
    }
}
