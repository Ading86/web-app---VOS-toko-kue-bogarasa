<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Satu batch = satu lot produksi atau penerimaan stok untuk satu produk di satu cabang.
 *
 * Aturan bisnis revisi:
 *  - Zona waktu: Asia/Jakarta untuk menentukan pergantian tanggal operasional.
 *  - Kue basah  (tipe_kue = 'basah')  : masa berlaku stok maksimal 1 hari (hanya tanggal produksi).
 *                 Saat tanggal berganti, stok hari sebelumnya otomatis TIDAK tersedia untuk penjualan.
 *                 Kue basah yang tersisa langsung dibagikan (tanpa penutupan manual / pembagian).
 *  - Kue kering (tipe_kue = 'kering') : masa layak jual maksimal 5 hari sejak produksi/penerimaan.
 *                 Stok lama yang masih layak jual tetap tersedia saat tanggal berganti dan diakumulasi
 *                 secara terpisah per batch (FIFO).
 *  - Standard   (tipe_kue = 'standard'): tidak menggunakan sistem batch; dikelola oleh BranchStock biasa.
 */
class StockBatch extends Model
{
    use BelongsToBranch, HasCodeId;

    protected $primaryKey = 'id_batch';

    public const STATUS_AKTIF       = 'aktif';
    public const STATUS_KEDALUWARSA = 'kedaluwarsa';

    public const TIMEZONE = 'Asia/Jakarta';

    protected $fillable = [
        'branch_id', 'product_id', 'user_id',
        'tanggal_produksi', 'tanggal_kedaluwarsa',
        'jumlah_masuk', 'jumlah_terjual', 'jumlah_dibagikan', 'jumlah_kadaluwarsa',
        'status', 'keterangan', 'movement_id',
    ];

    protected $casts = [
        'tanggal_produksi'   => 'date',
        'tanggal_kedaluwarsa' => 'date',
        'jumlah_masuk'       => 'integer',
        'jumlah_terjual'     => 'integer',
        'jumlah_dibagikan'   => 'integer',
        'jumlah_kadaluwarsa' => 'integer',
    ];

    /** Tanggal hari ini di zona waktu Asia/Jakarta. */
    public static function todayJakarta(): Carbon
    {
        return Carbon::now(self::TIMEZONE)->startOfDay();
    }

    /** Tanggal hari ini dalam format Y-m-d di zona waktu Asia/Jakarta. */
    public static function todayString(): string
    {
        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    /** Sisa stok fisik yang belum terjual pada batch ini. */
    public function getJumlahSisaAttribute(): int
    {
        return max(0,
            $this->jumlah_masuk
            - $this->jumlah_terjual
            - $this->jumlah_kadaluwarsa
            - $this->jumlah_dibagikan
        );
    }

    /** Apakah batch ini masih dalam masa layak jual pada tanggal tertentu (default: hari ini di Asia/Jakarta). */
    public function isLayakJual(?Carbon $on = null): bool
    {
        $date = $on ?? self::todayJakarta();

        return $this->status === self::STATUS_AKTIF
            && $this->tanggal_kedaluwarsa->greaterThanOrEqualTo($date->startOfDay());
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    /** Batch yang masih aktif dan belum kedaluwarsa pada tanggal tertentu (default: hari ini di Asia/Jakarta). */
    public function scopeAvailable(Builder $q, ?Carbon $on = null): Builder
    {
        $date = $on ? $on->toDateString() : self::todayString();

        return $q->where('status', self::STATUS_AKTIF)
                 ->whereDate('tanggal_kedaluwarsa', '>=', $date)
                 ->whereRaw('(jumlah_masuk - jumlah_terjual - jumlah_kadaluwarsa - jumlah_dibagikan) > 0');
    }

    // ── Relations ─────────────────────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movement()
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }
}
