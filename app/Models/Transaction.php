<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use BelongsToBranch, HasCodeId;

    protected $primaryKey = 'id_transaksi';

    public const STATUS_SELESAI = 'selesai';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $fillable = [
        'kode_transaksi', 'branch_id', 'user_id', 'idempotency_key', 'subtotal', 'total',
        'status', 'waktu_transaksi', 'cancelled_by', 'cancelled_at', 'alasan_batal',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'waktu_transaksi' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function cashier()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function details()
    {
        return $this->hasMany(TransactionDetail::class, 'transaction_id');
    }

    public function payments()
    {
        return $this->hasMany(TransactionPayment::class, 'transaction_id');
    }
}
