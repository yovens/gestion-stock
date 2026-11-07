<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;

/** Format JSON d'un produit pour les formulaires d'achat (bon de commande, réception). */
class PurchaseLookup
{
    public static function present(Product $p): array
    {
        $p->loadMissing('baseUnit', 'units.unit');

        // Dernier coût d'achat par unité de base (lot le plus récent)
        $last = $p->batches()->latest('id')->first(['unit_cost']);

        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'stock' => (float) $p->stock_quantity,
            'min_stock' => (float) $p->min_stock,
            'track_expiry' => (bool) $p->track_expiry,
            'cost' => (float) $p->cost_price,
            'last_cost' => $last ? (float) $last->unit_cost : null,
            'base' => ['name' => $p->baseUnit?->name, 'abbr' => $p->baseUnit?->abbreviation, 'decimal' => (bool) $p->baseUnit?->allows_decimal],
            'units' => $p->units->map(fn (ProductUnit $u) => [
                'id' => $u->id,
                'name' => $u->unit?->name,
                'abbr' => $u->unit?->abbreviation,
                'factor' => (float) $u->conversion_factor,
                'purchase_default' => (bool) $u->is_default_purchase_unit,
            ])->values(),
        ];
    }
}
