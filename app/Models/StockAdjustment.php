<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    public const TYPES = [
        'loss' => 'Perte',
        'damage' => 'Casse / abîmé',
        'expired' => 'Expiré',
        'theft' => 'Vol',
        'correction' => 'Correction',
        'gift' => 'Don / échantillon',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['adjustment_date' => 'date', 'approved_at' => 'datetime'];
    }

    public function items(): HasMany { return $this->hasMany(StockAdjustmentItem::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function totalValue(): float
    {
        return round($this->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_cost), 2);
    }
}
