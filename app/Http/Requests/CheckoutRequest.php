<?php

namespace App\Http\Requests;

use App\Models\TransactionPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'string', 'max:7'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:9999'],
            'metode' => ['required', Rule::in(TransactionPayment::METODE)],
            'jumlah_bayar' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'referensi' => ['nullable', 'string', 'max:50'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return ['items.required' => 'Keranjang masih kosong.', 'items.min' => 'Keranjang masih kosong.'];
    }
}
