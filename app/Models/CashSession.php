<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_amount_htg' => 'decimal:2',
            'opening_amount_usd' => 'decimal:2',
            'expected_amount_htg' => 'decimal:2',
            'expected_amount_usd' => 'decimal:2',
            'counted_amount_htg' => 'decimal:2',
            'counted_amount_usd' => 'decimal:2',
            'difference_htg' => 'decimal:2',
            'difference_usd' => 'decimal:2',
        ];
    }

    public function register(): BelongsTo { return $this->belongsTo(CashRegister::class, 'cash_register_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
    public function sales(): HasMany { return $this->hasMany(Sale::class); }
    public function cashMovements(): HasMany { return $this->hasMany(CashMovement::class); }
    public function customerPayments(): HasMany { return $this->hasMany(CustomerPayment::class); }
    public function expenses(): HasMany { return $this->hasMany(Expense::class); }
    public function saleReturns(): HasMany { return $this->hasMany(SaleReturn::class); }

    public function isOpen(): bool { return $this->status === 'open'; }

    public static function openFor(User $user): ?self
    {
        return static::where('user_id', $user->id)->where('status', 'open')->latest('id')->first();
    }
}
