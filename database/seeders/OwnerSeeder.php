<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('OWNER_EMAIL', 'owner@bogarasa.test');

        if (User::where('email', $email)->exists()) {
            return;
        }

        // Tidak ada kredensial di repository: password dari .env, atau acak (ditampilkan sekali).
        $password = env('OWNER_PASSWORD') ?: Str::password(16);

        $owner = User::create([
            'branch_id' => null,
            'name' => 'Owner Bogarasa',
            'email' => $email,
            'password' => $password,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $owner->assignRole('Owner');

        $this->command?->warn("Akun Owner dibuat: {$email}");
        if (! env('OWNER_PASSWORD')) {
            $this->command?->warn("Password sementara (catat sekarang, tidak ditampilkan lagi): {$password}");
        }
        $this->command?->warn('Password wajib diganti saat login pertama.');
    }
}
