<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\InsufficientStockException;
use App\Models\Batch;
use App\Models\GoodsReceipt;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * SEUL point d'entrée pour modifier le stock.
 * Toutes les quantités sont en unité de base du produit.
 * Les méthodes publiques doivent être appelées dans une transaction (elles en ouvrent une sinon).
 */
class StockService
{
    private const EPSILON = 0.0005;

    /**
     * Verrouille les produits dans l'ordre des id (évite les interblocages entre deux caisses).
     *
     * @return Collection<int, Product> indexée par id
     */
    public function lockProducts(iterable $ids): Collection
    {
        $ids = collect($ids)->filter()->unique()->sort()->values();

        return Product::with('baseUnit')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /** Stock initial lors de la mise en service (un lot par saisie). */
    public function addOpeningStock(Product $product, float $quantity, float $unitCost, ?string $expiryDate, User $user, ?string $batchNumber = null): Batch
    {
        return DB::transaction(function () use ($product, $quantity, $unitCost, $expiryDate, $user, $batchNumber) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $batch = $this->createBatch($product, $quantity, $unitCost, $expiryDate, $batchNumber);
            $this->updateAverageCost($product, $quantity, $unitCost);
            $this->record($product, $batch, StockMovement::OPENING, $quantity, $unitCost, null, $user, 'Stock initial');

            return $batch;
        });
    }

    /**
     * Valide une réception fournisseur : crée les lots, entre le stock,
     * répartit le transport sur le coût, met à jour le coût moyen et la dette fournisseur.
     */
    public function receive(GoodsReceipt $receipt, User $user): void
    {
        DB::transaction(function () use ($receipt, $user) {
            $receipt->loadMissing('items');
            if ($receipt->items->isEmpty()) {
                throw new BusinessException('La réception ne contient aucun article.');
            }
            if ($receipt->items->contains(fn ($i) => $i->batch()->exists())) {
                throw new BusinessException('Cette réception a déjà été entrée en stock.');
            }

            $products = $this->lockProducts($receipt->items->pluck('product_id'));
            $subtotal = (float) $receipt->items->sum('total');
            $transport = (float) $receipt->transport_cost;

            foreach ($receipt->items as $item) {
                $product = $products[$item->product_id];
                $factor = (float) $item->conversion_factor ?: 1.0;
                $baseQty = round((float) $item->quantity * $factor, 3);
                if ($baseQty <= 0) {
                    throw new BusinessException("Quantité invalide pour « {$product->name} ».");
                }

                // Coût par unité de base, transport inclus au prorata de la valeur
                $share = $subtotal > 0 ? $transport * (float) $item->total / $subtotal : 0;
                $unitCost = round(((float) $item->total + $share) / $baseQty, 2);

                $item->update(['base_quantity' => $baseQty]);

                $batch = $this->createBatch($product, $baseQty, $unitCost, $item->expiry_date?->toDateString(), $item->batch_number, $item->manufacture_date?->toDateString(), $item->id);
                $this->updateAverageCost($product, $baseQty, $unitCost);
                $this->record($product, $batch, StockMovement::PURCHASE, $baseQty, $unitCost, $receipt, $user);

                if ($receipt->purchase_order_id) {
                    $receipt->purchaseOrder->items()
                        ->where('product_id', $item->product_id)
                        ->increment('received_quantity', (float) $item->quantity);
                }
            }

            $total = round($subtotal + $transport, 2);
            $receipt->update([
                'subtotal' => $subtotal,
                'total' => $total,
                'payment_status' => $this->paymentStatus($total, (float) $receipt->paid_amount),
            ]);

            $receipt->supplier()->lockForUpdate()->first()
                ->increment('balance', max(0, $total - (float) $receipt->paid_amount));

            if ($po = $receipt->purchaseOrder) {
                $po->load('items');
                $complete = $po->items->every(fn ($i) => (float) $i->received_quantity >= (float) $i->quantity);
                $po->update(['status' => $complete ? 'received' : 'partially_received']);
            }
        });
    }

