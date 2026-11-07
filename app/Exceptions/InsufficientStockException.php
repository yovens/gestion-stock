<?php

namespace App\Exceptions;

use App\Models\Product;

class InsufficientStockException extends BusinessException
{
    public function __construct(
        public readonly Product $product,
        public readonly float $available,
        public readonly float $requested,
    ) {
        $unit = $product->baseUnit?->abbreviation ?? '';

        parent::__construct(sprintf(
            'Stock insuffisant pour « %s » : %s %s disponible(s), %s %s demandé(s).',
            $product->name,
            rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.'),
            $unit,
            rtrim(rtrim(number_format($requested, 3, '.', ''), '0'), '.'),
            $unit,
        ));
    }
}
