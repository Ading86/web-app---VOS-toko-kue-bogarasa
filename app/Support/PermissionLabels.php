<?php

namespace App\Support;

use Illuminate\Support\Str;

class PermissionLabels
{
    public const GROUPS = [
        'dashboard'     => 'Dashboard',
        'vos'           => 'VOS (monitoring)',
        'branches'      => 'Cabang',
        'categories'    => 'Kategori',
        'products'      => 'Produk',
        'transactions'  => 'Transaksi',
        'reports'       => 'Laporan',
        'inventory'     => 'Persediaan / Stok',
        'distributions' => 'Distribusi Stok',
        'users'         => 'Pengguna',
        'permissions'   => 'Hak Akses',
        'auditlogs'     => 'Audit Trail',
    ];

    public const ACTIONS = [
        'view'     => 'Lihat',
        'create'   => 'Tambah',
        'edit'     => 'Ubah',
        'delete'   => 'Hapus',
        'manage'   => 'Kelola',
        'cancel'   => 'Batalkan',
        'receive'  => 'Konfirmasi terima',
    ];

    public static function group(string $key): string
    {
        return self::GROUPS[$key] ?? $key;
    }

    public static function action(string $permission): string
    {
        $a = Str::after($permission, '.');

        return self::ACTIONS[$a] ?? $a;
    }
}