    /**
     * Sort une quantité du stock en FEFO (lot qui expire le plus tôt en premier).
     * Par défaut, les lots expirés sont ignorés : la caisse ne peut pas les vendre.
     *
     * @param  Product  $product  doit déjà être verrouillé (lockProducts)
     * @return array<int, array{batch: Batch, quantity: float, unit_cost: float}>
     */
    public function consume(Product $product, float $quantity, string $type, ?Model $reference, User $user, ?string $reason = null, bool $includeExpired = false): array
    {
        $quantity = round($quantity, 3);
        if ($quantity <= 0) {
            throw new BusinessException('La quantité doit être supérieure à zéro.');
        }

        $query = $product->batches()->fefo()->lockForUpdate();
        $includeExpired ? $query->inStock() : $query->sellable();
        $batches = $query->get();

        $available = (float) $batches->sum(fn (Batch $b) => (float) $b->remaining_quantity);
        if ($available + self::EPSILON < $quantity) {
            throw new InsufficientStockException($product, $available, $quantity);
        }

        $allocations = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= self::EPSILON) break;

            $take = round(min($remaining, (float) $batch->remaining_quantity), 3);
            $batch->remaining_quantity = round((float) $batch->remaining_quantity - $take, 3);
            $batch->save();

            $this->record($product, $batch, $type, -$take, (float) $batch->unit_cost, $reference, $user, $reason);

