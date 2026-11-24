<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    /** Sejalan dengan kolom transaction_details.jumlah (SMALLINT) dan batas validasi POS. */
    public const MAX_QTY_PER_PRODUCT = 9999;

    public function __construct(
        private CodeGeneratorService $codes,
        private StockService $stock,
        private ActivityLogService $audit,
        private StockBatchService $batch,
    ) {
    }

    /**
     * @param  array<int, array{product_id:string, qty:int}>  $items
     * @throws \App\Exceptions\InsufficientStockException
     */
    public function checkout(User $user, array $items, string $metode, float|int $bayar, ?string $referensi, string $idempotencyKey): Transaction
    {
        if (! $user->branch_id) {
            throw ValidationException::withMessages(['branch' => 'Akun Anda belum ditugaskan ke cabang.']);
        }

        // Request ulang (double click / retry): kembalikan transaksi yang sama.
        if ($existing = $this->findByKey($idempotencyKey, $user)) {
            return $existing;
        }

        try {
            return DB::transaction(
                fn () => $this->doCheckout($user, $items, $metode, $bayar, $referensi, $idempotencyKey), 3
            );
        } catch (QueryException $e) {
            // Dua request dengan key sama berlomba: unique idempotency_key menolak yang kedua.
            if (in_array($e->getCode(), ['23000', 23000], true) && ($existing = $this->findByKey($idempotencyKey, $user))) {
                return $existing;
            }
            throw $e;
        }
    }

    private function doCheckout(User $user, array $items, string $metode, float|int $bayar, ?string $referensi, string $key): Transaction
    {
        $branch = Branch::findOrFail($user->branch_id);

        // Gabungkan baris produk sama; urutkan agar urutan penguncian stok selalu sama.
        $qtyByProduct = [];
        foreach ($items as $item) {
            $pid = (string) $item['product_id'];
            $qtyByProduct[$pid] = ($qtyByProduct[$pid] ?? 0) + (int) $item['qty'];
        }
        ksort($qtyByProduct);

        foreach ($qtyByProduct as $qty) {
            if ($qty < 1 || $qty > self::MAX_QTY_PER_PRODUCT) {
                throw ValidationException::withMessages(['items' => 'Jumlah per produk dalam satu transaksi harus 1 sampai ' . self::MAX_QTY_PER_PRODUCT . '.']);
            }
        }

        // Harga SELALU dari database, bukan dari klien.
        $products = Product::whereIn('id_produk', array_keys($qtyByProduct))->where('status', 'aktif')->get()->keyBy('id_produk');
        if ($products->count() !== count($qtyByProduct)) {
            throw ValidationException::withMessages(['items' => 'Ada produk yang tidak tersedia atau sudah nonaktif.']);
        }

        $total = 0.0;
        $lines = [];
        foreach ($qtyByProduct as $pid => $qty) {
            $p = $products[$pid];
            $sub = round((float) $p->harga * $qty, 2);
            $total += $sub;
            $lines[] = [$p, $qty, $sub];
        }
        $total = round($total, 2);

        if ($metode === 'tunai') {
            if (round((float) $bayar, 2) < $total) {
                throw ValidationException::withMessages(['jumlah_bayar' => 'Uang pembayaran kurang dari total belanja.']);
            }
            $dibayar   = round((float) $bayar, 2);
            $kembalian = round($dibayar - $total, 2);
        } else {
            $dibayar   = $total;
            $kembalian = 0.0;
        }

        $now   = now();
        $trxId = $this->codes->forTable('transactions');
        $kode  = $this->codes->receiptCode($branch->id, $branch->kode_cabang, $now);

        $trx = new Transaction([
            'kode_transaksi'   => $kode,
            'branch_id'        => $branch->id,
            'user_id'          => $user->id,
            'idempotency_key'  => $key,
            'subtotal'         => $total,
            'total'            => $total,
            'status'           => Transaction::STATUS_SELESAI,
            'waktu_transaksi'  => $now,
        ]);
        $trx->id = $trxId;
        $trx->save();

        foreach ($lines as [$p, $qty, $sub]) {
            TransactionDetail::create([
                'transaction_id' => $trxId,
                'product_id'     => $p->id,
                'nama_produk'    => $p->nama_produk,
                'harga_satuan'   => $p->harga,
                'jumlah'         => $qty,
                'subtotal'       => $sub,
            ]);
        }

        TransactionPayment::create([
            'transaction_id' => $trxId,
            'metode'         => $metode,
            'jumlah_bayar'   => $dibayar,
            'kembalian'      => $kembalian,
            'referensi'      => $referensi,
        ]);

        // Stok berkurang hanya jika semua langkah sukses; gagal => rollback total.
        foreach ($qtyByProduct as $pid => $qty) {
            $p = $products[$pid];

            // Produk batch: kurangi batch FIFO; produk standard: kurangi BranchStock langsung.
            if ($p->usesBatchStock()) {
                // Kurangi batch FIFO (inkl. validasi stok tersedia; ini melempar InsufficientStockException)
                $this->batch->deductBatchForSale($branch->id, $pid, $qty, $trxId, $user->id);

                // Kurangi jumlah_stok di BranchStock agar konsisten (stok jual berkurang)
                $this->stock->change(
                    $branch->id, $pid, -$qty,
                    StockMovement::PENJUALAN, $user->id,
                    "Penjualan {$kode}", 'transactions', $trxId,
                );
            } else {
                $this->stock->change(
                    $branch->id, $pid, -$qty,
                    StockMovement::PENJUALAN, $user->id,
                    "Penjualan {$kode}", 'transactions', $trxId,
                );
            }
        }

        $this->audit->log('transaction', 'created',
            "Transaksi {$kode} total Rp " . number_format($total, 0, ',', '.') . " ({$metode})",
            $trx, null,
            ['kode_transaksi' => $kode, 'total' => $total, 'metode' => $metode, 'jumlah_item' => count($lines)],
            $branch->id, $user);

        return $trx;
    }

    public function cancel(Transaction $transaction, User $user, string $reason): Transaction
    {
        return DB::transaction(function () use ($transaction, $user, $reason) {
            $t = Transaction::withoutGlobalScopes()->whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();

            if (! $user->isOwner() && $t->branch_id !== $user->branch_id) {
                abort(403);
            }
            if ($t->status !== Transaction::STATUS_SELESAI) {
                throw ValidationException::withMessages(['status' => 'Transaksi ini sudah dibatalkan.']);
            }

            foreach ($t->details()->orderBy('product_id')->get() as $d) {
                // Kembalikan stok ke BranchStock
                $this->stock->change(
                    $t->branch_id, $d->product_id, (int) $d->jumlah,
                    StockMovement::PEMBATALAN, $user->id,
                    "Pembatalan {$t->kode_transaksi}", 'transactions', $t->id,
                );

                // Kembalikan ke batch bila produk batch
                $product = $d->product;
                if ($product && $product->usesBatchStock()) {
                    $this->batch->restoreBatchOnCancel($t->branch_id, $d->product_id, (int) $d->jumlah);
                }
            }

            $t->update([
                'status'       => Transaction::STATUS_DIBATALKAN,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'alasan_batal' => $reason,
            ]);

            $this->audit->log('transaction', 'cancelled',
                "Membatalkan transaksi {$t->kode_transaksi}: {$reason}",
                $t, ['status' => Transaction::STATUS_SELESAI],
                ['status' => Transaction::STATUS_DIBATALKAN, 'alasan' => $reason],
                $t->branch_id, $user);

            return $t;
        }, 3);
    }

    private function findByKey(string $key, User $user): ?Transaction
    {
        $existing = Transaction::withoutGlobalScopes()->where('idempotency_key', $key)->first();

        if ($existing && $existing->user_id !== $user->id) {
            throw ValidationException::withMessages(['idempotency_key' => 'Kunci transaksi tidak valid.']);
        }

        return $existing;
    }
}
