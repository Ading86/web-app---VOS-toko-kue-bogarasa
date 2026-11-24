<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Services\StockBatchService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BogarasaFixtures;
use Tests\TestCase;

class StockBatchTest extends TestCase
{
    use BogarasaFixtures, RefreshDatabase;

    private StockBatchService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBogarasa();
        $this->svc = app(StockBatchService::class);
        $this->owner()->forceFill(['must_change_password' => false])->save();
    }

    // ── Helper ────────────────────────────────────────────────────────────

    private function makeBatchProduct(string $tipe = Product::TIPE_BASAH, int $masaLayak = 1): Product
    {
        return Product::create([
            'category_id'    => \App\Models\Category::firstOrFail()->id,
            'nama_produk'    => 'Kue ' . Str::random(5),
            'harga'          => 15000,
            'status'         => 'aktif',
            'tipe_kue'       => $tipe,
            'masa_layak_jual' => $masaLayak,
        ]);
    }

    private function addBatch(Product $p, string $branchId, int $qty, Carbon $tgl): StockBatch
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        return $this->svc->receiveBatch($branchId, $p->id, $qty, $tgl, $owner->id);
    }

    // ── Tests: Stok Masuk Batch ────────────────────────────────────────────

    public function test_receive_batch_creates_batch_and_adds_stock(): void
    {
        $p     = $this->makeBatchProduct(Product::TIPE_BASAH, 1);
        $today = Carbon::now('Asia/Jakarta');
        $batch = $this->addBatch($p, $this->branchA->id, 50, $today);

        $this->assertSame(50, $batch->jumlah_masuk);
        $this->assertSame($today->toDateString(), $batch->tanggal_produksi->toDateString());
        $this->assertSame($today->toDateString(), $batch->tanggal_kedaluwarsa->toDateString()); // masa layak 1 hari → kedaluwarsa hari ini
        $this->assertSame(StockBatch::STATUS_AKTIF, $batch->status);
        $this->assertSame(50, $this->stockOf($p, $this->branchA));

        // StockMovement jenis batch_masuk harus ada
        $this->assertDatabaseHas('stock_movements', [
            'branch_id'       => $this->branchA->id,
            'product_id'      => $p->id,
            'jenis_pergerakan' => StockMovement::BATCH_MASUK,
            'jumlah'          => 50,
        ]);
    }

    public function test_kering_batch_expires_after_masa_layak_days(): void
    {
        $p     = $this->makeBatchProduct(Product::TIPE_KERING, 5);
        $today = Carbon::now('Asia/Jakarta');
        $batch = $this->addBatch($p, $this->branchA->id, 100, $today);

        $expectedExpiry = $today->copy()->addDays(4)->toDateString(); // masa_layak=5, inklusif (hari 1 s.d. hari 5)
        $this->assertSame($expectedExpiry, $batch->tanggal_kedaluwarsa->toDateString());
    }

    public function test_standard_product_cannot_use_batch(): void
    {
        $p = $this->makeBatchProduct(Product::TIPE_STANDARD);
        $this->actingAs($this->owner());

        $this->expectException(ValidationException::class);
        $this->svc->receiveBatch($this->branchA->id, $p->id, 10, Carbon::now('Asia/Jakarta'), $this->owner()->id);
    }

    // ── Tests: Kue Basah (Masa Berlaku 1 Hari) ─────────────────────────────

    public function test_basah_expired_batch_is_not_available_for_checkout(): void
    {
        $p       = $this->makeBatchProduct(Product::TIPE_BASAH, 1);
        $kemarin = Carbon::now('Asia/Jakarta')->subDay();

        // Buat batch kemarin (sudah kedaluwarsa)
        $this->addBatch($p, $this->branchA->id, 30, $kemarin);

        // availableQty harus 0 karena sudah berganti hari di Asia/Jakarta
        $available = $this->svc->availableQty($this->branchA->id, $p->id);
        $this->assertSame(0, $available);
    }

    public function test_basah_today_batch_is_available_until_end_of_day(): void
    {
        $p     = $this->makeBatchProduct(Product::TIPE_BASAH, 1);
        $today = Carbon::now('Asia/Jakarta');

        $this->addBatch($p, $this->branchA->id, 30, $today);

        $available = $this->svc->availableQty($this->branchA->id, $p->id);
        $this->assertSame(30, $available);
    }

    public function test_basah_past_production_date_cannot_be_checked_out(): void
    {
        $p       = $this->makeBatchProduct(Product::TIPE_BASAH, 1);
        $kemarin = Carbon::now('Asia/Jakarta')->subDay();

        // Hanya ada batch kemarin (kedaluwarsa)
        $this->addBatch($p, $this->branchA->id, 20, $kemarin);

        $kasir = $this->makeUser('Kasir', $this->branchA);
        $this->actingAs($kasir);

        // Checkout harus gagal karena stok kemarin tidak boleh dijual lagi
        $this->expectException(InsufficientStockException::class);
        app(TransactionService::class)->checkout($kasir, [['product_id' => $p->id, 'qty' => 1]], 'tunai', 50000, null, (string) Str::uuid());
    }

    public function test_new_batch_does_not_mix_with_old_expired_batch(): void
    {
        $p       = $this->makeBatchProduct(Product::TIPE_BASAH, 1);
        $kemarin = Carbon::now('Asia/Jakarta')->subDay();
        $today   = Carbon::now('Asia/Jakarta');

        // Kemarin ada 20 pcs
        $this->addBatch($p, $this->branchA->id, 20, $kemarin);

        // Hari ini masuk batch baru 30 pcs
        $this->addBatch($p, $this->branchA->id, 30, $today);

        // Yang tersedia untuk checkout HANYA 30 pcs (stok kemarin tidak digabung)
        $this->assertSame(30, $this->svc->availableQty($this->branchA->id, $p->id));

        $kasir = $this->makeUser('Kasir', $this->branchA);
        $this->actingAs($kasir);

        // Beli 10 pcs
        app(TransactionService::class)->checkout($kasir, [['product_id' => $p->id, 'qty' => 10]], 'tunai', 200000, null, (string) Str::uuid());

        // Sisa yang tersedia harus 20 pcs
        $this->assertSame(20, $this->svc->availableQty($this->branchA->id, $p->id));
    }

    // ── Tests: Kue Kering (Masa Layak 5 Hari, Akumulatif FIFO) ──────────────

    public function test_kering_available_across_multiple_days_within_shelf_life(): void
    {
        $p       = $this->makeBatchProduct(Product::TIPE_KERING, 5);
        $tigaHariLalu = Carbon::now('Asia/Jakarta')->subDays(3);

        // Batch 3 hari lalu masih layak jual (masa layak 5 hari)
        $this->addBatch($p, $this->branchA->id, 25, $tigaHariLalu);

        $this->assertSame(25, $this->svc->availableQty($this->branchA->id, $p->id));
    }

    public function test_kering_checkout_deducts_batch_fifo(): void
    {
        $p     = $this->makeBatchProduct(Product::TIPE_KERING, 5);
        $owner = $this->owner();

        // Dua batch: pertama 3 hari lalu (10 pcs), kedua hari ini (20 pcs)
        $this->addBatch($p, $this->branchA->id, 10, Carbon::now('Asia/Jakarta')->subDays(3));
        $this->addBatch($p, $this->branchA->id, 20, Carbon::now('Asia/Jakarta'));

        // Total available: 30 pcs (keduanya masih dalam masa layak jual)
        $this->assertSame(30, $this->svc->availableQty($this->branchA->id, $p->id));

        $kasir = $this->makeUser('Kasir', $this->branchA);
        $this->actingAs($kasir);

        // Beli 12 pcs → potong 10 dari batch lama + 2 dari batch baru (FIFO)
        app(TransactionService::class)->checkout($kasir, [['product_id' => $p->id, 'qty' => 12]], 'tunai', 200000, null, (string) Str::uuid());

        $batches = StockBatch::withoutGlobalScopes()
            ->where('product_id', $p->id)
            ->where('branch_id', $this->branchA->id)
            ->orderBy('tanggal_kedaluwarsa')
            ->get();

        $this->assertSame(10, $batches[0]->jumlah_terjual); // batch lama habis
        $this->assertSame(2, $batches[1]->jumlah_terjual);  // batch baru sisa 18
        $this->assertSame(18, $this->svc->availableQty($this->branchA->id, $p->id));
    }

    public function test_kering_batch_expired_after_shelf_life_is_not_available(): void
    {
        $p     = $this->makeBatchProduct(Product::TIPE_KERING, 5);
        $enamHariLalu = Carbon::now('Asia/Jakarta')->subDays(6);

        // Batch 6 hari lalu sudah melewati masa layak jual 5 hari
        $this->addBatch($p, $this->branchA->id, 15, $enamHariLalu);

        // Otomatis tidak tersedia untuk penjualan
        $this->assertSame(0, $this->svc->availableQty($this->branchA->id, $p->id));
    }

    // ── Tests: Automatic Stock Expiration ──────────────────────────────────

    public function test_auto_expire_stale_batches_deducts_stock_and_records_movement(): void
    {
        $p     = $this->makeBatchProduct(Product::TIPE_BASAH, 1);
        $owner = $this->owner();

        // Batch kue basah kemarin sisa 20 pcs
        $batch = $this->addBatch($p, $this->branchA->id, 20, Carbon::now('Asia/Jakarta')->subDay());

        // Jalankan automatic expiration
        $expired = $this->svc->autoExpireStaleBatches($this->branchA->id, $owner->id);

        $this->assertSame(20, $expired);
        $this->assertSame(0, $this->stockOf($p, $this->branchA));
        $this->assertSame(StockBatch::STATUS_KEDALUWARSA, $batch->fresh()->status);

        // Riwayat pergerakan stok kedaluwarsa tercatat otomatis
        $this->assertDatabaseHas('stock_movements', [
            'branch_id'       => $this->branchA->id,
            'product_id'      => $p->id,
            'jenis_pergerakan' => StockMovement::KEDALUWARSA,
            'jumlah'          => -20,
        ]);
    }

    // ── Tests: Pembatalan Transaksi (LIFO Restore) ─────────────────────────

    public function test_cancel_restores_batch_lifo(): void
    {
        $p     = $this->makeBatchProduct(Product::TIPE_KERING, 5);
        $owner = $this->owner();

        $this->addBatch($p, $this->branchA->id, 10, Carbon::now('Asia/Jakarta')->subDay());
        $this->addBatch($p, $this->branchA->id, 20, Carbon::now('Asia/Jakarta'));

        $kasir = $this->makeUser('Kasir', $this->branchA);
        $this->actingAs($kasir);
        $trx = app(TransactionService::class)->checkout($kasir, [['product_id' => $p->id, 'qty' => 5]], 'tunai', 100000, null, (string) Str::uuid());

        $this->actingAs($owner);
        app(TransactionService::class)->cancel($trx, $owner, 'salah input');

        // Stok kembali utuh 30 pcs
        $this->assertSame(30, $this->svc->availableQty($this->branchA->id, $p->id));
    }

    // ── Tests: POS & Penghapusan Fitur Penutupan Harian ─────────────────────

    public function test_pos_shows_zero_stock_for_expired_basah_via_http(): void
    {
        $p       = $this->makeBatchProduct(Product::TIPE_BASAH, 1);
        $kemarin = Carbon::now('Asia/Jakarta')->subDay();

        $this->addBatch($p, $this->branchA->id, 20, $kemarin);

        $kasir = $this->makeUser('Kasir', $this->branchA);
        $kasir->givePermissionTo('transactions.create');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $response = $this->actingAs($kasir)->get('/pos');
        $response->assertOk();

        // Produk kue basah expired harus stok 0 di POS
        $productsInView = $response->viewData('products');
        $item = collect($productsInView)->firstWhere('id', $p->id);
        $this->assertNotNull($item);
        $this->assertSame(0, $item['stok']);

        // Tidak ada banner atau peringatan penutupan harian di halaman POS
        $response->assertDontSee('penutupan');
        $response->assertDontSee('daily-closings');
    }

    public function test_daily_closing_route_is_removed_and_returns_404(): void
    {
        $owner = $this->owner();

        // Memastikan tidak ada rute daily-closings yang aktif
        $this->actingAs($owner)->get('/daily-closings')->assertNotFound();
        $this->actingAs($owner)->get('/daily-closings/create')->assertNotFound();
        $this->actingAs($owner)->post('/daily-closings', [])->assertNotFound();
    }

    public function test_branch_isolation_on_batches(): void
    {
        $p     = $this->makeBatchProduct(Product::TIPE_BASAH, 1);
        $today = Carbon::now('Asia/Jakarta');

        // Masukkan batch di cabang A
        $this->addBatch($p, $this->branchA->id, 50, $today);

        // Cabang A memiliki stok 50, cabang B memiliki stok 0
        $this->assertSame(50, $this->svc->availableQty($this->branchA->id, $p->id));
        $this->assertSame(0, $this->svc->availableQty($this->branchB->id, $p->id));
    }

    public function test_http_batch_store(): void
    {
        $p = $this->makeBatchProduct(Product::TIPE_BASAH, 1);
        $owner = $this->owner();

        $res = $this->actingAs($owner)->post('/stock-batches', [
            'branch_id'        => $this->branchA->id,
            'product_id'       => $p->id,
            'jumlah'           => 40,
            'tanggal_produksi' => Carbon::now('Asia/Jakarta')->toDateString(),
        ]);
        $res->assertSessionHasNoErrors();
        $res->assertRedirect();

        $this->assertDatabaseHas('stock_batches', ['product_id' => $p->id, 'jumlah_masuk' => 40]);
    }

    public function test_auto_expire_stale_batches_never_throws_when_physical_stock_is_lower(): void
    {
        $p = $this->makeBatchProduct(Product::TIPE_BASAH, 1);
        $yesterday = Carbon::now('Asia/Jakarta')->subDays(2);
        $owner = $this->owner();
        $this->actingAs($owner);

        // Buat batch kemarin dengan sisa 60
        $batch = $this->svc->receiveBatch($this->pusat->id, $p->id, 60, $yesterday, $owner->id);

        // Ubah stok fisik gudang pusat menjadi hanya 20 (misal akibat distribusi sebagian yang terpisah)
        \App\Models\BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->pusat->id)
            ->where('product_id', $p->id)
            ->update(['stok_gudang' => 20]);

        // Auto expire harus berjalan tanpa error InsufficientStockException
        $cleared = $this->svc->autoExpireStaleBatches($this->pusat->id, $owner->id);

        $this->assertSame(60, $cleared);
        $this->assertSame(StockBatch::STATUS_KEDALUWARSA, $batch->fresh()->status);
        // Stok fisik gudang menjadi 0 (tidak negatif)
        $this->assertSame(0, (int) \App\Models\BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->pusat->id)
            ->where('product_id', $p->id)
            ->value('stok_gudang'));
    }
}
