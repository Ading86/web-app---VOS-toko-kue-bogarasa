<?php

namespace App\Http\Requests;

use App\Models\BranchStock;
use Illuminate\Foundation\Http\FormRequest;

class StockRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_requests.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string', 'exists:products,id_produk'],
            'items.*.qty'        => ['required', 'integer', 'min:1', 'max:' . BranchStock::MAX_STOK],
            'catatan'            => ['nullable', 'string', 'max:255'],
            'branch_id'          => ['nullable', 'string', 'exists:branches,id_cabang'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'              => 'Pilih minimal satu produk kue yang ingin diajukan.',
            'items.min'                   => 'Pilih minimal satu produk kue yang ingin diajukan.',
            'items.*.product_id.required' => 'Produk kue wajib dipilih.',
            'items.*.product_id.exists'   => 'Produk kue yang dipilih tidak valid.',
            'items.*.qty.required'        => 'Jumlah permintaan wajib diisi.',
            'items.*.qty.min'             => 'Jumlah permintaan minimal 1 pcs.',
            'items.*.qty.max'             => 'Jumlah permintaan melebihi batas wajar.',
        ];
    }
}
