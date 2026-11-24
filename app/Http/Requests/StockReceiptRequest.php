<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal_penerimaan' => ['required', 'date', 'before_or_equal:today'],
            'catatan'            => ['nullable', 'string', 'max:250'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id_produk')->whereNull('deleted_at')],
            'items.*.jumlah'     => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_penerimaan.required'        => 'Tanggal penerimaan wajib diisi.',
            'tanggal_penerimaan.before_or_equal' => 'Tanggal penerimaan tidak boleh di masa depan.',
            'items.required'                     => 'Masukkan minimal satu produk.',
            'items.min'                          => 'Masukkan minimal satu produk.',
            'items.*.product_id.required'        => 'Pilih produk pada setiap baris penerimaan.',
            'items.*.product_id.exists'          => 'Produk yang dipilih tidak valid atau sudah dihapus.',
            'items.*.jumlah.required'            => 'Jumlah stok masuk wajib diisi.',
            'items.*.jumlah.min'                 => 'Jumlah stok masuk minimal 1 pcs.',
        ];
    }
}
