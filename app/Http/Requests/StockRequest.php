<?php

namespace App\Http\Requests;

use App\Models\BranchStock;
use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $jenis = $this->input('jenis');
        $needsNote = in_array($jenis, [StockMovement::PENYESUAIAN, StockMovement::KELUAR], true);

        return [
            'branch_id' => ['nullable', Rule::exists('branches', 'id_cabang')],
            'product_id' => ['required', Rule::exists('products', 'id_produk')->whereNull('deleted_at')],
            'jenis' => ['required', Rule::in([StockMovement::MASUK, StockMovement::KELUAR, StockMovement::PENYESUAIAN, 'minimum'])],
            // Kantong stok yang diubah (stok keluar/koreksi). Gudang hanya ada di Cabang Pusat (diperiksa di controller).
            'lokasi' => ['nullable', Rule::in([BranchStock::POOL_JUAL, BranchStock::POOL_GUDANG])],
            'jumlah' => ['required', 'integer', 'max:' . BranchStock::MAX_STOK, in_array($jenis, [StockMovement::MASUK, StockMovement::KELUAR], true) ? 'min:1' : 'min:0'],
            'keterangan' => [$needsNote ? 'required' : 'nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return ['keterangan.required' => 'Keterangan wajib diisi untuk koreksi dan stok keluar.'];
    }
}
