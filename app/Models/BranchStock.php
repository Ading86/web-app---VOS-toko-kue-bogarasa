<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;

class BranchStock extends Model
{
    use BelongsToBranch, HasCodeId;

    protected $primaryKey = 'id_stok';

    /** Tiga kantong stok per cabang per produk. */
    public const POOL_JUAL = 'jual';             // stok siap jual (POS)
    public const POOL_GUDANG = 'gudang';         // khusus Cabang Pusat: persediaan induk sumber distribusi
    public const POOL_PERJALANAN = 'perjalanan'; // kiriman menuju cabang ini yang belum dikonfirmasi

    public const POOL_COLUMNS = [
        self::POOL_JUAL => 'jumlah_stok',
        self::POOL_GUDANG => 'stok_gudang',
        self::POOL_PERJALANAN => 'stok_perjalanan',
    ];

    /** Batas atas tiap kantong (kolom MEDIUMINT UNSIGNED maks 16.777.215). */
    public const MAX_STOK = 1000000;

    protected $fillable = ['branch_id', 'product_id', 'jumlah_stok', 'stok_gudang', 'stok_perjalanan', 'stok_minimum'];

    protected $casts = [
        'jumlah_stok' => 'integer', 'stok_gudang' => 'integer',
        'stok_perjalanan' => 'integer', 'stok_minimum' => 'integer',
    ];

    /**
     * Buat baris stok 0 untuk satu pasangan cabang+produk, tanpa event model.
     * Hook BelongsToBranch memaksa branch_id = cabang akun yang login, sehingga tidak cocok
     * untuk pembuatan baris lintas cabang. Otorisasi sudah dilakukan route/permission pemanggil.
     */
    public static function createZeroRow(string $branchId, string $productId): self
    {
        $row = new static([
            'branch_id' => $branchId, 'product_id' => $productId,
            'jumlah_stok' => 0, 'stok_gudang' => 0, 'stok_perjalanan' => 0, 'stok_minimum' => 0,
        ]);
        $row->id_stok = app(\App\Services\CodeGeneratorService::class)->forTable('branch_stocks');
        static::withoutEvents(fn () => $row->save());

        return $row;
    }
    public static function columnFor(string $pool): string
    {
        return self::POOL_COLUMNS[$pool] ?? throw new \InvalidArgumentException("Kantong stok '{$pool}' tidak dikenal.");
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    /** Menipis dinilai dari stok siap jual saja. */
    public function isLow(): bool
    {
        return $this->jumlah_stok <= $this->stok_minimum;
    }
}
