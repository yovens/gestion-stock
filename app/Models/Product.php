<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Product extends Model
{
    use LogsActivity, SoftDeletes;

    /** Journal : qui a changé un prix, un seuil ou désactivé le produit, et quand. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'retail_price', 'wholesale_price', 'wholesale_min_qty', 'min_stock', 'is_active'])
            ->logOnlyDirty();
    }

    // stock_quantity et cost_price ne se modifient QUE via StockService
    protected $guarded = ['id', 'stock_quantity'];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'wholesale_min_qty' => 'decimal:3',
            'stock_quantity' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'max_stock' => 'decimal:3',
            'tax_rate' => 'decimal:2',
            'track_expiry' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function baseUnit(): BelongsTo { return $this->belongsTo(Unit::class, 'base_unit_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function units(): HasMany { return $this->hasMany(ProductUnit::class); }
    public function batches(): HasMany { return $this->hasMany(Batch::class); }
    public function stockMovements(): HasMany { return $this->hasMany(StockMovement::class); }
    public function saleItems(): HasMany { return $this->hasMany(SaleItem::class); }

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'min_stock');
    }

    public function scopeOutOfStock(Builder $q): Builder { return $q->where('stock_quantity', '<=', 0); }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) return $q;
        return $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
            ->orWhere('sku', $term)
            ->orWhere('barcode', $term)
            ->orWhereHas('units', fn ($u) => $u->where('barcode', $term)));
    }

    /**
     * Prix applicable pour une quantité donnée.
     * Prix gros si vente en gros OU si la quantité atteint le minimum gros.
     *
     * @return array{0: float, 1: string} [prix unitaire, 'retail'|'wholesale']
     */
    public function priceFor(?ProductUnit $unit, float $quantity, string $saleType = 'retail'): array
    {
        $retail = (float) ($unit ? $unit->retail_price : $this->retail_price);
        $wholesale = $unit ? $unit->wholesale_price : $this->wholesale_price;
        $minQty = $unit ? $unit->wholesale_min_qty : $this->wholesale_min_qty;

        if ($wholesale !== null && (float) $wholesale > 0
            && ($saleType === 'wholesale' || ($minQty !== null && $quantity >= (float) $minQty))) {
            return [(float) $wholesale, 'wholesale'];
        }

        return [$retail, 'retail'];
    }

    /** Quantité réellement vendable (lots non expirés). */
    public function sellableQuantity(): float
    {
        return (float) $this->batches()->sellable()->sum('remaining_quantity');
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock_quantity <= (float) $this->min_stock;
    }

    public function marginPercent(): ?float
    {
        $cost = (float) $this->cost_price;
        return $cost > 0 ? round(((float) $this->retail_price - $cost) / $cost * 100, 1) : null;
    }
}