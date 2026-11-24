<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

trait BogarasaFixtures
{
    protected Branch $pusat;     // BRN01, Cabang Pusat (is_pusat = true)
    protected Branch $branchA;   // BRN02, cabang biasa
    protected Branch $branchB;   // BRN03, cabang biasa

    protected function setUpBogarasa(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        User::role('Owner')->update(['must_change_password' => false]);

        [$this->pusat, $this->branchA, $this->branchB] = Branch::orderBy('kode_cabang')->get()->all();
    }

    protected function owner(): User
    {
        return User::role('Owner')->firstOrFail();
    }

    protected function makeUser(string $role, ?Branch $branch = null, array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name' => $role . ' Test', 'email' => Str::lower(Str::random(8)) . '@test.com', 'password' => 'Secret123',
            'branch_id' => $branch?->getKey(), 'is_active' => true, 'must_change_password' => false,
        ], $attrs));
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string,int>  $stocks  [branch_id => stok jual]
     * @param  int  $gudang  stok gudang Cabang Pusat
     * Panggil SEBELUM actingAs(kasir): hook BelongsToBranch memaksa branch_id sesuai akun yang sedang login.
     */
    protected function makeProduct(int $price = 10000, array $stocks = [], int $gudang = 0): Product
    {
        $product = Product::create([
            'category_id' => Category::firstOrFail()->getKey(), 'nama_produk' => 'Roti ' . Str::random(5),
            'harga' => $price, 'status' => 'aktif',
        ]);

        foreach (Branch::all() as $b) {
            BranchStock::create([
                'branch_id' => $b->getKey(), 'product_id' => $product->getKey(),
                'jumlah_stok' => $stocks[$b->getKey()] ?? 0,
                'stok_gudang' => $b->is_pusat ? $gudang : 0,
                'stok_minimum' => 0,
            ]);
        }

        return $product;
    }

    private function stockColumn(Product $p, Branch $b, string $column): int
    {
        return (int) BranchStock::withoutGlobalScopes()->where('branch_id', $b->getKey())->where('product_id', $p->getKey())->value($column);
    }

    /** Stok jual cabang. */
    protected function stockOf(Product $p, Branch $b): int
    {
        return $this->stockColumn($p, $b, 'jumlah_stok');
    }

    /** Stok gudang Cabang Pusat. */
    protected function gudangOf(Product $p): int
    {
        return $this->stockColumn($p, $this->pusat, 'stok_gudang');
    }

    /** Stok dalam perjalanan menuju cabang. */
    protected function transitOf(Product $p, Branch $b): int
    {
        return $this->stockColumn($p, $b, 'stok_perjalanan');
    }
}
