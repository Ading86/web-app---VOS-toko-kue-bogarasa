<?php

namespace App\Services;

use App\Exceptions\IdCapacityExceededException;
use App\Support\IdFormat;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CodeGeneratorService
{
    /** Batas urutan struk harian per cabang (4 digit). */
    public const RECEIPT_MAX = 9999;

    /** Contoh: next('PRD', 4) => PRD0001, next('TRX', 6) => TRX000001. Melempar exception bila kapasitas habis. */
    public function next(string $prefix, int $digits = 3): string
    {
        $max = (10 ** $digits) - 1;

        return $prefix . str_pad((string) $this->increment($prefix, $max), $digits, '0', STR_PAD_LEFT);
    }

    /** ID berikutnya untuk sebuah tabel sesuai IdFormat. Contoh: forTable('branches') => BRN01 */
    public function forTable(string $table): string
    {
        return $this->next(IdFormat::prefixOf($table), IdFormat::digitsOf($table));
    }

    /** Contoh: BRN01-20261031-0001 (urut harian per cabang) */
    public function receiptCode(string $branchId, string $branchCode, ?CarbonInterface $date = null): string
    {
        $ymd = ($date ?? now())->format('Ymd');
        $number = $this->increment("RCPT:{$branchId}:{$ymd}", self::RECEIPT_MAX);
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $branchCode));

        return sprintf('%s-%s-%04d', $code, $ymd, $number);
    }

    /**
     * Atomik: baris sequence dikunci (SELECT ... FOR UPDATE) sampai transaksi
     * pemanggil selesai. Jika transaksi pemanggil di-rollback, nomor ikut
     * di-rollback sehingga tidak ada lubang nomor.
     */
    private function increment(string $key, int $max): int
    {
        return DB::transaction(function () use ($key, $max) {
            DB::table('code_sequences')->insertOrIgnore(['sequence_key' => $key, 'last_number' => 0]);

            $row = DB::table('code_sequences')
                ->where('sequence_key', $key)
                ->lockForUpdate()
                ->first();

            $next = (int) $row->last_number + 1;

            if ($next > $max) {
                throw new IdCapacityExceededException($key, $max);   // rollback: nomor tidak maju
            }

            DB::table('code_sequences')->where('sequence_key', $key)->update(['last_number' => $next]);

            return $next;
        });
    }
}
