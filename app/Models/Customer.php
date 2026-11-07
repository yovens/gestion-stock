<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    // balance ne se modifie que via SaleService / paiements
    protected $guarded = ['id', 'balance'];

    protected function casts(): array
    {
        return [
            'credit_allowed' => 'boolean',
            'credit_limit' => 'decimal:2',
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany { return $this->hasMany(Sale::class); }
    public function payments(): HasMany { return $this->hasMany(CustomerPayment::class); }

    public function scopeWithDebt(Builder $q): Builder { return $q->where('balance', '>', 0); }
    public function scopeWholesale(Builder $q): Builder { return $q->where('type', 'wholesale'); }

    public function availableCredit(): float
    {
        return $this->credit_allowed ? max(0, round((float) $this->credit_limit - (float) $this->balance, 2)) : 0.0;
    }
}
