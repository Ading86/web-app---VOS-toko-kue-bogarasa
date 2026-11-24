<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Kue Basah', 'Kue Kering'] as $nama) {
            Category::firstOrCreate(['nama_kategori' => $nama], ['status' => 'aktif']);
        }
    }
}
