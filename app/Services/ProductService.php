<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    private const PRICE_FIELDS = ['retail_price', 'wholesale_price', 'wholesale_min_qty', 'cost_price'];
    private const UNIT_PRICE_FIELDS = ['retail_price', 'wholesale_price', 'wholesale_min_qty'];

    public function __construct(private StockService $stock) {}

    /**
     * Crée ou met à jour un produit et ses conditionnements.
     * Sans la permission products.edit_prices, les prix existants ne sont pas modifiables.
     */
    public function save(array $data, ?Product $product, User $user, ?UploadedFile $image = null): Product
    {
        $creating = $product === null;
        $canEditPrices = $creating || $user->can('products.edit_prices');

        return DB::transaction(function () use ($data, $product, $user, $image, $creating, $canEditPrices) {
            $fields = Arr::only($data, [
                'name', 'sku', 'barcode', 'category_id', 'base_unit_id', 'description',
                'retail_price', 'wholesale_price', 'wholesale_min_qty', 'tax_rate',
                'min_stock', 'max_stock', 'location', 'track_expiry', 'expiry_alert_days', 'is_active',
            ]);
            $fields['tax_rate'] = $fields['tax_rate'] ?? 0;
            $fields['brand_id'] = ! empty($data['brand_name'])
                ? Brand::firstOrCreate(['name' => trim($data['brand_name'])])->id
                : null;

            if (! $canEditPrices) {
                $fields = Arr::except($fields, self::PRICE_FIELDS);
            }

            if (! $creating && (int) $fields['base_unit_id'] !== (int) $product->base_unit_id
                && $product->stockMovements()->exists()) {
                throw new BusinessException("L'unité de base ne peut plus changer : le produit a déjà des mouvements de stock.");
            }

            $product ??= new Product(['created_by' => $user->id]);
            $product->fill($fields);

            if ($creating) {
                // Coût de référence avant la première réception
                $product->cost_price = (float) ($data['opening_unit_cost'] ?? $data['cost_price'] ?? 0);
            }

            if ($image) {
                $this->deleteImage($product);
                $product->image = $image->store('products', 'public');
            } elseif (! empty($data['remove_image'])) {
                $this->deleteImage($product);
                $product->image = null;
            }

            $product->save();

            $this->syncUnits($product, $data['units'] ?? [], $canEditPrices);

            if ($creating && (float) ($data['opening_quantity'] ?? 0) > 0) {
                $this->stock->addOpeningStock(
                    $product,
                    (float) $data['opening_quantity'],
                    (float) $product->cost_price,
                    $data['opening_expiry'] ?? null,
                    $user,
                    $data['opening_batch'] ?? null,
                );
            }

            return $product->fresh(['units.unit', 'category', 'baseUnit', 'brand']);
        });
    }

    /** Suppression logique, refusée s'il reste du stock. */
    public function delete(Product $product): void
    {
        if ((float) $product->stock_quantity > 0) {
            throw new BusinessException('Impossible de supprimer un produit qui a encore du stock. Faites d\'abord un ajustement.');
        }

        $product->update(['is_active' => false]);
        $product->delete();
    }

    private function syncUnits(Product $product, array $units, bool $canEditPrices): void
    {
        $existing = $product->units()->get()->keyBy('id');
        $keep = [];
        $hasDefault = false;

        foreach ($units as $u) {
            $attrs = [
                'unit_id' => $u['unit_id'],
                'conversion_factor' => $u['conversion_factor'],
                'barcode' => $u['barcode'] ?? null,
                'retail_price' => $u['retail_price'],
                'wholesale_price' => $u['wholesale_price'] ?? null,
                'wholesale_min_qty' => $u['wholesale_min_qty'] ?? null,
                'is_default_sale_unit' => ! $hasDefault && ! empty($u['is_default_sale_unit']),
            ];
            $hasDefault = $hasDefault || $attrs['is_default_sale_unit'];

            $model = ! empty($u['id']) ? $existing->get((int) $u['id']) : null;
            if ($model) {
                if (! $canEditPrices) {
                    $attrs = Arr::except($attrs, self::UNIT_PRICE_FIELDS);
                }
                $model->update($attrs);
                $keep[] = $model->id;
            } else {
                $keep[] = $product->units()->create($attrs)->id;
            }
        }

        // Les lignes de vente gardent le nom et la contenance : on peut supprimer sans casser l'historique
        $product->units()->whereNotIn('id', $keep)->delete();
    }

    private function deleteImage(Product $product): void
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
    }
}
