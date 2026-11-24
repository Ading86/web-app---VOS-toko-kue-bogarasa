<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Nomor telepon disimpan sebagai digit saja (maks 13 digit): spasi, tanda hubung, kurung dibuang; +62/62 menjadi 0. */
    protected function prepareForValidation(): void
    {
        $phone = $this->input('nomor_telepon');

        if (is_string($phone)) {
            $digits = preg_replace('/[^0-9+]/', '', $phone);

            if (str_starts_with($digits, '+62')) {
                $digits = '0' . substr($digits, 3);
            } elseif (str_starts_with($digits, '62')) {
                $digits = '0' . substr($digits, 2);
            }

            $digits = str_replace('+', '', $digits);

            $this->merge(['nomor_telepon' => $digits === '' ? null : $digits]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('branch')?->getKey();

        return [
            // Saat tambah cabang boleh kosong: kode otomatis = ID (mis. BRN05). Saat ubah wajib diisi.
            'kode_cabang' => [$id ? 'required' : 'nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('branches', 'kode_cabang')->ignore($id)],
            'nama_cabang' => ['required', 'string', 'max:60'],
            'alamat' => ['nullable', 'string', 'max:150'],
            'nomor_telepon' => ['nullable', 'string', 'regex:/^0[0-9]{7,12}$/'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            // is_pusat sengaja TIDAK bisa diubah lewat form: penetapan Cabang Pusat dilakukan lewat seeder/database.
        ];
    }

    public function messages(): array
    {
        return [
            'kode_cabang.regex' => 'Kode cabang hanya boleh huruf, angka, dan tanda minus.',
            'nomor_telepon.regex' => 'Nomor telepon harus 8 sampai 13 digit angka dan diawali 0 (boleh ditulis +62 atau 62).',
        ];
    }
}
