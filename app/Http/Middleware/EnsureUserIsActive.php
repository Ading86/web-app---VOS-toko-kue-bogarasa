<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            $inactive = ! $user->is_active;

            // Kasir wajib punya cabang aktif.
            if (! $inactive && ! $user->isOwner()) {
                $branch = $user->branch;
                $inactive = ! $branch || $branch->status !== 'aktif';
            }

            if ($inactive) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Akun nonaktif atau cabang tidak aktif. Hubungi Owner.']);
            }
        }

        return $next($request);
    }
}
