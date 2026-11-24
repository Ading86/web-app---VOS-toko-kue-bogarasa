<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tipe = $this->input('tipe_kue') ?: Product::TIPE_STANDARD;
        $masaLayak = $this->input('masa_layak_jual');

        if ($tipe === Product::TIPE_BASAH) {
            $masaLayak = 1; // Kue basah mutlak 1 hari
        } elseif (! $masaLayak) {
            $masaLayak = Product::MASA_LAYAK_DEFAULT[$tipe] ?? 1;
        }

        $this->merge([
            'tipe_kue'        => $tipe,
            'masa_layak_jual' => (int) $masaLayak,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('product')?->getKey();

        return [
            'kode_produk'     => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-_.]+$/', Rule::unique('products', 'kode_produk')->ignore($id, 'id_produk')],
            'category_id'     => ['required', Rule::exists('categories', 'id_kategori')],
            'nama_produk'     => ['required', 'string', 'max:100'],
            'deskripsi'       => ['nullable', 'string', 'max:500'],
            'harga'           => ['required', 'numeric', 'min:0', 'max:99999999'],
            'gambar'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'hapus_gambar'    => ['sometimes', 'boolean'],
            'status'          => ['required', Rule::in(['aktif', 'nonaktif'])],
            // ── Kolom baru untuk manajemen batch (backward-compatible, defaults to standard) ──
            'tipe_kue'        => ['nullable', Rule::in(array_keys(Product::TIPE_OPTIONS))],
            'masa_layak_jual' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id'     => 'kategori',
            'nama_produk'     => 'nama produk',
            'kode_produk'     => 'kode produk',
            'tipe_kue'        => 'tipe kue',
            'masa_layak_jual' => 'masa layak jual',
        ];
    }
}
