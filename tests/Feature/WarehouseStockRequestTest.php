<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\StockRequestDetail;
use App\Services\StockRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BogarasaFixtures;
use Tests\TestCase;

class WarehouseStockRequestTest extends TestCase
{
    use BogarasaFixtures, RefreshDatabase;

    private StockRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBogarasa();
        $this->service = app(StockRequestService::class);
    }

    public function test_cabang_can_create_stock_request(): void
    {
        $kasirA = $this->makeUser('Kasir', $this->branchA);
        $p1 = $this->makeProduct(10000, [], 50);
        $p2 = $this->makeProduct(15000, [], 30);

        $response = $this->actingAs($kasirA)->post('/stock-requests', [
            'catatan' => 'Restock kue akhir pekan untuk Cabang A',
            'items'   => [
                ['product_id' => $p1->id_produk, 'qty' => 20],
                ['product_id' => $p2->id_produk, 'qty' => 15],
            ],
        ]);

        $response->assertRedirect();

        $req = StockRequest::first();
        $this->assertNotNull($req);
        $this->assertSame($this->branchA->id_cabang, $req->branch_id);
        $this->assertSame($kasirA->id_user, $req->user_id);
        $this->assertSame(StockRequest::STATUS_DIAJUKAN, $req->status);
        $this->assertCount(2, $req->details);

        $d1 = $req->details()->where('product_id', $p1->id_produk)->first();
        $this->assertSame(20, $d1->jumlah_minta);
        $this->assertSame(20, $d1->jumlah_setuju); // Nilai default usulan
    }

    public function test_cabang_can_only_view_own_requests(): void
    {
        $kasirA = $this->makeUser('Kasir', $this->branchA);
        $kasirB = $this->makeUser('Kasir', $this->branchB);
        $owner  = $this->owner();
        $p = $this->makeProduct(10000);

        // Buat request milik Cabang A
        $reqA = $this->service->create($kasirA, [
            ['product_id' => $p->id_produk, 'qty' => 10],
        ], 'Permintaan A');

        // Kasir A bisa melihat request cabangnya
        $this->actingAs($kasirA)->get("/stock-requests/{$reqA->id_permintaan}")->assertOk();

        // Kasir B dilarang melihat request Cabang A
        $this->actingAs($kasirB)->get("/stock-requests/{$reqA->id_permintaan}")->assertForbidden();

        // Owner bisa melihat
        $this->actingAs($owner)->get("/stock-requests/{$reqA->id_permintaan}")->assertOk();
    }

    public function test_owner_can_review_and_adjust_approved_quantity(): void
    {
        $kasirA = $this->makeUser('Kasir', $this->branchA);
        $owner  = $this->owner();
        $p1 = $this->makeProduct(10000, [], 100);
        $p2 = $this->makeProduct(15000, [], 50);

        $req = $this->service->create($kasirA, [
            ['product_id' => $p1->id_produk, 'qty' => 50],
            ['product_id' => $p2->id_produk, 'qty' => 30],
        ], 'Butuh banyak');

        // Owner menyetujui, tetapi kuantitas p1 dikurangi menjadi 35 dan p2 menjadi 20
        $response = $this->actingAs($owner)->post("/stock-requests/{$req->id_permintaan}/approve", [
            'approved_qtys' => [
                $p1->id_produk => 35,
                $p2->id_produk => 20,
            ],
            'catatan_owner' => 'Disetujui sebagian karena stok gudang dibagi dengan cabang lain',
        ]);

        $response->assertRedirect();
        $req->refresh();

        $this->assertSame(StockRequest::STATUS_DISETUJUI, $req->status);
        $this->assertSame($owner->id_user, $req->approved_by);
        $this->assertNotNull($req->approved_at);
        $this->assertStringContainsString('Disetujui sebagian', $req->catatan_owner);

        $d1 = $req->details()->where('product_id', $p1->id_produk)->first();
        $this->assertSame(50, $d1->jumlah_minta);
        $this->assertSame(35, $d1->jumlah_setuju); // Berhasil diubah oleh owner

        $d2 = $req->details()->where('product_id', $p2->id_produk)->first();
        $this->assertSame(30, $d2->jumlah_minta);
        $this->assertSame(20, $d2->jumlah_setuju);
    }

    public function test_owner_can_reject_request_with_reason(): void
    {
        $kasirA = $this->makeUser('Kasir', $this->branchA);
        $owner  = $this->owner();
        $p = $this->makeProduct(10000);

        $req = $this->service->create($kasirA, [
            ['product_id' => $p->id_produk, 'qty' => 20],
        ], 'Minta kue');

        $response = $this->actingAs($owner)->post("/stock-requests/{$req->id_permintaan}/reject", [
            'alasan_penolakan' => 'Produksi dapur hari ini libur nasional',
        ]);

        $response->assertRedirect();
        $req->refresh();

        $this->assertSame(StockRequest::STATUS_DITOLAK, $req->status);
        $this->assertSame($owner->id_user, $req->approved_by);
        $this->assertSame('Produksi dapur hari ini libur nasional', $req->catatan_owner);
    }

    public function test_warehouse_can_dispatch_approved_request_and_deducts_gudang_pusat(): void
    {
        $kasirA    = $this->makeUser('Kasir', $this->branchA);
        $warehouse = $this->makeUser('Warehouse', $this->pusat);
        $owner     = $this->owner();

        // Siapkan produk dengan stok gudang pusat = 100
        $p = $this->makeProduct(10000, [], 100);

        // 1. Cabang mengajukan 40 pcs
        $req = $this->service->create($kasirA, [
            ['product_id' => $p->id_produk, 'qty' => 40],
        ], 'Permintaan stok');

        // 2. Owner menyetujui 40 pcs
        $this->service->approve($req, $owner, [$p->id_produk => 40], 'ACC');

        // 3. Petugas Warehouse memproses pengiriman
        $response = $this->actingAs($warehouse)->post("/stock-requests/{$req->id_permintaan}/dispatch", [
            'catatan_pengiriman' => 'Dikirim kurir pagi armada 01',
        ]);

        $response->assertRedirect();
        $req->refresh();

        $this->assertSame(StockRequest::STATUS_DIKIRIM, $req->status);
        $this->assertSame($warehouse->id_user, $req->processed_by);
        $this->assertNotNull($req->distribution_id);

        // Verifikasi mutasi stok:
        // Stok Gudang Pusat berkurang: 100 - 40 = 60
        $stkGudang = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->pusat->id_cabang)
            ->where('product_id', $p->id_produk)
            ->value('stok_gudang');
        $this->assertSame(60, $stkGudang);

        // Stok Dalam Perjalanan Cabang A bertambah: 40
        $stkPerjalanan = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->branchA->id_cabang)
            ->where('product_id', $p->id_produk)
            ->value('stok_perjalanan');
        $this->assertSame(40, $stkPerjalanan);
    }

    public function test_cabang_can_confirm_receipt_exact(): void
    {
        $kasirA    = $this->makeUser('Kasir', $this->branchA);
        $warehouse = $this->makeUser('Warehouse', $this->pusat);
        $owner     = $this->owner();
        $p = $this->makeProduct(10000, [$this->branchA->id_cabang => 5], 50);

        $req = $this->service->create($kasirA, [['product_id' => $p->id_produk, 'qty' => 25]], 'Minta 25');
        $this->service->approve($req, $owner, [$p->id_produk => 25]);
        $this->service->processDispatch($req, $warehouse);

        $detail = $req->details()->first();

        // Cabang mengonfirmasi penerimaan 25 pcs (pas)
        $response = $this->actingAs($kasirA)->post("/stock-requests/{$req->id_permintaan}/receive", [
            'received_qtys'   => [$detail->id => 25],
            'catatan_selisih' => null,
        ]);

        $response->assertRedirect();
        $req->refresh();

        $this->assertSame(StockRequest::STATUS_SELESAI, $req->status);

        // Verifikasi stok Cabang A:
        // Stok perjalanan kembali 0
        $stkPerjalanan = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->branchA->id_cabang)
            ->where('product_id', $p->id_produk)
            ->value('stok_perjalanan');
        $this->assertSame(0, $stkPerjalanan);

        // Stok jual bertambah: 5 + 25 = 30
        $stkJual = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->branchA->id_cabang)
            ->where('product_id', $p->id_produk)
            ->value('jumlah_stok');
        $this->assertSame(30, $stkJual);
    }

    public function test_cabang_can_confirm_receipt_with_shortage_and_mandatory_note(): void
    {
        $kasirA    = $this->makeUser('Kasir', $this->branchA);
        $warehouse = $this->makeUser('Warehouse', $this->pusat);
        $owner     = $this->owner();
        $p = $this->makeProduct(10000, [$this->branchA->id_cabang => 0], 50);

        $req = $this->service->create($kasirA, [['product_id' => $p->id_produk, 'qty' => 20]], 'Minta 20');
        $this->service->approve($req, $owner, [$p->id_produk => 20]);
        $this->service->processDispatch($req, $warehouse);

        $detail = $req->details()->first();

        // 1. Terima kurang (17 pcs dari 20 pcs) TANPA catatan => harus ditolak
        $responseGagal = $this->actingAs($kasirA)->post("/stock-requests/{$req->id_permintaan}/receive", [
            'received_qtys'   => [$detail->id => 17],
            'catatan_selisih' => '',
        ]);
        $responseGagal->assertRedirect();
        $responseGagal->assertSessionHas('error');

        // 2. Terima kurang (17 pcs) DENGAN catatan alasan selisih => harus sukses
        $responseSukses = $this->actingAs($kasirA)->post("/stock-requests/{$req->id_permintaan}/receive", [
            'received_qtys'   => [$detail->id => 17],
            'catatan_selisih' => '3 pcs kue rusak tertekan saat pengiriman armada',
        ]);
        $responseSukses->assertRedirect();
        $req->refresh();
        $detail->refresh();

        $this->assertSame(StockRequest::STATUS_SELESAI, $req->status);
        $this->assertSame(17, $detail->jumlah_terima);
        $this->assertSame(3, $detail->selisih);
        $this->assertSame('3 pcs kue rusak tertekan saat pengiriman armada', $detail->catatan_selisih);

        // Stok jual cabang bertambah HANYA yang diterima fisik: 0 + 17 = 17
        $stkJual = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->branchA->id_cabang)
            ->where('product_id', $p->id_produk)
            ->value('jumlah_stok');
        $this->assertSame(17, $stkJual);

        // Stok perjalanan bersih kembali 0
        $stkPerjalanan = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->branchA->id_cabang)
            ->where('product_id', $p->id_produk)
            ->value('stok_perjalanan');
        $this->assertSame(0, $stkPerjalanan);
    }
}
