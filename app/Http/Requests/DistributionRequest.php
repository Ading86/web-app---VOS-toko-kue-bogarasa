<?php

namespace App\Http\Requests;

use App\Models\BranchStock;
use Illuminate\Foundation\Http\FormRequest;

class DistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi lewat middleware route (can:distributions.create) dan DistributionService
    }

    public function rules(): array
    {
        return [
            // Matriks isian: qty[ID_PRODUK][ID_CABANG] = jumlah kirim (kosong/0 = tidak dikirim).
            'qty' => ['required', 'array'],
            'qty.*' => ['array'],
            'qty.*.*' => ['nullable', 'integer', 'min:0', 'max:' . BranchStock::MAX_STOK],
            'catatan' => ['nullable', 'string', 'max:150'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** Ratakan matriks menjadi daftar baris [product_id, branch_id, qty] yang jumlahnya > 0. */
    public function items(): array
    {
        $items = [];

        foreach ((array) $this->input('qty', []) as $productId => $perBranch) {
            foreach ((array) $perBranch as $branchId => $qty) {
                if ((int) $qty > 0) {
                    $items[] = ['product_id' => (string) $productId, 'branch_id' => (string) $branchId, 'qty' => (int) $qty];
                }
            }
        }

        return $items;
    }

    public function messages(): array
    {
        return [
            'qty.required' => 'Isi jumlah kirim untuk minimal satu produk dan satu cabang.',
            'qty.*.*.min' => 'Jumlah distribusi tidak boleh kurang dari 0.',
            'qty.*.*.max' => 'Jumlah distribusi melebihi batas maksimum.',
            'idempotency_key.required' => 'Kunci pengiriman (idempotency key) wajib ada. Silakan muat ulang halaman jika error berulang.',
            'idempotency_key.uuid' => 'Format kunci pengiriman tidak valid.',
        ];
    }
}
