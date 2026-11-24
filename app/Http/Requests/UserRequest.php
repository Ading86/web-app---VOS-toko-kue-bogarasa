<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi lewat middleware route (can:users.manage)
    }

    public function rules(): array
    {
        $id = $this->route('user')?->getKey();

        return [
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($id, 'id_user')],
            'password' => [$id ? 'nullable' : 'required', 'string', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'branch_id' => [
                Rule::requiredIf(fn () => $this->input('role') !== 'Owner'),
                'nullable',
                Rule::exists('branches', 'id_cabang'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama', 'branch_id' => 'cabang', 'role' => 'role'];
    }

    public function messages(): array
    {
        return ['branch_id.required' => 'Akun selain Owner wajib ditugaskan ke satu cabang.'];
    }
}
