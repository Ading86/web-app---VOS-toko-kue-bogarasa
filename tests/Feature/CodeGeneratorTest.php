<?php

namespace Tests\Feature;

use App\Exceptions\IdCapacityExceededException;
use App\Services\CodeGeneratorService;
use App\Support\IdFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_codes_are_sequential_and_unique(): void
    {
        $g = app(CodeGeneratorService::class);
        $codes = [$g->next('XYZ'), $g->next('XYZ'), $g->next('XYZ', 6)];

        $this->assertSame(['XYZ001', 'XYZ002', 'XYZ000003'], $codes);
    }

    public function test_for_table_uses_id_format_prefix_and_digits(): void
    {
        $g = app(CodeGeneratorService::class);

        $this->assertSame('BRN01', $g->forTable('branches'));
        $this->assertSame('USR001', $g->forTable('users'));
        $this->assertSame('PRD0001', $g->forTable('products'));
        $this->assertSame('TRX000001', $g->forTable('transactions'));
        $this->assertSame('DST0001', $g->forTable('stock_distributions'));
        $this->assertSame('DDT000001', $g->forTable('stock_distribution_details'));
        $this->assertSame('SMV000001', $g->forTable('stock_movements'));
    }

    public function test_every_generated_code_matches_the_declared_length(): void
    {
        $g = app(CodeGeneratorService::class);

        foreach (array_keys(IdFormat::FORMATS) as $table) {
            $code = $g->forTable($table);

            $this->assertSame(IdFormat::lengthOf($table), strlen($code), $table);
            $this->assertMatchesRegularExpression(IdFormat::patternOf($table), $code, $table);
        }
    }

    public function test_capacity_overflow_is_rejected_instead_of_creating_oversized_id(): void
    {
        $g = app(CodeGeneratorService::class);

        foreach (range(1, 9) as $i) {
            $g->next('CAP', 1);
        }

        $this->expectException(IdCapacityExceededException::class);
        $g->next('CAP', 1);   // ke-10 tidak muat di 1 digit
    }

    public function test_receipt_code_resets_per_day_and_per_branch(): void
    {
        $g = app(CodeGeneratorService::class);
        $d1 = Carbon::create(2026, 10, 30, 10);
        $d2 = Carbon::create(2026, 10, 31, 10);

        $this->assertSame('BRN01-20261030-0001', $g->receiptCode('BRN01', 'BRN01', $d1));
        $this->assertSame('BRN01-20261030-0002', $g->receiptCode('BRN01', 'BRN01', $d1));
        $this->assertSame('BRN02-20261030-0001', $g->receiptCode('BRN02', 'BRN02', $d1));
        $this->assertSame('BRN01-20261031-0001', $g->receiptCode('BRN01', 'BRN01', $d2));
        $this->assertSame('BRN03-20261031-0001', $g->receiptCode('BRN03', 'BRN-03', $d2));   // karakter non-alfanumerik dibuang
    }
}
