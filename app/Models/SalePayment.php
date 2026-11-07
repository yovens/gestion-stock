<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    public const METHODS = [
        'cash' => 'Espèces',
        'moncash' => 'MonCash',
        'natcash' => 'NatCash',
        'card' => 'Carte',
        'bank' => 'Virement / chèque',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'exchange_rate' => 'decimal:4', 'amount_htg' => 'decimal:2'];
    }

    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
}
