<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;

class StockDistributionDetail extends Model
{
    use HasCodeId;

    protected $primaryKey = 'id_detail_distribusi';

    public const DIKIRIM = 'dikirim';         // dalam perjalanan, menunggu konfirmasi cabang tujuan
    public const DITERIMA = 'diterima';
    public const DIBATALKAN = 'dibatalkan';

    protected $fillable = [
        'distribution_id', 'product_id', 'branch_tujuan_id', 'jumlah_dikirim',
        'tanggal_produksi', 'tanggal_kedaluwarsa',
        'jumlah_diterima', 'status', 'diterima_oleh', 'waktu_diterima', 'catatan_selisih',
    ];

    protected $casts = [
        'jumlah_dikirim'     => 'integer',
        'jumlah_diterima'    => 'integer',
        'tanggal_produksi'   => 'date',
        'tanggal_kedaluwarsa' => 'date',
        'waktu_diterima'     => 'datetime',
    ];

    public function distribution()
    {
        return $this->belongsTo(StockDistribution::class, 'distribution_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function destination()
    {
        return $this->belongsTo(Branch::class, 'branch_tujuan_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'diterima_oleh');
    }

    /** Selisih kirim vs terima; null bila belum dikonfirmasi. Selisih tidak masuk stok cabang mana pun. */
    public function getSelisihAttribute(): ?int
    {
        return $this->jumlah_diterima === null ? null : $this->jumlah_dikirim - $this->jumlah_diterima;
    }
}
