<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Services\StockService;
use App\Services\TransactionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\BogarasaFixtures;
use Tests\TestCase;

class BranchIsolationTest extends TestCase
{
    use BogarasaFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBogarasa();
    }

    private function sell($kasir, $product, int $qty = 1): Transaction
    {
        $this->actingAs($kasir);

        return app(TransactionService::class)->checkout($kasir, [['product_id' => $product->id, 'qty' => $qty]], 'qris', 0, null, (string) Str::uuid());
    }

    public function test_kasir_only_sees_own_branch_transactions(): void
    {
        $a = $this->makeUser('Kasir', $this->branchA);
        $b = $this->makeUser('Kasir', $this->branchB);
        $p = $this->makeProduct(10000, [$this->branchA->id => 5, $this->branchB->id => 5]);

        $trxA = $this->sell($a, $p);
        $this->sell($b, $p);

        $this->actingAs($a);
        $this->assertSame(1, Transaction::count());
        $this->actingAs($b);
        $this->assertSame(1, Transaction::count());

        $this->actingAs($b)->get("/transactions/{$trxA->id}")->assertNotFound();
        $this->actingAs($this->owner());
        $this->assertSame(2, Transaction::count());
    }

    public function test_stock_service_rejects_other_branch(): void
    {
        $a = $this->makeUser('Kasir', $this->branchA);
        $p = $this->makeProduct(10000, [$this->branchB->id => 5]);
        $this->actingAs($a);

        $this->expectException(AuthorizationException::class);
        app(StockService::class)->change($this->branchB->id, $p->id, 1, 'stok_masuk', $a->id);
    }

    public function test_kasir_cannot_pick_another_branch_when_adding_stock(): void
    {
        // Stok masuk langsung hanya sah di Cabang Pusat; kasir pusat tetap tidak bisa menembak cabang lain.
        $a = $this->makeUser('Kasir', $this->pusat);
        $a->givePermissionTo('inventory.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $p = $this->makeProduct(10000, [$this->branchB->id => 1], 1);

        $this->actingAs($a)->post('/inventory', [
            'branch_id' => $this->branchB->id, 'product_id' => $p->id, 'jenis' => 'stok_masuk', 'jumlah' => 5,
        ])->assertRedirect();

        $this->assertSame(6, $this->gudangOf($p));                  // masuk ke gudang cabang kasir (pusat)
        $this->assertSame(1, $this->stockOf($p, $this->branchB));   // cabang lain tidak berubah
    }

    public function test_kasir_cannot_cancel_other_branch_transaction_via_service(): void
    {
        $a = $this->makeUser('Kasir', $this->branchA);
        $b = $this->makeUser('Kasir', $this->branchB);
        $p = $this->makeProduct(10000, [$this->branchA->id => 5]);
        $trx = $this->sell($a, $p);

        $this->actingAs($b);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TransactionService::class)->cancel($trx, $b, 'coba-coba');
    }

    public function test_kasir_with_product_create_permission_creates_stock_rows_for_every_branch(): void
    {
        $k = $this->makeUser('Kasir', $this->branchA);
        $k->givePermissionTo(['products.create', 'products.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($k)->post('/products', [
            'category_id' => \App\Models\Category::firstOrFail()->id, 'nama_produk' => 'Roti Uji', 'harga' => 12000, 'status' => 'aktif',
        ])->assertRedirect();

        $product = \App\Models\Product::where('nama_produk', 'Roti Uji')->firstOrFail();
        $rows = \App\Models\BranchStock::withoutGlobalScopes()->where('product_id', $product->id)->pluck('branch_id')->sort()->values()->all();

        $this->assertSame(\App\Models\Branch::orderBy('id_cabang')->pluck('id_cabang')->all(), $rows);   // satu baris per cabang, bukan semua di cabang kasir
    }
}
