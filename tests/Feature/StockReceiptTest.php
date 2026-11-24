<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Services\DistributionService;
use App\Services\StockBatchService;
use App\Services\StockReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\BogarasaFixtures;
use Tests\TestCase;

class StockReceiptTest extends TestCase
{
    use BogarasaFixtures, RefreshDatabase;

    private StockReceiptService $receiptService;
    private StockBatchService $batchService;
    private DistributionService $distService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBogarasa();
        $this->receiptService = app(StockReceiptService::class);
        $this->batchService = app(StockBatchService::class);
        $this->distService = app(DistributionService::class);

        $this->owner()->forceFill(['must_change_password' => false])->save();
    }

    private function makeItem(string $nama, string $tipe = Product::TIPE_BASAH, int $masaLayak = 1): Product
    {
        return Product::create([
            'category_id'     => Category::firstOrFail()->id,
            'nama_produk'     => $nama . ' ' . Str::random(4),
            'harga'           => 12000,
            'status'          => 'aktif',
            'tipe_kue'        => $tipe,
            'masa_layak_jual' => $masaLayak,
        ]);
    }

    public function test_non_pusat_branch_user_cannot_access_create_receipt(): void
    {
        $nonPusatUser = $this->makeUser('Kasir', $this->branchA);
        $this->actingAs($nonPusatUser);

        // Kasir Cabang A tidak memiliki hak inventory.manage atau bukan cabang pusat
        $responseCreate = $this->get(route('stock-receipts.create'));
        $this->assertTrue(in_array($responseCreate->status(), [403, 302]));

        // Kasir tidak bisa mengakses halaman index riwayat penerimaan
        $responseIndex = $this->get(route('stock-receipts.index'));
        $this->assertTrue(in_array($responseIndex->status(), [403, 302]));

        // Menu "Penerimaan Stok" tidak boleh muncul di navbar kasir
        $responseDashboard = $this->get(route('dashboard'));
        $responseDashboard->assertDontSee('Penerimaan Stok');

        // Bahkan jika user cabang non-pusat diberi izin inventory.manage, menu tetap tidak muncul
        $nonPusatUser->givePermissionTo('inventory.manage');
        $responseDashboard2 = $this->get(route('dashboard'));
        $responseDashboard2->assertDontSee('Penerimaan Stok');
    }

    public function test_owner_can_access_create_form(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $response = $this->get(route('stock-receipts.create'));
        $response->assertOk();
        $response->assertSee('Form Penerimaan Stok Gudang Pusat');
        $response->assertSee('RCV-');
    }

    public function test_owner_can_receive_multi_products_into_gudang_pusat(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $pusat = Branch::pusat();
        $this->assertNotNull($pusat);

        $kueBasah = $this->makeItem('Lemper Ayam', Product::TIPE_BASAH, 1);
        $kueKering = $this->makeItem('Nastar Spesial', Product::TIPE_KERING, 5);

        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $payload = [
            'tanggal_penerimaan' => $today,
            'catatan'            => 'Penerimaan pagi dapur produksi',
            'items'              => [
                [
                    'product_id' => $kueBasah->id,
                    'jumlah'     => 50,
                ],
                [
                    'product_id' => $kueKering->id,
                    'jumlah'     => 100,
                ],
            ],
        ];

        $response = $this->post(route('stock-receipts.store'), $payload);
        $response->assertRedirect();

        // 1. Cek Header StockReceipt
        $receipt = StockReceipt::latest('created_at')->first();
        $this->assertNotNull($receipt);
        $this->assertStringStartsWith('RCV-', $receipt->nomor_penerimaan);
        $this->assertEquals(2, $receipt->total_item);
        $this->assertEquals(150, $receipt->total_kuantitas);
        $this->assertEquals($pusat->id, $receipt->branch_id);

        // 2. Cek Detail
        $this->assertCount(2, $receipt->details);
        $detailBasah = $receipt->details->firstWhere('product_id', $kueBasah->id);
        $this->assertEquals(1, $detailBasah->masa_layak_jual);
        $this->assertEquals($today, $detailBasah->tanggal_kedaluwarsa->toDateString());

        $detailKering = $receipt->details->firstWhere('product_id', $kueKering->id);
        $this->assertEquals(5, $detailKering->masa_layak_jual);
        $expectedExpKering = Carbon::parse($today, 'Asia/Jakarta')->addDays(4)->toDateString();
        $this->assertEquals($expectedExpKering, $detailKering->tanggal_kedaluwarsa->toDateString());

        // 3. Cek Stok Gudang Pusat di BranchStock
        $stockBasah = BranchStock::withoutGlobalScopes()->where('branch_id', $pusat->id)->where('product_id', $kueBasah->id)->first();
        $this->assertEquals(50, $stockBasah->stok_gudang);

        $stockKering = BranchStock::withoutGlobalScopes()->where('branch_id', $pusat->id)->where('product_id', $kueKering->id)->first();
        $this->assertEquals(100, $stockKering->stok_gudang);

        // 4. Cek StockBatch terbuat di Gudang Pusat
        $batchBasah = StockBatch::withoutGlobalScopes()->where('branch_id', $pusat->id)->where('product_id', $kueBasah->id)->first();
        $this->assertNotNull($batchBasah);
        $this->assertEquals(50, $batchBasah->jumlah_masuk);
        $this->assertEquals($today, $batchBasah->tanggal_kedaluwarsa->toDateString());
    }

    public function test_distribution_flow_from_gudang_pusat_to_branch(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $pusat = Branch::pusat();
        $cabangA = $this->branchA;

        $kueKering = $this->makeItem('Kastengel Keju', Product::TIPE_KERING, 5);
        $today = Carbon::now('Asia/Jakarta');

        // 1. Terima stok di Gudang Pusat 40 pcs
        $this->receiptService->receive($owner, $today, 'Stok baru', [
            ['product_id' => $kueKering->id, 'jumlah' => 40],
        ]);

        $this->assertEquals(40, $this->batchService->availableGudangQty($pusat->id, $kueKering->id));

        // 2. Buat Distribusi 25 pcs ke Cabang A
        $dist = $this->distService->create($owner, [
            ['product_id' => $kueKering->id, 'branch_id' => $cabangA->id, 'qty' => 25],
        ], 'Kirim ke Cabang A', (string) Str::uuid());

        // Sisa gudang pusat harus berkurang jadi 15
        $stockGudangPusat = BranchStock::withoutGlobalScopes()->where('branch_id', $pusat->id)->where('product_id', $kueKering->id)->first();
        $this->assertEquals(15, $stockGudangPusat->stok_gudang);
        $this->assertEquals(15, $this->batchService->availableGudangQty($pusat->id, $kueKering->id));

        // 3. Konfirmasi Penerimaan di Cabang A
        $detail = $dist->details()->first();
        $this->distService->receive($dist, $cabangA->id, [$detail->id => 25], null, $owner);

        // Cek stok jual di Cabang A bertambah 25
        $stockCabangA = BranchStock::withoutGlobalScopes()->where('branch_id', $cabangA->id)->where('product_id', $kueKering->id)->first();
        $this->assertEquals(25, $stockCabangA->jumlah_stok);

        // Cek batch di Cabang A terbuat dengan tanggal kedaluwarsa yang sama
        $batchCabangA = StockBatch::withoutGlobalScopes()->where('branch_id', $cabangA->id)->where('product_id', $kueKering->id)->first();
        $this->assertNotNull($batchCabangA);
        $this->assertEquals(25, $batchCabangA->jumlah_masuk);
        $this->assertEquals($detail->tanggal_kedaluwarsa->toDateString(), $batchCabangA->tanggal_kedaluwarsa->toDateString());
    }

    public function test_expired_batches_in_gudang_cannot_be_distributed(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $pusat = Branch::pusat();
        $cabangA = $this->branchA;

        $kueBasah = $this->makeItem('Dadar Gulung', Product::TIPE_BASAH, 1);
        $kemarin = Carbon::now('Asia/Jakarta')->subDays(2);

        // Terima stok kue basah kemarin (sudah kedaluwarsa hari ini)
        $this->receiptService->receive($owner, $kemarin, 'Stok kemarin', [
            ['product_id' => $kueBasah->id, 'jumlah' => 20],
        ]);

        // Gudang pusat tidak boleh memiliki stok layak kirim
        $this->assertEquals(0, $this->batchService->availableGudangQty($pusat->id, $kueBasah->id));

        // Percobaan distribusi harus gagal karena stok yang layak jual sudah kedaluwarsa
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->distService->create($owner, [
            ['product_id' => $kueBasah->id, 'branch_id' => $cabangA->id, 'qty' => 10],
        ], 'Coba kirim stok basi', (string) Str::uuid());
    }

    public function test_clear_basah_button_empties_remaining_wet_cake(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $pusat = Branch::pusat();

        $kueBasah = $this->makeItem('Bika Ambon', Product::TIPE_BASAH, 1);
        $today = Carbon::now('Asia/Jakarta');

        // Terima stok kue basah 30 pcs di pusat
        $this->receiptService->receive($owner, $today, 'Produksi pagi', [
            ['product_id' => $kueBasah->id, 'jumlah' => 30],
        ]);

        $stock = BranchStock::withoutGlobalScopes()->where('branch_id', $pusat->id)->where('product_id', $kueBasah->id)->first();
        $this->assertEquals(30, $stock->stok_gudang);

        // Tekan tombol Clear kue basah
        $response = $this->post(route('inventory.clear-basah'), [
            'branch_id'  => $pusat->id,
            'product_id' => $kueBasah->id,
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Pastikan stok fisik telah dikosongkan (0)
        $stock->refresh();
        $this->assertEquals(0, $stock->stok_gudang);
        $this->assertEquals(0, $stock->jumlah_stok);

        // Pastikan tercatat stock movement kedaluwarsa dengan jumlah -30
        $movement = StockMovement::where('product_id', $kueBasah->id)
            ->where('jenis_pergerakan', StockMovement::KEDALUWARSA)
            ->latest()
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-30, $movement->jumlah);
    }

    public function test_history_and_show_page_renders_correctly(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $kue = $this->makeItem('Lapis Legit', Product::TIPE_KERING, 5);
        $today = Carbon::now('Asia/Jakarta');

        $receipt = $this->receiptService->receive($owner, $today, 'Uji Riwayat', [
            ['product_id' => $kue->id, 'jumlah' => 15],
        ]);

        // Cek halaman index
        $response = $this->get(route('stock-receipts.index'));
        $response->assertOk();
        $response->assertSee($receipt->nomor_penerimaan);

        // Cek halaman show
        $responseShow = $this->get(route('stock-receipts.show', $receipt));
        $responseShow->assertOk();
        $responseShow->assertSee($receipt->nomor_penerimaan);
        $responseShow->assertSee($kue->nama_produk);
        $responseShow->assertSee('15 pcs');

        // Cek halaman inventory movements
        $responseMovements = $this->get(route('inventory.movements'));
        $responseMovements->assertOk();
        $responseMovements->assertSee('Riwayat Pergerakan Stok');
    }
}
