<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu kegiatan distribusi dari Cabang Pusat. Tidak memakai BelongsToBranch karena satu distribusi
 * menyentuh banyak cabang; batas akses diatur scopeVisibleTo() dan DistributionService.
 */
class StockDistribution extends Model
{
    use HasCodeId;

    protected $primaryKey = 'id_distribusi';

    public const DIKIRIM = 'dikirim';         // masih ada baris yang belum dikonfirmasi
    public const SELESAI = 'selesai';         // semua baris sudah dikonfirmasi/dibatalkan, minimal satu diterima
    public const DIBATALKAN = 'dibatalkan';   // seluruh baris dibatalkan

    protected $fillable = [
        'request_id', 'branch_asal_id', 'user_id', 'idempotency_key', 'status', 'waktu_kirim', 'catatan',
        'cancelled_by', 'cancelled_at', 'alasan_batal',
    ];

    protected $casts = ['waktu_kirim' => 'datetime', 'cancelled_at' => 'datetime'];

    public function sourceBranch()
    {
        return $this->belongsTo(Branch::class, 'branch_asal_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function stockRequest()
    {
        return $this->belongsTo(StockRequest::class, 'request_id');
    }

    public function details()
    {
        return $this->hasMany(StockDistributionDetail::class, 'distribution_id');
    }

    /** Owner melihat semua; akun cabang hanya distribusi yang dikirim dari cabangnya atau menuju cabangnya. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        $branchId = $user->branch_id;

        if (! $branchId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($branchId) {
            $q->where('branch_asal_id', $branchId)
                ->orWhereHas('details', fn (Builder $d) => $d->where('branch_tujuan_id', $branchId));
        });
    }

    /** Pengirim (Owner atau staf Cabang Pusat dengan hak create) boleh melihat semua baris; cabang tujuan hanya barisnya sendiri. */
    public function isPrivilegedFor(User $user): bool
    {
        return $user->isOwner() || ($user->can('distributions.create') && $user->branch_id !== null && $user->branch_id === $this->branch_asal_id);
    }
}