            $allocations[] = ['batch' => $batch, 'quantity' => $take, 'unit_cost' => (float) $batch->unit_cost];
            $remaining = round($remaining - $take, 3);
        }

        return $allocations;
    }

    /** Remet une quantité dans un lot précis (annulation, retour client). */
    public function restore(Product $product, Batch $batch, float $quantity, string $type, ?Model $reference, User $user, ?string $reason = null): void
    {
        $quantity = round($quantity, 3);
        if ($quantity <= 0) return;

        $batch = Batch::lockForUpdate()->findOrFail($batch->id);
        $batch->remaining_quantity = round((float) $batch->remaining_quantity + $quantity, 3);
        $batch->save();

        $this->record($product, $batch, $type, $quantity, (float) $batch->unit_cost, $reference, $user, $reason);
    }

    /** Approuve un ajustement (perte, casse, expiré...) et l'applique au stock. */
    public function approveAdjustment(StockAdjustment $adjustment, User $approver): void
    {
        DB::transaction(function () use ($adjustment, $approver) {
            $adjustment = StockAdjustment::lockForUpdate()->with('items')->findOrFail($adjustment->id);
            if ($adjustment->status !== 'pending') {
                throw new BusinessException('Cet ajustement a déjà été traité.');
            }

            $products = $this->lockProducts($adjustment->items->pluck('product_id'));
            $label = StockAdjustment::TYPES[$adjustment->type] ?? $adjustment->type;

            foreach ($adjustment->items as $item) {
                $product = $products[$item->product_id];
                $qty = (float) $item->quantity;
                $reason = $item->reason ?: $label;

                if ($qty < 0) {
                    if ($item->batch_id) {
                        $batch = Batch::lockForUpdate()->findOrFail($item->batch_id);
                        if ((float) $batch->remaining_quantity + self::EPSILON < abs($qty)) {
                            throw new InsufficientStockException($product, (float) $batch->remaining_quantity, abs($qty));
                        }
                        $batch->remaining_quantity = round((float) $batch->remaining_quantity + $qty, 3);
                        $batch->save();
                        $this->record($product, $batch, StockMovement::ADJUSTMENT_OUT, $qty, (float) $batch->unit_cost, $adjustment, $approver, $reason);
                        $item->update(['unit_cost' => $batch->unit_cost]);
                    } else {
                        // Pertes : on peut retirer aussi des lots expirés
                        $alloc = $this->consume($product, abs($qty), StockMovement::ADJUSTMENT_OUT, $adjustment, $approver, $reason, includeExpired: true);
                        $item->update(['unit_cost' => $this->weightedCost($alloc)]);
                    }
                } elseif ($qty > 0) {
                    if ($item->batch_id) {
                        $batch = Batch::findOrFail($item->batch_id);
                        $this->restore($product, $batch, $qty, StockMovement::ADJUSTMENT_IN, $adjustment, $approver, $reason);
                    } else {
                        $cost = (float) ((float) $item->unit_cost > 0 ? $item->unit_cost : $product->cost_price);
                        $batch = $this->createBatch($product, $qty, $cost, null, 'AJ');
                        $this->record($product, $batch, StockMovement::ADJUSTMENT_IN, $qty, $cost, $adjustment, $approver, $reason);
                    }
                }
            }

            $adjustment->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);
        });
    }

    /** Valide un inventaire physique : chaque écart devient un mouvement de stock. */
    public function validateInventory(InventoryCount $count, User $validator): void
    {
        DB::transaction(function () use ($count, $validator) {
            $count = InventoryCount::lockForUpdate()->with('items')->findOrFail($count->id);
            if ($count->status !== 'in_progress') {
                throw new BusinessException('Cet inventaire est déjà clôturé.');
            }
            if ($count->items->contains(fn ($i) => $i->counted_quantity === null)) {
                throw new BusinessException('Tous les produits doivent être comptés avant la validation.');
            }

            $products = $this->lockProducts($count->items->pluck('product_id'));

            foreach ($count->items as $item) {
                $product = $products[$item->product_id];
                // Écart calculé sur le stock ACTUEL (des ventes ont pu avoir lieu pendant le comptage)
                $diff = round((float) $item->counted_quantity - (float) $product->stock_quantity, 3);
                $item->update(['system_quantity' => $product->stock_quantity, 'difference' => $diff, 'unit_cost' => $product->cost_price]);

                if ($diff < -self::EPSILON) {
                    $this->consume($product, abs($diff), StockMovement::INVENTORY_OUT, $count, $validator, 'Écart d\'inventaire', includeExpired: true);
                } elseif ($diff > self::EPSILON) {
                    $batch = $this->createBatch($product, $diff, (float) $product->cost_price, null, 'INV');
                    $this->record($product, $batch, StockMovement::INVENTORY_IN, $diff, (float) $product->cost_price, $count, $validator, 'Écart d\'inventaire');
                }
            }

            $count->update(['status' => 'completed', 'completed_at' => now(), 'validated_by' => $validator->id]);
        });
    }

    /** @param array<int, array{quantity: float, unit_cost: float}> $allocations */
    public function weightedCost(array $allocations): float
    {
        $qty = array_sum(array_column($allocations, 'quantity'));
        if ($qty <= 0) return 0.0;
        $value = array_sum(array_map(fn ($a) => $a['quantity'] * $a['unit_cost'], $allocations));

        return round($value / $qty, 2);
    }

    // ------------------------------------------------------------------

    private function createBatch(Product $product, float $qty, float $unitCost, ?string $expiry, ?string $number = null, ?string $manufactured = null, ?int $receiptItemId = null): Batch
    {
        return Batch::create([
            'product_id' => $product->id,
            'goods_receipt_item_id' => $receiptItemId,
            'batch_number' => $number,
            'manufacture_date' => $manufactured,
            'expiry_date' => $expiry,
            'initial_quantity' => $qty,
            'remaining_quantity' => $qty,
            'unit_cost' => $unitCost,
        ]);
    }

    /** Coût moyen pondéré, à appeler AVANT record() (qui augmente stock_quantity). */
    private function updateAverageCost(Product $product, float $qty, float $unitCost): void
    {
        $current = max(0, (float) $product->stock_quantity);
        $total = $current + $qty;
        $product->cost_price = $total > 0
            ? round(($current * (float) $product->cost_price + $qty * $unitCost) / $total, 2)
            : $unitCost;
    }

    private function record(Product $product, ?Batch $batch, string $type, float $qty, float $unitCost, ?Model $reference, User $user, ?string $reason = null): StockMovement
    {
        $before = (float) $product->stock_quantity;
        $after = round($before + $qty, 3);

        $product->stock_quantity = $after;
        $product->save();

        return StockMovement::create([
            'product_id' => $product->id,
            'batch_id' => $batch?->id,
            'type' => $type,
            'quantity' => round($qty, 3),
            'quantity_before' => $before,
            'quantity_after' => $after,
            'unit_cost' => $unitCost,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'reason' => $reason,
            'user_id' => $user->id,
        ]);
    }

    private function paymentStatus(float $total, float $paid): string
    {
        return $paid <= 0 ? 'unpaid' : ($paid + 0.005 >= $total ? 'paid' : 'partial');
    }
}
