<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'difference' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function inventoryCount(): BelongsTo { return $this->belongsTo(InventoryCount::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
