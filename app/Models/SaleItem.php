<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'conversion_factor' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'returned_quantity' => 'decimal:3',
        ];
    }

    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class)->withTrashed(); }
    public function productUnit(): BelongsTo { return $this->belongsTo(ProductUnit::class); }
    public function batchAllocations(): HasMany { return $this->hasMany(SaleItemBatch::class); }

    public function returnableQuantity(): float
    {
        return round((float) $this->quantity - (float) $this->returned_quantity, 3);
    }

    /** Prix net par unité vendue (après remise de ligne). */
    public function netUnitPrice(): float
    {
        return (float) $this->quantity > 0 ? (float) $this->total / (float) $this->quantity : 0.0;
    }
}
