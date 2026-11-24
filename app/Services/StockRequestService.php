<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockDistribution;
use App\Models\StockDistributionDetail;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\StockRequestDetail;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockRequestService
{
    public function __construct(
        private CodeGeneratorService $codes,
        private StockService $stock,
        private ActivityLogService $audit,
        private StockBatchService $batchService,
        private DistributionService $distService,
    ) {
    }

    /**
     * Cabang mengajukan permintaan stok baru.
     *
     * @param  array<int, array{product_id: string, qty: int|string}>  $items
     */
    public function create(User $user, array $items, ?string $catatan, ?string $targetBranchId = null): StockRequest
    {
        $branchId = $user->isOwner() ? ($targetBranchId ?: $user->branch_id) : $user->branch_id;

        if (! $branchId) {
            throw ValidationException::withMessages(['branch' => 'Akun tidak terhubung dengan cabang mana pun.']);
        }

        $branch = Branch::whereKey($branchId)->where('status', 'aktif')->first();
        if (! $branch) {
            throw ValidationException::withMessages(['branch' => 'Cabang pemohon tidak ditemukan atau tidak aktif.']);
        }

        // Normalisasi items
        $cleaned = [];
        foreach ($items as $item) {
            $pid = (string) ($item['product_id'] ?? '');
            $qty = (int) ($item['qty'] ?? 0);

            if ($pid === '' || $qty < 1) {
                continue;
            }

            $cleaned[$pid] = ($cleaned[$pid] ?? 0) + $qty;
        }

        if (empty($cleaned)) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu produk dengan jumlah permintaan minimal 1 pcs.']);
        }

        $productIds = array_keys($cleaned);
        $products = Product::whereIn('id_produk', $productIds)->where('status', 'aktif')->get()->keyBy('id_produk');

        if ($products->count() !== count($productIds)) {
            throw ValidationException::withMessages(['items' => 'Terdapat produk yang tidak aktif atau tidak ditemukan.']);
        }

        return DB::transaction(function () use ($user, $branchId, $cleaned, $catatan) {
            $request = new StockRequest([
                'branch_id'          => $branchId,
                'user_id'            => $user->id_user,
                'tanggal_permintaan' => Carbon::now('Asia/Jakarta')->toDateString(),
                'status'             => StockRequest::STATUS_DIAJUKAN,
                'catatan_cabang'     => $catatan !== null && trim($catatan) !== '' ? mb_substr(trim($catatan), 0, 255) : null,
            ]);
            $request->id = $this->codes->forTable('stock_requests');
            $request->save();

            foreach ($cleaned as $pid => $qty) {
                $detail = new StockRequestDetail([
                    'request_id'    => $request->id,
                    'product_id'    => $pid,
                    'jumlah_minta'  => $qty,
                    'jumlah_setuju' => $qty, // Nilai default usulan
                ]);
                $detail->id = $this->codes->forTable('stock_request_details');
                $detail->save();
            }

            $this->audit->log(
                'stock_request',
                'created',
                "Mengajukan permintaan stok {$request->id} untuk cabang " . Branch::whereKey($branchId)->value('nama_cabang'),
                $request,
                null,
                ['status' => $request->status, 'total_produk' => count($cleaned), 'catatan' => $request->catatan_cabang],
                $branchId,
                $user
            );

            return $request;
        }, 3);
    }

    /**
     * Owner memeriksa dan menyetujui permintaan stok (dapat mengubah kuantitas disetujui).
     *
     * @param  array<string, int|string>  $approvedQtys  [product_id => jumlah_setuju]
     */
    public function approve(StockRequest $request, User $owner, array $approvedQtys, ?string $catatan = null): StockRequest
    {
        $this->assertOwner($owner);

        return DB::transaction(function () use ($request, $owner, $approvedQtys, $catatan) {
            $req = StockRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if (! $req->isPending()) {
                throw ValidationException::withMessages(['status' => 'Hanya permintaan dengan status diajukan yang dapat disetujui.']);
            }

            $details = $req->details()->lockForUpdate()->get();
            $totalSetuju = 0;

            foreach ($details as $detail) {
                $raw = $approvedQtys[$detail->product_id] ?? $detail->jumlah_minta;
                $qtySetuju = max(0, (int) $raw);

                $detail->update([
                    'jumlah_setuju' => $qtySetuju,
                ]);

                $totalSetuju += $qtySetuju;
            }

            if ($totalSetuju < 1) {
                throw ValidationException::withMessages(['items' => 'Minimal harus ada 1 produk dengan jumlah yang disetujui lebih dari 0. Jika ditolak semua, gunakan aksi Tolak.']);
            }

            $before = $req->status;
            $req->update([
                'status'        => StockRequest::STATUS_DISETUJUI,
                'approved_by'   => $owner->id_user,
                'approved_at'   => now(),
                'catatan_owner' => $catatan !== null && trim($catatan) !== '' ? mb_substr(trim($catatan), 0, 255) : null,
            ]);

            $this->audit->log(
                'stock_request',
                'approved',
                "Menyetujui permintaan stok {$req->id} dengan total disetujui {$totalSetuju} pcs",
                $req,
                ['status' => $before],
                ['status' => $req->status, 'total_setuju' => $totalSetuju, 'catatan_owner' => $req->catatan_owner],
                $req->branch_id,
                $owner
            );

            return $req;
        }, 3);
    }

    /**
     * Owner menolak permintaan stok.
     */
    public function reject(StockRequest $request, User $owner, string $alasan): StockRequest
    {
        $this->assertOwner($owner);

        $alasanTrim = trim($alasan);
        if ($alasanTrim === '') {
            throw ValidationException::withMessages(['alasan' => 'Alasan penolakan wajib diisi.']);
        }

        return DB::transaction(function () use ($request, $owner, $alasanTrim) {
            $req = StockRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if (! $req->isPending()) {
                throw ValidationException::withMessages(['status' => 'Hanya permintaan dengan status diajukan yang dapat ditolak.']);
            }

            $before = $req->status;
            $req->update([
                'status'        => StockRequest::STATUS_DITOLAK,
                'approved_by'   => $owner->id_user,
                'approved_at'   => now(),
                'catatan_owner' => mb_substr($alasanTrim, 0, 255),
            ]);

            $this->audit->log(
                'stock_request',
                'rejected',
                "Menolak permintaan stok {$req->id}: {$alasanTrim}",
                $req,
                ['status' => $before],
                ['status' => $req->status, 'alasan' => $alasanTrim],
                $req->branch_id,
                $owner
            );

            return $req;
        }, 3);
    }

    /**
     * Petugas Warehouse memproses pengiriman stok ke cabang tujuan.
     */
    public function processDispatch(StockRequest $request, User $warehouseUser, ?string $catatan = null): StockDistribution
    {
        $this->assertWarehouseOrOwner($warehouseUser);

        $pusat = Branch::pusat();
        if (! $pusat || $pusat->status !== 'aktif') {
            throw ValidationException::withMessages(['pusat' => 'Cabang Pusat belum ditetapkan atau tidak aktif.']);
        }

        return DB::transaction(function () use ($request, $warehouseUser, $pusat, $catatan) {
            $req = StockRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if (! $req->isApproved()) {
                throw ValidationException::withMessages(['status' => 'Hanya permintaan yang telah disetujui Owner yang dapat diproses kirim oleh Warehouse.']);
            }

            $details = $req->details()->where('jumlah_setuju', '>', 0)->get();
            if ($details->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Tidak ada produk dengan jumlah disetujui untuk dikirim.']);
            }

            // Siapkan item untuk DistributionService
            $distItems = [];
            foreach ($details as $d) {
                $distItems[] = [
                    'product_id' => $d->product_id,
                    'branch_id'  => $req->branch_id,
                    'qty'        => $d->jumlah_setuju,
                ];
            }

            $catatanKirim = $catatan ?: "Pengiriman WMS untuk Permintaan {$req->id}";
            $idempotencyKey = (string) Str::uuid();

            // Panggil DistributionService untuk memotong stok gudang pusat & menambah stok perjalanan cabang
            $distribution = $this->distService->create($warehouseUser, $distItems, $catatanKirim, $idempotencyKey);

            // Hubungkan distribusi ke permintaan
            $distribution->update(['request_id' => $req->id]);

            // Update baris permintaan dengan jumlah_kirim
            foreach ($details as $d) {
                $d->update(['jumlah_kirim' => $d->jumlah_setuju]);
            }

            $before = $req->status;
            $req->update([
                'status'          => StockRequest::STATUS_DIKIRIM,
                'processed_by'    => $warehouseUser->id_user,
                'processed_at'    => now(),
                'distribution_id' => $distribution->id,
            ]);

            $this->audit->log(
                'stock_request',
                'dispatched',
                "Warehouse mengirimkan permintaan stok {$req->id} (Distribusi {$distribution->id}) ke " . Branch::whereKey($req->branch_id)->value('nama_cabang'),
                $req,
                ['status' => $before],
                ['status' => $req->status, 'distribution_id' => $distribution->id],
                $req->branch_id,
                $warehouseUser
            );

            return $distribution;
        }, 3);
    }

    /**
     * Cabang mengonfirmasi penerimaan kue.
     * Mengurangi stok perjalanan dan menambah stok jual cabang.
     * Wajib mencatat alasan jika jumlah yang diterima kurang dari yang dikirim.
     */
    public function receive(StockRequest $request, User $user, array $receivedQtys, ?string $catatan = null): StockRequest
    {
        if (! $user->isOwner() && $user->branch_id !== $request->branch_id) {
            throw new AuthorizationException('Anda hanya dapat mengonfirmasi penerimaan untuk cabang Anda sendiri.');
        }

        return DB::transaction(function () use ($request, $user, $receivedQtys, $catatan) {
            $req = StockRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($req->status !== StockRequest::STATUS_DIKIRIM) {
                throw new StockException("Permintaan {$req->id} berstatus '{$req->status}', hanya permintaan yang sedang dalam perjalanan ('dikirim') yang dapat diterima.");
            }

            $details = $req->details()->with('product')->lockForUpdate()->get();
            $note = trim((string) $catatan);
            $hasShortage = false;

            foreach ($details as $d) {
                $qty = $receivedQtys[$d->id] ?? $receivedQtys[$d->id_detail_permintaan] ?? $d->jumlah_kirim;
                $qty = (int) $qty;

                if ($qty < 0) {
                    throw ValidationException::withMessages(['received_qtys' => 'Jumlah diterima tidak boleh bernilai negatif.']);
                }
                if ($qty > $d->jumlah_kirim) {
                    throw ValidationException::withMessages(['received_qtys' => "Jumlah diterima untuk {$d->product->nama_produk} tidak boleh melebihi jumlah kirim ({$d->jumlah_kirim})."]);
                }
                if ($qty < $d->jumlah_kirim) {
                    $hasShortage = true;
                }
            }

            if ($hasShortage && $note === '') {
                throw ValidationException::withMessages(['catatan_selisih' => 'Alasan selisih wajib dicatat jika ada kue yang diterima kurang dari jumlah pengiriman.']);
            }

            // Map detail distribusi jika linked
            $distDetailsByProduct = collect();
            if ($req->distribution_id) {
                $distDetailsByProduct = StockDistributionDetail::where('distribution_id', $req->distribution_id)
                    ->where('branch_tujuan_id', $req->branch_id)
                    ->get()
                    ->keyBy('product_id');
            }

            foreach ($details as $d) {
                $qty = (int) ($receivedQtys[$d->id] ?? $receivedQtys[$d->id_detail_permintaan] ?? $d->jumlah_kirim);
                $d->update([
                    'jumlah_terima'   => $qty,
                    'catatan_selisih' => $qty < $d->jumlah_kirim ? $note : null,
                ]);

                // Kurangi stok perjalanan sebesar jumlah_kirim
                $this->stock->changeTrusted(
                    $req->branch_id,
                    $d->product_id,
                    -$d->jumlah_kirim,
                    StockMovement::DIST_TERIMA,
                    $user->id,
                    "Penerimaan WMS {$req->id}",
                    'stock_requests',
                    $req->id,
                    BranchStock::POOL_PERJALANAN
                );

                // Tambah stok jual sebesar jumlah_terima
                if ($qty > 0) {
                    $this->stock->changeTrusted(
                        $req->branch_id,
                        $d->product_id,
                        $qty,
                        StockMovement::DIST_TERIMA,
                        $user->id,
                        "Penerimaan WMS {$req->id}",
                        'stock_requests',
                        $req->id,
                        BranchStock::POOL_JUAL
                    );

                    // Catat batch di cabang jika menggunakan batch
                    if ($d->product?->usesBatchStock()) {
                        $this->batchService->receiveDistributedBatch(
                            $req->branch_id,
                            $d->product_id,
                            $qty,
                            null,
                            null,
                            $user->id,
                            $req->distribution_id ?: $req->id
                        );
                    }
                }

                // Update detail distribusi jika ada
                if (isset($distDetailsByProduct[$d->product_id])) {
                    $distLine = $distDetailsByProduct[$d->product_id];
                    $distLine->update([
                        'jumlah_diterima' => $qty,
                        'status'          => StockDistributionDetail::DITERIMA,
                    ]);
                }
            }

            // Jika distribusi terkait sudah tidak ada baris pending, set selesai
            if ($req->distribution_id) {
                $dist = StockDistribution::find($req->distribution_id);
                if ($dist) {
                    $pendingCount = $dist->details()->where('status', StockDistributionDetail::DIKIRIM)->count();
                    if ($pendingCount === 0) {
                        $dist->update(['status' => StockDistribution::SELESAI]);
                    }
                }
            }

            $before = $req->status;
            $req->update(['status' => StockRequest::STATUS_SELESAI]);

            $this->audit->log(
                'stock_request',
                'received',
                "Cabang mengonfirmasi penerimaan permintaan stok {$req->id}" . ($hasShortage ? " (dengan selisih: {$note})" : ''),
                $req,
                ['status' => $before],
                ['status' => $req->status],
                $req->branch_id,
                $user
            );

            return $req;
        }, 3);
    }

    private function assertOwner(User $user): void
    {
        if (! $user->isOwner()) {
            throw new AuthorizationException('Aksi ini hanya dapat dilakukan oleh Owner.');
        }
    }

    private function assertWarehouseOrOwner(User $user): void
    {
        $pusat = Branch::pusat();
        $isPusatStaff = $pusat && $user->branch_id === $pusat->id;

        if (! $user->isOwner() && ! $user->isWarehouse() && ! $isPusatStaff) {
            throw new AuthorizationException('Aksi ini hanya dapat dilakukan oleh Petugas Warehouse atau Owner.');
        }
    }
}
