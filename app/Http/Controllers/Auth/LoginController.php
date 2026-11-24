<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 60;

    public function show()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, ActivityLogService $audit)
    {
        $email = Str::lower($request->input('email'));
        $key = $email . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $ok = Auth::attempt(
            ['email' => $email, 'password' => $request->input('password'), 'is_active' => true],
            $request->boolean('remember')
        );

        if (! $ok) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $audit->log('auth', 'login_gagal', "Percobaan login gagal untuk {$email}");

            throw ValidationException::withMessages(['email' => 'Email atau password salah, atau akun nonaktif.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $audit->log('auth', 'login', 'Login berhasil');

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request, ActivityLogService $audit)
    {
        $audit->log('auth', 'logout', 'Logout');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
