<?php

namespace App\Support;

/**
 * Sumber tunggal format ID semua tabel bisnis: PREFIX + angka berurutan dengan jumlah digit tetap.
 * Panjang kolom di migration = panjang prefix + jumlah digit (ditulis literal di migration agar migration
 * tetap stabil; tes SchemaConsistencyTest memastikan keduanya selalu sama).
 */
final class IdFormat
{
    /** tabel => [prefix, jumlah digit]  (kapasitas = 10^digit - 1) */
    public const FORMATS = [
        'users' => ['USR', 3],                          // USR001      6 karakter, maks 999
        'branches' => ['BRN', 2],                       // BRN01       5 karakter, maks 99
        'categories' => ['CTG', 2],                     // CTG01       5 karakter, maks 99
        'products' => ['PRD', 4],                       // PRD0001     7 karakter, maks 9.999
        'branch_stocks' => ['BST', 4],                  // BST0001     7 karakter, maks 9.999
        'transactions' => ['TRX', 6],                   // TRX000001   9 karakter, maks 999.999
        'transaction_details' => ['TDT', 6],            // TDT000001   9 karakter, maks 999.999
        'transaction_payments' => ['PAY', 6],           // PAY000001   9 karakter, maks 999.999
        'stock_movements' => ['SMV', 6],                // SMV000001   9 karakter, maks 999.999
        'activity_log' => ['LOG', 6],                   // LOG000001   9 karakter, maks 999.999
        'roles' => ['ROL', 2],                          // ROL01       5 karakter, maks 99
        'permissions' => ['PRM', 3],                    // PRM001      6 karakter, maks 999
        'stock_distributions' => ['DST', 4],            // DST0001     7 karakter, maks 9.999
        'stock_distribution_details' => ['DDT', 6],     // DDT000001   9 karakter, maks 999.999
        'stock_batches' => ['SBT', 6],                  // SBT000001   9 karakter, maks 999.999
        'stock_receipts' => ['RCV', 6],                 // RCV000001   9 karakter, maks 999.999
        'stock_receipt_details' => ['RDT', 6],         // RDT000001   9 karakter, maks 999.999
        'stock_requests' => ['SRQ', 4],                // SRQ0001     7 karakter, maks 9.999
        'stock_request_details' => ['SQD', 6],         // SQD000001   9 karakter, maks 999.999
    ];

    public static function prefixOf(string $table): string
    {
        return self::spec($table)[0];
    }

    public static function digitsOf(string $table): int
    {
        return self::spec($table)[1];
    }

    public static function lengthOf(string $table): int
    {
        return strlen(self::prefixOf($table)) + self::digitsOf($table);
    }

    public static function capacityOf(string $table): int
    {
        return (10 ** self::digitsOf($table)) - 1;
    }

    public static function patternOf(string $table): string
    {
        return '/^' . preg_quote(self::prefixOf($table), '/') . '\d{' . self::digitsOf($table) . '}$/';
    }

    /** @return array{0:string, 1:int} [prefix, digits] */
    private static function spec(string $table): array
    {
        return self::FORMATS[$table] ?? throw new \InvalidArgumentException("Format ID untuk tabel '{$table}' belum terdaftar.");
    }
}
