<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GoodsReceiptItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'conversion_factor' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'manufacture_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function goodsReceipt(): BelongsTo { return $this->belongsTo(GoodsReceipt::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function productUnit(): BelongsTo { return $this->belongsTo(ProductUnit::class); }
    public function batch(): HasOne { return $this->hasOne(Batch::class); }
}
