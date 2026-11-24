<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;

class StockReceiptDetail extends Model
{
    use HasCodeId;

    protected $primaryKey = 'id_detail_penerimaan';

    protected $fillable = [
        'receipt_id',
        'product_id',
        'jumlah',
        'masa_layak_jual',
        'tanggal_kedaluwarsa',
        'batch_id',
    ];

    protected $casts = [
        'jumlah'              => 'integer',
        'masa_layak_jual'     => 'integer',
        'tanggal_kedaluwarsa' => 'date',
    ];

    public function receipt()
    {
        return $this->belongsTo(StockReceipt::class, 'receipt_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function batch()
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }
}
