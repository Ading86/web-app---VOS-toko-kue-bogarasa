<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use BelongsToBranch, HasCodeId;

    protected $primaryKey = 'id_pergerakan';

    public const UPDATED_AT = null; // riwayat bersifat immutable

    public const MASUK        = 'stok_masuk';
    public const PENYESUAIAN  = 'penyesuaian';
    public const KELUAR       = 'stok_keluar';
    public const PENJUALAN    = 'penjualan';
    public const PEMBATALAN   = 'pembatalan';
    public const DIST_KELUAR  = 'distribusi_keluar';
    public const DIST_JALAN   = 'distribusi_jalan';
    public const DIST_TERIMA  = 'distribusi_terima';
    public const DIST_BATAL   = 'distribusi_batal';
    // ── Jenis baru untuk manajemen batch ───────────────────────────────
    public const BATCH_MASUK      = 'batch_masuk';      // stok masuk melalui sistem batch
    public const PEMBAGIAN        = 'pembagian';         // sisa kue basah dibagikan (bukan penjualan)
    public const KEDALUWARSA      = 'kedaluwarsa';       // stok kering dibuang karena expired

    public const JENIS_LABELS = [
        self::MASUK          => 'Stok masuk',
        self::PENYESUAIAN    => 'Penyesuaian',
        self::KELUAR         => 'Stok keluar',
        self::PENJUALAN      => 'Penjualan',
        self::PEMBATALAN     => 'Pembatalan transaksi',
        self::DIST_KELUAR    => 'Distribusi keluar',
        self::DIST_JALAN     => 'Distribusi dalam perjalanan',
        self::DIST_TERIMA    => 'Distribusi diterima',
        self::DIST_BATAL     => 'Distribusi dibatalkan',
        self::BATCH_MASUK    => 'Stok masuk (batch)',
        self::PEMBAGIAN      => 'Pembagian (non-penjualan)',
        self::KEDALUWARSA    => 'Kedaluwarsa / dibuang',
    ];

    protected $fillable = [
        'branch_id', 'product_id', 'user_id', 'lokasi_stok', 'jenis_pergerakan', 'jumlah',
        'stok_sebelum', 'stok_sesudah', 'keterangan', 'reference_type', 'reference_id',
    ];

    protected $casts = ['jumlah' => 'integer', 'stok_sebelum' => 'integer', 'stok_sesudah' => 'integer'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
