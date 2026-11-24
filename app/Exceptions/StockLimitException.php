<?php

namespace App\Exceptions;

class StockLimitException extends StockException
{
    public function __construct(public readonly string $productName, public readonly int $limit)
    {
        parent::__construct("Stok {$productName} melebihi batas maksimum " . number_format($limit, 0, ',', '.') . '.');
    }
}
