<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    use HasCodeId;

    protected $primaryKey = 'id_detail';

    protected $fillable = ['transaction_id', 'product_id', 'nama_produk', 'harga_satuan', 'jumlah', 'subtotal'];

    protected $casts = ['harga_satuan' => 'decimal:2', 'subtotal' => 'decimal:2', 'jumlah' => 'integer'];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }
}
