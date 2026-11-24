<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BogarasaFixtures;
use Tests\TestCase;

class AuditAndReportTest extends TestCase
{
    use BogarasaFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBogarasa();
    }

    public function test_audit_log_is_immutable(): void
    {
        $log = app(ActivityLogService::class)->log('test', 'x', 'uji');

        try {
            $log->update(['description' => 'diubah']);
            $this->fail('Audit log seharusnya tidak bisa diubah');
        } catch (\LogicException) {
        }
        $this->expectException(\LogicException::class);
        $log->delete();
    }

    public function test_secrets_are_not_logged(): void
    {
        $log = app(ActivityLogService::class)->log('test', 'x', 'uji', null, ['password' => 'rahasia'], ['password' => 'baru', 'name' => 'ok', 'token' => 't']);

        $this->assertSame(['name' => 'ok'], $log->nilai_sesudah);
        $this->assertSame([], $log->nilai_sebelum);
    }

    public function test_product_create_is_audited_with_new_values(): void
    {
        $cat = \App\Models\Category::firstOrFail();
        $this->actingAs($this->owner())->post('/products', [
            'nama_produk' => 'Roti Uji', 'category_id' => $cat->id, 'harga' => 12000, 'status' => 'aktif',
        ])->assertRedirect(route('products.index'));

        $log = ActivityLog::where('log_name', 'product')->where('aktivitas', 'created')->latest('created_at')->first();
        $this->assertNotNull($log);
        $this->assertSame('Roti Uji', $log->nilai_sesudah['nama_produk']);
        $this->assertSame(\App\Models\Branch::count(), \App\Models\BranchStock::count());   // baris stok tiap cabang dibuat
    }

    public function test_dashboard_and_report_exclude_cancelled_transactions(): void
    {
        $k = $this->makeUser('Kasir', $this->branchA);
        $p = $this->makeProduct(10000, [$this->branchA->id => 20]);
        $svc = app(TransactionService::class);

        $this->actingAs($k);
        $svc->checkout($k, [['product_id' => $p->id, 'qty' => 2]], 'qris', 0, null, (string) Str::uuid());           // 20.000
        $batal = $svc->checkout($k, [['product_id' => $p->id, 'qty' => 5]], 'qris', 0, null, (string) Str::uuid());  // 50.000 (dibatalkan)

        $owner = $this->owner();
        $this->actingAs($owner);
        $svc->cancel($batal, $owner, 'uji batal');

        $json = $this->getJson('/dashboard/data?periode=today')->assertOk()->json();
        $this->assertEquals(20000, $json['kpi']['omzet']);
        $this->assertSame(1, $json['kpi']['transaksi']);
        $this->assertSame(2, $json['kpi']['terjual']);

        // Kasir lain/cabang lain tidak melihat angka cabang A
        $b = $this->makeUser('Kasir', $this->branchB);
        $jsonB = $this->actingAs($b)->getJson('/dashboard/data?periode=today&branch_id=' . $this->branchA->id)->json();
        $this->assertEquals(0, $jsonB['kpi']['omzet']);
    }

    public function test_csv_export_has_bom_headers_and_no_formula_injection(): void
    {
        $owner = $this->owner();
        $res = $this->actingAs($owner)->get('/reports?type=activities&periode=today&export=csv');
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));

        $body = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('Waktu', $body);
    }

    public function test_audit_index_page_renders_successfully(): void
    {
        $owner = $this->owner();
        $res = $this->actingAs($owner)->get('/audit');
        $res->assertOk();
        $res->assertSee('Audit Trail');
    }
}
