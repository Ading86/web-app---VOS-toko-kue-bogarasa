<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

// Jika project Anda sudah punya isi lain di sini, cukup tambahkan baris Gate::before ke boot() yang ada.
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Carbon::setLocale('id');
        date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));

        // Owner = akses penuh ke semua ability (termasuk permission yang baru ditambahkan di masa depan).
        Gate::before(fn ($user, $ability) => $user->hasRole('Owner') ? true : null);
    }
}
