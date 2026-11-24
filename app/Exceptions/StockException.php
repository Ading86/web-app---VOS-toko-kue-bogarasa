<?php

namespace App\Exceptions;

use RuntimeException;

/** Induk semua kegagalan aturan stok; controller cukup menangkap kelas ini. */
class StockException extends RuntimeException
{
}
