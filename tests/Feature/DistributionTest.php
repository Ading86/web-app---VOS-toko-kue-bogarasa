<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\StockDistribution;
use App\Models\StockDistributionDetail;
use App\Models\StockMovement;
use App\Services\DistributionService;
use App\Services\StockService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BogarasaFixtures;
use Tests\TestCase;

class DistributionTest extends TestCase
{
    use BogarasaFixtures, RefreshDatabase;

    private DistributionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBogarasa();
        $this->svc = app(DistributionService::class);
    }

    private function send($user, $p, array $qtyByBranch, ?string $key = null): StockDistribution
    {
        $items = [];
        foreach ($qtyByBranch as $branchId => $qty) {
            $items[] = ['product_id' => $p->id, 'branch_id' => $branchId, 'qty' => $qty];
        }
        $this->actingAs($user);

        return $this->svc->create($user, $items, 'uji', $key ?? (string) Str::uuid());
    }

    private function line(StockDistribution $d, Branch $b): StockDistributionDetail
    {
        return $d->details()->where('branch_tujuan_id', $b->id)->firstOrFail();
    }

    public function test_dispatch_moves_stock_from_gudang_to_transit_including_pusat_allocation(): void
    {
        $p = $this->makeProduct(10000, [], 100);
        $d = $this->send($this->owner(), $p, [$this->branchA->id => 10, $this->branchB->id => 20, $this->pusat->id => 5]);

        $this->assertSame(65, $this->gudangOf($p));
        $this->assertSame(10, $this->transitOf($p, $this->branchA));
        $this->assertSame(20, $this->transitOf($p, $this->branchB));
        $this->assertSame(5, $this->transitOf($p, $this->pusat));       // alokasi untuk penjualan pusat sendiri
        $this->assertSame(0, $this->stockOf($p, $this->branchA));       // belum bisa dijual sebelum dikonfirmasi

        $this->assertSame('dikirim', $d->fresh()->status);
        $this->assertMatchesRegularExpression('/^DST\d{4}$/', $d->id);
        $this->assertMatchesRegularExpression('/^DDT\d{6}$/', $d->details()->first()->id);
        $this->assertSame(3, $d->details()->count());

        $mv = StockMovement::withoutGlobalScopes();
        $this->assertSame(1, (clone $mv)->where('reference_id', $d->id)->where('jenis_pergerakan', 'distribusi_keluar')->where('lokasi_stok', 'gudang')->count());
        $this->assertSame(3, (clone $mv)->where('reference_id', $d->id)->where('jenis_pergerakan', 'distribusi_jalan')->where('lokasi_stok', 'perjalanan')->count());
        // pergerakan milik cabang tujuan tercatat di cabang tujuan, bukan di akun pengirim
        $this->assertSame(1, (clone $mv)->where('reference_id', $d->id)->where('branch_id', $this->branchB->id)->count());
        $this->assertDatabaseHas('activity_log', ['aktivitas' => 'created', 'log_name' => 'distribution', 'subject_id' => $d->id]);
    }

    public function test_cannot_distribute_more_than_gudang_and_nothing_is_written(): void
    {
        $p = $this->makeProduct(10000, [], 10);

        try {
            $this->send($this->owner(), $p, [$this->branchA->id => 6, $this->branchB->id => 5]);   // total 11 > 10
            $this->fail('Harus gagal: stok gudang tidak cukup');
        } catch (InsufficientStockException) {
        }

        $this->assertSame(10, $this->gudangOf($p));
        $this->assertSame(0, $this->transitOf($p, $this->branchA));
        $this->assertSame(0, StockDistribution::count());
        $this->assertSame(0, StockDistributionDetail::count());
        $this->assertSame(0, StockMovement::withoutGlobalScopes()->count());
    }

    public function test_receive_moves_transit_to_sales_stock_and_header_completes_after_all_lines(): void
    {
        $p = $this->makeProduct(10000, [], 100);
        $d = $this->send($this->owner(), $p, [$this->branchA->id => 10, $this->branchB->id => 20]);

        $kasirA = $this->makeUser('Kasir', $this->branchA);
        $this->actingAs($kasirA);
        $this->svc->receive($d, $this->branchA->id, [$this->line($d, $this->branchA)->id => 10], null, $kasirA);

        $this->assertSame(10, $this->stockOf($p, $this->branchA));
        $this->assertSame(0, $this->transitOf($p, $this->branchA));
        $this->assertSame('dikirim', $d->fresh()->status);               // cabang B masih menunggu

        $owner = $this->owner();
        $this->actingAs($owner);
        $this->svc->receive($d, $this->branchB->id, [$this->line($d, $this->branchB)->id => 20], null, $owner);

        $this->assertSame(20, $this->stockOf($p, $this->branchB));
        $this->assertSame('selesai', $d->fresh()->status);
        $this->assertSame(10, $this->line($d, $this->branchA)->jumlah_diterima);
        $this->assertSame(0, $this->line($d, $this->branchA)->selisih);

        $this->expectException(ValidationException::class);               // tidak bisa dikonfirmasi dua kali
        $this->svc->receive($d, $this->branchA->id, [$this->line($d, $this->branchA)->id => 10], null, $owner);
    }

    public function test_pusat_allocation_becomes_sellable_only_after_confirmation(): void
    {
        $p = $this->makeProduct(10000, [], 50);
        $d = $this->send($this->owner(), $p, [$this->pusat->id => 8]);

        $this->assertSame(0, $this->stockOf($p, $this->pusat));
        $kasirPusat = $this->makeUser('Kasir', $this->pusat);
        $this->actingAs($kasirPusat);
        $this->svc->receive($d, $this->pusat->id, [$this->line($d, $this->pusat)->id => 8], null, $kasirPusat);

        $this->assertSame(8, $this->stockOf($p, $this->pusat));
        $this->assertSame(42, $this->gudangOf($p));
        $this->assertSame('selesai', $d->fresh()->status);
    }

    public function test_short_receipt_requires_note_and_shortfall_is_not_returned_to_gudang(): void
    {
        $p = $this->makeProduct(10000, [], 100);
        $d = $this->send($this->owner(), $p, [$this->branchA->id => 10]);
        $kasirA = $this->makeUser('Kasir', $this->branchA);
        $this->actingAs($kasirA);
        $lineId = $this->line($d, $this->branchA)->id;

        try {
            $this->svc->receive($d, $this->branchA->id, [$lineId => 7], null, $kasirA);
            $this->fail('Catatan wajib bila ada selisih');
        } catch (ValidationException) {
        }
        $this->assertSame(10, $this->transitOf($p, $this->branchA));      // gagal => rollback total

        $this->svc->receive($d, $this->branchA->id, [$lineId => 7], 'dus penyok, 3 pcs rusak', $kasirA);

        $this->assertSame(7, $this->stockOf($p, $this->branchA));
        $this->assertSame(0, $this->transitOf($p, $this->branchA));
        $this->assertSame(90, $this->gudangOf($p));                       // selisih tidak kembali ke gudang
        $line = $this->line($d, $this->branchA);
        $this->assertSame(3, $line->selisih);
        $this->assertSame('dus penyok, 3 pcs rusak', $line->catatan_selisih);
    }

    public function test_received_quantity_cannot_exceed_sent(): void
    {
        $p = $this->makeProduct(10000, [], 100);
        $d = $this->send($this->owner(), $p, [$this->branchA->id => 10]);
        $owner = $this->owner();

        $this->expectException(ValidationException::class);
        $this->svc->receive($d, $this->branchA->id, [$this->line($d, $this->branchA)->id => 11], null, $owner);
    }

    public function test_cancel_returns_everything_to_gudang(): void
    {
        $p = $this->makeProduct(10000, [], 100);
        $owner = $this->owner();
        $d = $this->send($owner, $p, [$this->branchA->id => 10, $this->pusat->id => 5]);

        $this->svc->cancel($d, $owner, 'salah kirim');

        $this->assertSame(100, $this->gudangOf($p));
        $this->assertSame(0, $this->transitOf($p, $this->branchA));
        $this->assertSame(0, $this->transitOf($p, $this->pusat));
        $this->assertSame('dibatalkan', $d->fresh()->status);
        $this->assertSame(0, $d->details()->where('status', '!=', 'dibatalkan')->count());
        $this->assertDatabaseHas('activity_log', ['aktivitas' => 'cancelled', 'subject_id' => $d->id]);

        $this->expectException(ValidationException::class);               // tidak bisa dibatalkan dua kali
        $this->svc->cancel($d->fresh(), $owner, 'lagi');
    }

    public function test_cancel_after_partial_receipt_only_cancels_pending_lines(): void
    {
        $p = $this->makeProduct(10000, [], 100);
        $owner = $this->owner();
        $d = $this->send($owner, $p, [$this->branchA->id => 10, $this->branchB->id => 20]);
        $this->svc->receive($d, $this->branchA->id, [$this->line($d, $this->branchA)->id => 10], null, $owner);

        $this->svc->cancel($d, $owner, 'cabang B tutup');

        $this->assertSame(10, $this->stockOf($p, $this->branchA));        // yang sudah diterima tetap
        $this->assertSame(0, $this->transitOf($p, $this->branchB));
        $this->assertSame(90, $this->gudangOf($p));                       // 100 - 10 diterima A; 20 untuk B kembali
        $this->assertSame('diterima', $this->line($d, $this->branchA)->status);
        $this->assertSame('dibatalkan', $this->line($d, $this->branchB)->status);
        $this->assertSame('selesai', $d->fresh()->status);
    }

    public function test_kasir_cannot_confirm_other_branch_shipment(): void
    {
        $p = $this->makeProduct(10000, [], 100);
        $d = $this->send($this->owner(), $p, [$this->branchA->id => 10]);
        $kasirB = $this->makeUser('Kasir', $this->branchB);
        $this->actingAs($kasirB);

        $this->expectException(AuthorizationException::class);
        $this->svc->receive($d, $this->branchA->id, [$this->line($d, $this->branchA)->id => 10], null, $kasirB);
    }

    public function test_only_owner_or_pusat_staff_can_create_or_cancel(): void
    {
        $p = $this->makeProduct(10000, [], 100);
        $kasirA = $this->makeUser('Kasir', $this->branchA);

        try {
            $this->send($kasirA, $p, [$this->branchA->id => 1]);
            $this->fail('Akun cabang biasa tidak boleh membuat distribusi');
        } catch (AuthorizationException) {
        }

        $d = $this->send($this->owner(), $p, [$this->branchA->id => 1]);
        $this->actingAs($kasirA);
        $this->expectException(AuthorizationException::class);
        $this->svc->cancel($d, $kasirA, 'iseng');
    }

    public function test_same_idempotency_key_creates_only_one_distribution(): void
    {
        $p = $this->makeProduct(10000, [], 100);
        $key = (string) Str::uuid();
        $owner = $this->owner();

        $d1 = $this->send($owner, $p, [$this->branchA->id => 10], $key);
        $d2 = $this->send($owner, $p, [$this->branchA->id => 10], $key);

        $this->assertSame($d1->id, $d2->id);
        $this->assertSame(1, StockDistribution::count());
        $this->assertSame(90, $this->gudangOf($p));
    }

    public function test_inactive_destination_or_product_is_rejected(): void
    {
        $p = $this->makeProduct(10000, [], 100);
        $this->branchB->update(['status' => 'nonaktif']);

        try {
            $this->send($this->owner(), $p, [$this->branchB->id => 1]);
            $this->fail('Cabang nonaktif tidak boleh menjadi tujuan');
        } catch (ValidationException) {
        }
        $this->assertSame(100, $this->gudangOf($p));

        $this->branchB->update(['status' => 'aktif']);
        $p->update(['status' => 'nonaktif']);
        $this->expectException(ValidationException::class);
        $this->send($this->owner(), $p, [$this->branchB->id => 1]);
    }

    public function test_gudang_pool_exists_only_on_pusat(): void
    {
        $p = $this->makeProduct(10000);
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->expectException(\DomainException::class);
        app(StockService::class)->change($this->branchA->id, $p->id, 5, 'stok_masuk', $owner->id, null, null, null, 'gudang');
    }

    public function test_http_flow_and_branch_visibility(): void
    {
        $p = $this->makeProduct(10000, [], 100);
        $owner = $this->owner();

        $this->actingAs($owner)->post('/distributions', [
            'qty' => [$p->id => [$this->branchA->id => 12, $this->branchB->id => '', $this->pusat->id => 0]],
            'catatan' => 'pagi', 'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $d = StockDistribution::firstOrFail();
        $this->assertSame(1, $d->details()->count());                     // kosong/0 diabaikan
        $this->assertSame(88, $this->gudangOf($p));

        $kasirA = $this->makeUser('Kasir', $this->branchA);
        $kasirB = $this->makeUser('Kasir', $this->branchB);
        $this->actingAs($kasirA)->get("/distributions/{$d->id}")->assertOk();
        $this->actingAs($kasirB)->get("/distributions/{$d->id}")->assertNotFound();   // tidak terlibat => tidak terlihat
        $this->actingAs($kasirA)->get('/distributions/create')->assertForbidden();     // tidak punya distributions.create

        $line = $this->line($d, $this->branchA);
        $this->actingAs($kasirA)->post("/distributions/{$d->id}/receive", [
            'branch_id' => $this->branchA->id, 'terima' => [$line->id => 12],
        ])->assertRedirect();
        $this->assertSame(12, $this->stockOf($p, $this->branchA));
        $this->assertSame('selesai', $d->fresh()->status);

        $this->actingAs($kasirA)->post("/distributions/{$d->id}/cancel", ['alasan' => 'coba'])->assertForbidden();   // kasir tidak boleh membatalkan
    }

    public function test_only_one_pusat_branch_is_allowed_by_database(): void
    {
        if (! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Penjaga unique pusat_unik diuji pada MySQL/MariaDB.');
        }

        $this->expectException(QueryException::class);
        Branch::create(['nama_cabang' => 'Pusat Kedua', 'is_pusat' => true, 'status' => 'aktif']);
    }
}
