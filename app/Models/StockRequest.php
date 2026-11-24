<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockRequest extends Model
{
    use HasCodeId;

    protected $primaryKey = 'id_permintaan';

    public const STATUS_DIAJUKAN  = 'diajukan';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK   = 'ditolak';
    public const STATUS_DIKIRIM   = 'dikirim';
    public const STATUS_SELESAI   = 'selesai';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $fillable = [
        'branch_id',
        'user_id',
        'tanggal_permintaan',
        'status',
        'catatan_cabang',
        'approved_by',
        'approved_at',
        'catatan_owner',
        'processed_by',
        'processed_at',
        'distribution_id',
    ];

    protected $casts = [
        'tanggal_permintaan' => 'date',
        'approved_at'        => 'datetime',
        'processed_at'       => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function distribution()
    {
        return $this->belongsTo(StockDistribution::class, 'distribution_id');
    }

    public function details()
    {
        return $this->hasMany(StockRequestDetail::class, 'request_id');
    }

    /**
     * Owner & Warehouse dapat melihat seluruh permintaan;
     * Akun cabang hanya dapat melihat permintaan cabangnya sendiri.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isOwner() || $user->isWarehouse()) {
            return $query;
        }

        if (! $user->branch_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('branch_id', $user->branch_id);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_DIAJUKAN;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_DISETUJUI;
    }

    public function isDispatched(): bool
    {
        return $this->status === self::STATUS_DIKIRIM;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_SELESAI;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_DITOLAK;
    }
}
