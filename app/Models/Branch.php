<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasCodeId;

    protected $primaryKey = 'id_cabang';

    protected $fillable = ['kode_cabang', 'nama_cabang', 'alamat', 'nomor_telepon', 'is_pusat', 'status'];

    protected $casts = ['is_pusat' => 'boolean'];

    protected static function booted(): void
    {
        // kode_cabang awalnya sama dengan id, tetapi boleh diubah Owner (id tidak pernah berubah).
        static::creating(function (Branch $branch) {
            if (empty($branch->kode_cabang)) {
                $branch->kode_cabang = $branch->getKey();
            }
        });
    }

    /** Cabang pusat distribusi (hanya satu; dijaga unique index pusat_unik). */
    public static function pusat(): ?self
    {
        return static::query()->where('is_pusat', true)->first();
    }

    public function users()
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function stocks()
    {
        return $this->hasMany(BranchStock::class, 'branch_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'branch_id');
    }

    /** Distribusi yang dikirim dari cabang ini (bermakna untuk Cabang Pusat). */
    public function distributionsSent()
    {
        return $this->hasMany(StockDistribution::class, 'branch_asal_id');
    }

    /** Baris distribusi yang ditujukan ke cabang ini. */
    public function distributionLines()
    {
        return $this->hasMany(StockDistributionDetail::class, 'branch_tujuan_id');
    }
}
