<?php

namespace App\Exceptions;

use RuntimeException;

class IdCapacityExceededException extends RuntimeException
{
    public function __construct(public readonly string $prefix, public readonly int $capacity)
    {
        parent::__construct(
            "Kapasitas ID {$prefix} sudah penuh (maksimum " . number_format($capacity, 0, ',', '.') . '). '
            . 'Tambah jumlah digit di App\\Support\\IdFormat dan ukuran kolom lewat migration baru.'
        );
    }
}
