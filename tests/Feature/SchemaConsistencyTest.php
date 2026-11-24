<?php

namespace Tests\Feature;

use App\Support\IdFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Menjaga standar ID: panjang kolom id = IdFormat, dan setiap foreign key bertipe sama persis dengan primary key induknya.
 * Diuji pada MySQL/MariaDB (tipe kolom SQLite tidak merepresentasikan panjang CHAR).
 */
class SchemaConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Tes skema memerlukan MySQL/MariaDB (set DB_CONNECTION=mysql di .env.testing).');
        }
    }

    private function columns(string $table)
    {
        return collect(Schema::getColumns($table))->keyBy('name');
    }

    public function test_primary_key_lengths_follow_id_format(): void
    {
        $pkMap = [
            'users' => 'id_user',
            'branches' => 'id_cabang',
            'categories' => 'id_kategori',
            'products' => 'id_produk',
            'branch_stocks' => 'id_stok',
            'transactions' => 'id_transaksi',
            'transaction_details' => 'id_detail',
            'transaction_payments' => 'id_pembayaran',
            'stock_movements' => 'id_pergerakan',
            'activity_log' => 'id_log',
            'roles' => 'id_role',
            'permissions' => 'id_permission',
            'stock_distributions' => 'id_distribusi',
            'stock_distribution_details' => 'id_detail_distribusi',
            'stock_batches' => 'id_batch',
            'stock_receipts' => 'id_penerimaan',
            'stock_receipt_details' => 'id_detail_penerimaan',
            'stock_requests' => 'id_permintaan',
            'stock_request_details' => 'id_detail_permintaan',
        ];

        foreach (IdFormat::FORMATS as $table => $_) {
            $pkCol = $pkMap[$table] ?? 'id';
            $this->assertSame('char(' . IdFormat::lengthOf($table) . ')', strtolower($this->columns($table)[$pkCol]['type']), "PK {$table}.{$pkCol}");
        }
    }

    public function test_every_foreign_key_has_same_type_and_collation_as_parent_key(): void
    {
        $checked = 0;

        foreach (Schema::getTables() as $t) {
            $table = $t['name'];
            $local = $this->columns($table);

            foreach (Schema::getForeignKeys($table) as $fk) {
                $foreign = $this->columns($fk['foreign_table']);

                foreach ($fk['columns'] as $i => $col) {
                    $parentCol = $fk['foreign_columns'][$i];
                    $this->assertSame($foreign[$parentCol]['type'], $local[$col]['type'], "{$table}.{$col} vs {$fk['foreign_table']}.{$parentCol}");
                    $this->assertSame($foreign[$parentCol]['collation'], $local[$col]['collation'], "collation {$table}.{$col}");
                    $checked++;
                }
            }
        }

        $this->assertGreaterThan(20, $checked);
    }

    public function test_id_columns_that_are_not_foreign_keys_still_match_their_parent(): void
    {
        // Kolom yang merujuk PK tetapi bukan FK sungguhan (pivot Spatie dan sessions).
        $pairs = [
            ['model_has_roles', 'model_id', 'users', 'id_user'], ['model_has_permissions', 'model_id', 'users', 'id_user'],
            ['sessions', 'user_id', 'users', 'id_user'],
        ];

        foreach ($pairs as [$t, $c, $pt, $pc]) {
            $this->assertSame($this->columns($pt)[$pc]['type'], $this->columns($t)[$c]['type'], "{$t}.{$c}");
        }
    }
}
