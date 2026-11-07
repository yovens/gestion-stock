<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const HELD = 'held';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const REFUNDED = 'refunded';
    public const PARTIALLY_REFUNDED = 'partially_refunded';

    /** Statuts qui comptent dans le chiffre d'affaires et la caisse. */
    public const COUNTED = [self::COMPLETED, self::PARTIALLY_REFUNDED, self::REFUNDED];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany { return $this->hasMany(SaleItem::class); }
    public function payments(): HasMany { return $this->hasMany(SalePayment::class); }
    public function returns(): HasMany { return $this->hasMany(SaleReturn::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function cashSession(): BelongsTo { return $this->belongsTo(CashSession::class); }
    public function canceller(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }

    public function scopeCounted(Builder $q): Builder { return $q->whereIn('status', self::COUNTED); }
    public function scopeToday(Builder $q): Builder { return $q->whereDate('created_at', today()); }
    public function scopeBetween(Builder $q, $from, $to): Builder
    {
        return $q->whereBetween('created_at', [$from, $to]);
    }

    public function grossProfit(): float
    {
        return round((float) $this->total - (float) $this->cost_total, 2);
    }

    public function isCancellable(): bool { return $this->status === self::COMPLETED; }
}
