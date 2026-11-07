<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'subtotal' => 'decimal:2',
            'transport_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(GoodsReceiptItem::class); }
    public function payments(): HasMany { return $this->hasMany(SupplierPayment::class); }

    public function dueAmount(): float
    {
        return max(0, round((float) $this->total - (float) $this->paid_amount, 2));
    }
}
