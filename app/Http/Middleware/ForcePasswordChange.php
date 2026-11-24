<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->must_change_password) {
            return redirect()->route('password.change')
                ->with('error', 'Anda wajib mengganti password sebelum melanjutkan.');
        }

        return $next($request);
    }
}
