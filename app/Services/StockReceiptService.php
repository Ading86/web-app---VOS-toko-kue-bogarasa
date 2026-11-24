<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptDetail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockReceiptService
{
    public const TIMEZONE = 'Asia/Jakarta';

    public function __construct(
        private CodeGeneratorService $codes,
        private StockService $stock,
        private StockBatchService $batchService,
        private ActivityLogService $audit,
    ) {}

    /**
     * Buat nomor penerimaan yang unik dan informatif: RCV-YYYYMMDD-XXXX
     */
    public function generateReceiptNumber(Carbon $tanggal): string
    {
        $prefix = 'RCV-' . $tanggal->format('Ymd') . '-';
        $countToday = StockReceipt::whereDate('tanggal_penerimaan', $tanggal->toDateString())->count();
        $next = str_pad((string) ($countToday + 1), 4, '0', STR_PAD_LEFT);

        $candidate = $prefix . $next;
        // Jaga-jaga bila ada collision
        $i = 1;
        while (StockReceipt::where('nomor_penerimaan', $candidate)->exists()) {
            $candidate = $prefix . str_pad((string) ($countToday + 1 + $i), 4, '0', STR_PAD_LEFT);
            $i++;
        }

        return $candidate;
    }

    /**
     * Catat penerimaan stok multi-produk di Gudang Pusat.
     *
     * @param array<int, array{product_id: string, jumlah: int}> $items
     * @throws ValidationException
     */
    public function receive(User $user, Carbon $tanggalPenerimaan, ?string $catatan, array $items): StockReceipt
    {
        $pusat = Branch::pusat();

        if (! $pusat || $pusat->status !== 'aktif') {
            throw ValidationException::withMessages(['branch' => 'Cabang Pusat belum ditetapkan atau tidak aktif.']);
        }

        // Pastikan hanya staff pusat atau owner yang dapat mencatat penerimaan gudang pusat
        if (! $user->isOwner() && $user->branch_id !== $pusat->id) {
            throw ValidationException::withMessages(['user' => 'Penerimaan stok Gudang Pusat hanya dapat dilakukan oleh staf Cabang Pusat atau Owner.']);
        }

        // Normalisasi items
        $normalized = [];
        foreach ($items as $item) {
            $pid = (string) ($item['product_id'] ?? '');
            $qty = (int) ($item['jumlah'] ?? 0);

            if ($pid === '' || $qty < 1) {
                continue;
            }

            $normalized[$pid] = ($normalized[$pid] ?? 0) + $qty;
        }

        if (empty($normalized)) {
            throw ValidationException::withMessages(['items' => 'Masukkan minimal satu produk dengan jumlah stok masuk minimal 1 pcs.']);
        }

        $products = Product::whereIn('id_produk', array_keys($normalized))->where('status', 'aktif')->get()->keyBy('id_produk');
        if ($products->count() !== count($normalized)) {
            throw ValidationException::withMessages(['items' => 'Ada produk yang tidak ditemukan atau sudah nonaktif.']);
        }

        // Jalankan pembersihan otomatis untuk batch yang sudah kedaluwarsa di gudang pusat
        $this->batchService->autoExpireStaleBatches($pusat->id, $user->id);

        return DB::transaction(function () use ($user, $pusat, $tanggalPenerimaan, $catatan, $normalized, $products) {
            $receiptId = $this->codes->forTable('stock_receipts');
            $nomorPenerimaan = $this->generateReceiptNumber($tanggalPenerimaan);

            $receipt = new StockReceipt([
                'nomor_penerimaan'   => $nomorPenerimaan,
                'branch_id'          => $pusat->id,
                'user_id'            => $user->id,
                'tanggal_penerimaan' => $tanggalPenerimaan->toDateString(),
                'catatan'            => $catatan !== null && trim($catatan) !== '' ? mb_substr(trim($catatan), 0, 250) : null,
                'total_item'         => count($normalized),
                'total_kuantitas'    => array_sum($normalized),
            ]);
            $receipt->id = $receiptId;
            StockReceipt::withoutEvents(fn () => $receipt->save());

            foreach ($normalized as $productId => $jumlah) {
                $product = $products[$productId];

                // Aturan masa layak jual:
                // - Kue basah: otomatis 1 hari (kedaluwarsa = hari itu juga)
                // - Kue kering: sesuai konfigurasi product (default 5 hari)
                // - Standard: 0 / tidak berlaku
                if ($product->tipe_kue === Product::TIPE_BASAH) {
                    $masaLayak = 1;
                } elseif ($product->tipe_kue === Product::TIPE_KERING) {
                    $masaLayak = max(1, (int) ($product->masa_layak_jual ?: 5));
                } else {
                    $masaLayak = max(1, (int) ($product->masa_layak_jual ?: 1));
                }

                $kedaluwarsa = $tanggalPenerimaan->copy()->addDays($masaLayak - 1); // inklusif

                // 1) Tambah stok di Gudang Pusat (POOL_GUDANG)
                $movement = $this->stock->change(
                    $pusat->id,
                    $productId,
                    $jumlah,
                    StockMovement::MASUK,
                    $user->id,
                    "Penerimaan stok {$nomorPenerimaan}",
                    'stock_receipts',
                    $receiptId,
                    BranchStock::POOL_GUDANG,
                );

                // 2) Buat batch di Gudang Pusat untuk produk ber-batch
                $batchId = null;
                if ($product->usesBatchStock()) {
                    $batchId = $this->codes->forTable('stock_batches');
                    $batch   = new StockBatch([
                        'branch_id'           => $pusat->id,
                        'product_id'          => $productId,
                        'user_id'             => $user->id,
                        'tanggal_produksi'    => $tanggalPenerimaan->toDateString(),
                        'tanggal_kedaluwarsa' => $kedaluwarsa->toDateString(),
                        'jumlah_masuk'        => $jumlah,
                        'status'              => StockBatch::STATUS_AKTIF,
                        'keterangan'          => "Penerimaan stok {$nomorPenerimaan}",
                        'movement_id'         => $movement->id,
                    ]);
                    $batch->id = $batchId;
                    StockBatch::withoutEvents(fn () => $batch->save());
                }

                // 3) Buat detail baris penerimaan
                $detailId = $this->codes->forTable('stock_receipt_details');
                $detail   = new StockReceiptDetail([
                    'receipt_id'          => $receiptId,
                    'product_id'          => $productId,
                    'jumlah'              => $jumlah,
                    'masa_layak_jual'     => $masaLayak,
                    'tanggal_kedaluwarsa' => $kedaluwarsa->toDateString(),
                    'batch_id'            => $batchId,
                ]);
                $detail->id = $detailId;
                StockReceiptDetail::withoutEvents(fn () => $detail->save());
            }

            $this->audit->log(
                'stock_receipt',
                'created',
                "Penerimaan stok {$nomorPenerimaan}: " . count($normalized) . ' produk, total ' . array_sum($normalized) . ' pcs',
                $receipt,
                null,
                ['nomor' => $nomorPenerimaan, 'total_item' => count($normalized), 'total_kuantitas' => array_sum($normalized)],
                $pusat->id,
                $user
            );

            return $receipt;
        }, 3);
    }
}
