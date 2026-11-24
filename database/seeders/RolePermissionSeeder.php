<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'dashboard.view', 'vos.view',
        'branches.view', 'branches.manage',
        'categories.view', 'categories.manage',
        'products.view', 'products.create', 'products.edit', 'products.delete',
        'transactions.view', 'transactions.create', 'transactions.cancel',
        'reports.view',
        'inventory.view', 'inventory.manage',
        'distributions.view', 'distributions.create', 'distributions.receive', 'distributions.cancel',
        'stock_requests.view', 'stock_requests.create', 'stock_requests.approve', 'stock_requests.process', 'stock_requests.receive',
        'users.view', 'users.manage',
        'permissions.manage',
        'auditlogs.view',
    ];

public const KASIR_DEFAULT = [
        'dashboard.view', 'vos.view', 'products.view',
        'transactions.view', 'transactions.create', 'inventory.view',
        'distributions.view', 'distributions.receive',
        'stock_requests.view', 'stock_requests.create', 'stock_requests.receive',
    ];

    public const WAREHOUSE_DEFAULT = [
        'dashboard.view', 'vos.view', 'products.view',
        'inventory.view', 'inventory.manage',
        'distributions.view', 'distributions.create',
        'stock_requests.view', 'stock_requests.process',
        'auditlogs.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Owner selalu memegang semua permission.
        $owner = Role::findOrCreate('Owner', 'web');
        $owner->syncPermissions(Permission::all());

        // Kasir: selalu sync agar permission baru langsung aktif di role yang sudah ada.
        $kasir = Role::findOrCreate('Kasir', 'web');
        $kasir->syncPermissions(self::KASIR_DEFAULT);

        // Warehouse: petugas gudang pusat untuk proses & kirim stok
        $warehouse = Role::findOrCreate('Warehouse', 'web');
        $warehouse->syncPermissions(self::WAREHOUSE_DEFAULT);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}