<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;

class StockRequestDetail extends Model
{
    use HasCodeId;

    protected $primaryKey = 'id_detail_permintaan';

    protected $fillable = [
        'request_id',
        'product_id',
        'jumlah_minta',
        'jumlah_setuju',
        'jumlah_kirim',
        'jumlah_terima',
        'catatan_selisih',
    ];

    protected $casts = [
        'jumlah_minta'  => 'integer',
        'jumlah_setuju' => 'integer',
        'jumlah_kirim'  => 'integer',
        'jumlah_terima' => 'integer',
    ];

    public function request()
    {
        return $this->belongsTo(StockRequest::class, 'request_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    /**
     * Selisih antara jumlah dikirim vs diterima (positif jika kurang diterima).
     */
    public function getSelisihAttribute(): ?int
    {
        if ($this->jumlah_terima === null || $this->jumlah_kirim === null) {
            return null;
        }

        return $this->jumlah_kirim - $this->jumlah_terima;
    }
}
