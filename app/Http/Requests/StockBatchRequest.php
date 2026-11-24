<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id'         => ['nullable', Rule::exists('branches', 'id_cabang')],
            'product_id'        => [
                'required',
                Rule::exists('products', 'id_produk')->whereNull('deleted_at')->whereIn('tipe_kue', [Product::TIPE_BASAH, Product::TIPE_KERING]),
            ],
            'jumlah'            => ['required', 'integer', 'min:1', 'max:1000000'],
            'tanggal_produksi'  => ['required', 'date', 'before_or_equal:today'],
            'keterangan'        => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.exists'              => 'Produk tidak ditemukan atau bukan bertipe kue basah/kering.',
            'tanggal_produksi.before_or_equal' => 'Tanggal produksi tidak boleh di masa depan.',
        ];
    }
}
