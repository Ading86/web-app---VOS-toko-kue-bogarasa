<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BogarasaFixtures;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use BogarasaFixtures, RefreshDatabase;

    private TransactionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBogarasa();
        $this->svc = app(TransactionService::class);
    }

    private function items($p, int $qty): array
    {
        return [['product_id' => $p->id, 'qty' => $qty]];
    }

    public function test_checkout_reduces_stock_and_stores_price_snapshot(): void
    {
        $k = $this->makeUser('Kasir', $this->branchA);
        $p = $this->makeProduct(15000, [$this->branchA->id => 10]);
        $this->actingAs($k);

        $trx = $this->svc->checkout($k, $this->items($p, 3), 'tunai', 50000, null, (string) Str::uuid());

        $this->assertSame(7, $this->stockOf($p, $this->branchA));
        $this->assertSame('45000.00', $trx->fresh()->total);
        $this->assertSame('5000.00', $trx->payments()->first()->kembalian);
        $this->assertMatchesRegularExpression('/^BRN02-\d{8}-0001$/', $trx->kode_transaksi);
        $this->assertMatchesRegularExpression('/^TRX\d{6}$/', $trx->id);
        $this->assertSame($this->branchA->id, $trx->branch_id);
        $this->assertSame($k->id, $trx->user_id);

        $p->update(['harga' => 99999]);   // perubahan harga tidak mengubah riwayat
        $this->assertSame('15000.00', $trx->details()->first()->harga_satuan);

        $m = StockMovement::withoutGlobalScopes()->where('reference_id', $trx->id)->first();
        $this->assertSame([-3, 10, 7, 'penjualan'], [$m->jumlah, $m->stok_sebelum, $m->stok_sesudah, $m->jenis_pergerakan]);
    }

    public function test_insufficient_stock_rolls_everything_back(): void
    {
        $k = $this->makeUser('Kasir', $this->branchA);
        $p = $this->makeProduct(10000, [$this->branchA->id => 2]);
        $this->actingAs($k);

        try {
            $this->svc->checkout($k, $this->items($p, 3), 'tunai', 100000, null, (string) Str::uuid());
            $this->fail('Harus gagal karena stok kurang');
        } catch (InsufficientStockException) {
        }

        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
        $this->assertSame(2, $this->stockOf($p, $this->branchA));
        $this->assertSame(0, StockMovement::withoutGlobalScopes()->count());
    }

    public function test_same_idempotency_key_creates_only_one_transaction(): void
    {
        $k = $this->makeUser('Kasir', $this->branchA);
        $p = $this->makeProduct(10000, [$this->branchA->id => 10]);
        $this->actingAs($k);
        $key = (string) Str::uuid();

        $t1 = $this->svc->checkout($k, $this->items($p, 3), 'tunai', 30000, null, $key);
        $t2 = $this->svc->checkout($k, $this->items($p, 3), 'tunai', 30000, null, $key);

        $this->assertSame($t1->id, $t2->id);
        $this->assertSame(1, Transaction::withoutGlobalScopes()->count());
        $this->assertSame(7, $this->stockOf($p, $this->branchA));
    }

    public function test_cash_underpayment_is_rejected(): void
    {
        $k = $this->makeUser('Kasir', $this->branchA);
        $p = $this->makeProduct(10000, [$this->branchA->id => 10]);
        $this->actingAs($k);

        $this->expectException(ValidationException::class);
        $this->svc->checkout($k, $this->items($p, 2), 'tunai', 15000, null, (string) Str::uuid());
    }

    public function test_server_uses_database_price_not_client_price(): void
    {
        $k = $this->makeUser('Kasir', $this->branchA);
        $p = $this->makeProduct(10000, [$this->branchA->id => 10]);
        $this->actingAs($k)->postJson('/pos/checkout', [
            'items' => [['product_id' => $p->id, 'qty' => 1, 'harga' => 1]],
            'metode' => 'transfer', 'jumlah_bayar' => 1, 'idempotency_key' => (string) Str::uuid(),
        ])->assertOk()->assertJsonStructure(['ok', 'kode', 'redirect']);

        $this->assertSame('10000.00', Transaction::withoutGlobalScopes()->first()->total);
    }

    public function test_inactive_product_cannot_be_sold(): void
    {
        $k = $this->makeUser('Kasir', $this->branchA);
        $p = $this->makeProduct(10000, [$this->branchA->id => 10]);
        $p->update(['status' => 'nonaktif']);
        $this->actingAs($k);

        $this->expectException(ValidationException::class);
        $this->svc->checkout($k, $this->items($p, 1), 'qris', 0, null, (string) Str::uuid());
    }

    public function test_cancel_restores_stock_once(): void
    {
        $k = $this->makeUser('Kasir', $this->branchA);
        $p = $this->makeProduct(10000, [$this->branchA->id => 10]);
        $this->actingAs($k);
        $trx = $this->svc->checkout($k, $this->items($p, 4), 'qris', 0, null, (string) Str::uuid());

        $owner = $this->owner();
        $this->actingAs($owner);
        $this->svc->cancel($trx, $owner, 'salah input');

        $this->assertSame(10, $this->stockOf($p, $this->branchA));
        $this->assertSame('dibatalkan', $trx->fresh()->status);
        $this->assertSame($owner->id, $trx->fresh()->cancelled_by);
        $this->assertDatabaseHas('stock_movements', ['reference_id' => $trx->id, 'jenis_pergerakan' => 'pembatalan', 'jumlah' => 4]);
        $this->assertDatabaseHas('activity_log', ['aktivitas' => 'cancelled', 'subject_id' => $trx->id]);

        $this->expectException(ValidationException::class);   // tidak bisa dibatalkan dua kali
        $this->svc->cancel($trx->fresh(), $owner, 'lagi');
    }

    public function test_kasir_cannot_cancel_by_default_via_http(): void
    {
        $k = $this->makeUser('Kasir', $this->branchA);
        $p = $this->makeProduct(10000, [$this->branchA->id => 5]);
        $this->actingAs($k);
        $trx = $this->svc->checkout($k, $this->items($p, 1), 'qris', 0, null, (string) Str::uuid());

        $this->actingAs($k)->post("/transactions/{$trx->id}/cancel", ['alasan' => 'tes tes'])->assertForbidden();
        $this->assertSame('selesai', $trx->fresh()->status);
    }

    public function test_stock_correction_and_manual_in_out_are_recorded(): void
    {
        $owner = $this->owner();
        $p = $this->makeProduct(10000);
        $base = ['branch_id' => $this->pusat->id, 'product_id' => $p->id];

        // Stok masuk langsung hanya untuk Cabang Pusat dan masuk ke gudang.
        $this->actingAs($owner)->post('/inventory', $base + ['jenis' => 'stok_masuk', 'jumlah' => 5])->assertRedirect();
        $this->assertSame(5, $this->gudangOf($p));

        $this->actingAs($owner)->post('/inventory', $base + ['jenis' => 'penyesuaian', 'lokasi' => 'gudang', 'jumlah' => 3, 'keterangan' => 'stok opname'])->assertRedirect();
        $this->assertSame(3, $this->gudangOf($p));

        $this->actingAs($owner)->post('/inventory', $base + ['jenis' => 'stok_keluar', 'lokasi' => 'gudang', 'jumlah' => 99, 'keterangan' => 'rusak'])
            ->assertSessionHas('error');
        $this->assertSame(3, $this->gudangOf($p));   // tidak pernah negatif
        $this->assertSame(2, StockMovement::withoutGlobalScopes()->where('product_id', $p->id)->count());
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'lokasi_stok' => 'gudang', 'jenis_pergerakan' => 'stok_masuk', 'jumlah' => 5]);
    }

    public function test_direct_stock_in_is_rejected_for_non_pusat_branch(): void
    {
        $p = $this->makeProduct(10000);

        $this->actingAs($this->owner())->post('/inventory', [
            'branch_id' => $this->branchA->id, 'product_id' => $p->id, 'jenis' => 'stok_masuk', 'jumlah' => 5,
        ])->assertSessionHas('error');

        $this->assertSame(0, $this->stockOf($p, $this->branchA));
        $this->assertSame(0, StockMovement::withoutGlobalScopes()->count());
    }

    public function test_correction_of_sales_stock_on_regular_branch_still_works(): void
    {
        $p = $this->makeProduct(10000, [$this->branchA->id => 5]);

        $this->actingAs($this->owner())->post('/inventory', [
            'branch_id' => $this->branchA->id, 'product_id' => $p->id, 'jenis' => 'penyesuaian', 'jumlah' => 8, 'keterangan' => 'opname',
        ])->assertRedirect();

        $this->assertSame(8, $this->stockOf($p, $this->branchA));
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'lokasi_stok' => 'jual', 'jenis_pergerakan' => 'penyesuaian', 'jumlah' => 3]);
    }
}
