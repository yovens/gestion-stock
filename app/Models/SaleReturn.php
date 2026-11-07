<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'restock' => 'boolean'];
    }

    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function cashSession(): BelongsTo { return $this->belongsTo(CashSession::class); }
    public function items(): HasMany { return $this->hasMany(SaleReturnItem::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
