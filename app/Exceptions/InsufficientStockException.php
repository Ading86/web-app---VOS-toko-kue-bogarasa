<?php

namespace App\Exceptions;

class InsufficientStockException extends StockException
{
    public function __construct(
        public readonly string $productName,
        public readonly int $available,
        public readonly int $requested,
        public readonly ?string $lokasi = null,
    ) {
        $where = $lokasi ? " di {$lokasi}" : '';

        parent::__construct("Stok {$productName}{$where} tidak cukup (tersedia {$available}, diminta {$requested}).");
    }
}
