<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;

class StockReceipt extends Model
{
    use BelongsToBranch, HasCodeId;

    protected $primaryKey = 'id_penerimaan';

    protected $fillable = [
        'nomor_penerimaan',
        'branch_id',
        'user_id',
        'tanggal_penerimaan',
        'catatan',
        'total_item',
        'total_kuantitas',
    ];

    protected $casts = [
        'tanggal_penerimaan' => 'date',
        'total_item'         => 'integer',
        'total_kuantitas'    => 'integer',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function details()
    {
        return $this->hasMany(StockReceiptDetail::class, 'receipt_id');
    }
}
