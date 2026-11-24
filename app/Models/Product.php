<?php

namespace App\Models;

use App\Models\Concerns\HasCodeId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasCodeId, SoftDeletes;

    protected $primaryKey = 'id_produk';

    // ── Tipe kue ──────────────────────────────────────────────────────────
    public const TIPE_BASAH    = 'basah';    // stok berlaku 1 hari, wajib ditutup di akhir hari
    public const TIPE_KERING   = 'kering';   // stok bertahan ~5 hari, akumulatif
    public const TIPE_STANDARD = 'standard'; // tidak pakai sistem batch (stok biasa)

    public const TIPE_OPTIONS = [
        self::TIPE_BASAH    => 'Kue Basah (stok harian)',
        self::TIPE_KERING   => 'Kue Kering (stok ~5 hari)',
        self::TIPE_STANDARD => 'Standard (tanpa batas waktu)',
    ];

    /** Masa layak jual default per tipe (hari). */
    public const MASA_LAYAK_DEFAULT = [
        self::TIPE_BASAH    => 1,
        self::TIPE_KERING   => 5,
        self::TIPE_STANDARD => 0,
    ];

    protected $fillable = ['kode_produk', 'category_id', 'nama_produk', 'deskripsi', 'harga', 'gambar', 'status', 'tipe_kue', 'masa_layak_jual'];

    protected $casts = [
        'harga'          => 'decimal:2',
        'masa_layak_jual' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->kode_produk)) {
                $product->kode_produk = $product->getKey();
            }
            // Set masa layak jual default sesuai tipe bila belum diisi.
            if (empty($product->masa_layak_jual) && $product->tipe_kue !== self::TIPE_STANDARD) {
                $product->masa_layak_jual = self::MASA_LAYAK_DEFAULT[$product->tipe_kue] ?? 1;
            }
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Apakah produk ini termasuk kategori atau bertipe kue basah. */
    public function isBasah(): bool
    {
        if ($this->tipe_kue === self::TIPE_BASAH) {
            return true;
        }

        if ($this->tipe_kue === self::TIPE_STANDARD || $this->tipe_kue === self::TIPE_KERING) {
            return false;
        }

        $catName = strtolower($this->category?->nama_kategori ?? '');

        return str_contains($catName, 'basah');
    }

    /** Apakah produk ini termasuk kategori atau bertipe kue kering. */
    public function isKering(): bool
    {
        if ($this->tipe_kue === self::TIPE_KERING) {
            return true;
        }

        if ($this->tipe_kue === self::TIPE_STANDARD || $this->tipe_kue === self::TIPE_BASAH) {
            return false;
        }

        $catName = strtolower($this->category?->nama_kategori ?? '');

        return str_contains($catName, 'kering');
    }

    /** Apakah produk ini menggunakan sistem batch (kue basah/kering). */
    public function usesBatchStock(): bool
    {
        return $this->isBasah() || $this->isKering();
    }

    // ── Relations ─────────────────────────────────────────────────────────

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function stocks()
    {
        return $this->hasMany(BranchStock::class, 'product_id');
    }

    public function batches()
    {
        return $this->hasMany(StockBatch::class, 'product_id');
    }
}
