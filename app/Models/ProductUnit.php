<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Conditionnement vendable : 1 caisse = 24 bouteilles, 1 sac = 50 livres. */
class ProductUnit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:3',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'wholesale_min_qty' => 'decimal:3',
            'is_default_sale_unit' => 'boolean',
            'is_default_purchase_unit' => 'boolean',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }

    public function toBase(float $quantity): float
    {
        return round($quantity * (float) $this->conversion_factor, 3);
    }
}
