<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Lot de marchandise avec sa date d'expiration. */
class Batch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'manufacture_date' => 'date',
            'expiry_date' => 'date',
            'initial_quantity' => 'decimal:3',
            'remaining_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function goodsReceiptItem(): BelongsTo { return $this->belongsTo(GoodsReceiptItem::class); }
    public function movements(): HasMany { return $this->hasMany(StockMovement::class); }

    public function scopeInStock(Builder $q): Builder { return $q->where('remaining_quantity', '>', 0); }

    /** En stock ET non expiré : ce que la caisse a le droit de vendre. */
    public function scopeSellable(Builder $q): Builder
    {
        return $q->inStock()->where(fn ($w) => $w->whereNull('expiry_date')
            ->orWhereDate('expiry_date', '>=', today()));
    }

    /** FEFO : premier expiré, premier sorti ; lots sans date en dernier. */
    public function scopeFefo(Builder $q): Builder
    {
        return $q->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderBy('id');
    }

    public function scopeExpired(Builder $q): Builder
    {
        return $q->inStock()->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today());
    }

    public function scopeExpiringWithin(Builder $q, int $days): Builder
    {
        return $q->inStock()->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays($days));
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expiry_date ? (int) today()->diffInDays($this->expiry_date, false) : null;
    }
}
