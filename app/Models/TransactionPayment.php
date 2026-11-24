<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;

class TransactionPayment extends Model
{
    use HasCodeId;

    protected $primaryKey = 'id_pembayaran';

    public const METODE = ['tunai', 'transfer', 'qris'];

    protected $fillable = ['transaction_id', 'metode', 'jumlah_bayar', 'kembalian', 'referensi'];

    protected $casts = ['jumlah_bayar' => 'decimal:2', 'kembalian' => 'decimal:2'];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
