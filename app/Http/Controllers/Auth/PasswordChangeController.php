<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordChangeController extends Controller
{
    public function edit()
    {
        return view('auth.change-password');
    }

    public function update(Request $request, ActivityLogService $audit)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'Password saat ini salah.',
            'password.different' => 'Password baru harus berbeda dari password lama.',
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        $audit->log('auth', 'password_diubah', 'Pengguna mengganti password sendiri', $user);

        return redirect('/dashboard')->with('success', 'Password berhasil diganti.');
    }
}
