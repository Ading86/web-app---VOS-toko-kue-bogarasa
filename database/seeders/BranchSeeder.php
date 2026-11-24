<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        if (Branch::count() > 0) {
            return; // aman dijalankan ulang; tidak menimpa data yang sudah diubah Owner
        }

        // Cabang pertama (BRN01) = Cabang Pusat distribusi. Nama/alamat/telepon diubah Owner di menu Cabang.
        Branch::create([
            'nama_cabang' => 'Bogarasa Pusat',
            'alamat' => 'Alamat pusat (placeholder, ubah di menu Cabang)',
            'nomor_telepon' => null,
            'is_pusat' => true,
            'status' => 'aktif',
        ]);

        foreach (range(2, 4) as $i) {
            Branch::create([
                'nama_cabang' => "Bogarasa Cabang {$i}",
                'alamat' => "Alamat cabang {$i} (placeholder, ubah di menu Cabang)",
                'nomor_telepon' => null,
                'is_pusat' => false,
                'status' => 'aktif',
            ]);
        }
    }
}
