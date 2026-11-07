<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Journal immuable : chaque entrée/sortie de stock. */
class StockMovement extends Model
{
    const UPDATED_AT = null;

    public const OPENING = 'opening';
    public const PURCHASE = 'purchase';
    public const PURCHASE_RETURN = 'purchase_return';
    public const SALE = 'sale';
    public const SALE_CANCEL = 'sale_cancel';
    public const SALE_RETURN = 'sale_return';
    public const ADJUSTMENT_IN = 'adjustment_in';
    public const ADJUSTMENT_OUT = 'adjustment_out';
    public const INVENTORY_IN = 'inventory_in';
    public const INVENTORY_OUT = 'inventory_out';

    public const LABELS = [
        self::OPENING => 'Stock initial',
        self::PURCHASE => 'Réception fournisseur',
        self::PURCHASE_RETURN => 'Retour fournisseur',
        self::SALE => 'Vente',
        self::SALE_CANCEL => 'Annulation de vente',
        self::SALE_RETURN => 'Retour client',
        self::ADJUSTMENT_IN => 'Ajustement (+)',
        self::ADJUSTMENT_OUT => 'Perte / ajustement (−)',
        self::INVENTORY_IN => 'Inventaire (+)',
        self::INVENTORY_OUT => 'Inventaire (−)',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_before' => 'decimal:3',
            'quantity_after' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function batch(): BelongsTo { return $this->belongsTo(Batch::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reference(): MorphTo { return $this->morphTo(); }

    public function label(): string { return self::LABELS[$this->type] ?? $this->type; }
    public function isEntry(): bool { return (float) $this->quantity > 0; }
}
