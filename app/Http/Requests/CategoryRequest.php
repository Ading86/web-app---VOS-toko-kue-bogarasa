<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('category')?->getKey();

        return [
            'nama_kategori' => ['required', 'string', 'max:60', Rule::unique('categories', 'nama_kategori')->ignore($id)],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ];
    }
}
