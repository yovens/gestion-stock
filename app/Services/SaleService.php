<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Enregistrement d'une vente, annulation et retours.
 * Tout se fait dans UNE transaction : si une ligne échoue (stock insuffisant...), rien n'est enregistré.
 */
class SaleService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param array{
     *   customer_id?: int|null,
     *   sale_type?: 'retail'|'wholesale',
     *   hold?: bool,
     *   discount_amount?: float,
     *   notes?: string|null,
     *   items: array<int, array{product_id: int, product_unit_id?: int|null, quantity: float, discount?: float}>,
     *   payments?: array<int, array{method: string, amount: float, currency?: 'HTG'|'USD', reference?: string|null}>
     * } $data
     */
    public function create(array $data, User $cashier): Sale
    {
        $session = CashSession::openFor($cashier);
        if (! $session) {
            throw new BusinessException('Ouvrez votre caisse avant de vendre.');
        }
        if (empty($data['items'])) {
            throw new BusinessException('Le panier est vide.');
        }

        return DB::transaction(function () use ($data, $cashier, $session) {
            $hold = (bool) ($data['hold'] ?? false);

            $customer = ! empty($data['customer_id'])
                ? Customer::lockForUpdate()->findOrFail($data['customer_id'])
                : null;
            $saleType = $data['sale_type'] ?? ($customer?->type === 'wholesale' ? 'wholesale' : 'retail');

            $products = $this->stock->lockProducts(array_column($data['items'], 'product_id'));
            $units = ProductUnit::with('unit')
                ->whereIn('id', array_filter(array_column($data['items'], 'product_unit_id')))
                ->get()->keyBy('id');

            $rate = ExchangeRate::current();
            $maxDiscountPct = (float) Setting::get('max_discount_percent', 10);
            $freeDiscount = $cashier->can('sales.discount');

            // 1. Calcul des lignes ------------------------------------------------
            $lines = [];
            $gross = 0.0;
            $lineDiscounts = 0.0;

            foreach ($data['items'] as $row) {
                $product = $products[$row['product_id']] ?? throw new BusinessException('Produit introuvable.');
                if (! $product->is_active) {
                    throw new BusinessException("« {$product->name} » n'est plus en vente.");
                }

                $unit = null;
                if (! empty($row['product_unit_id'])) {
                    $unit = $units[$row['product_unit_id']] ?? null;
                    if (! $unit || (int) $unit->product_id !== (int) $product->id) {
                        throw new BusinessException("Conditionnement invalide pour « {$product->name} ».");
                    }
                }

                $qty = round((float) $row['quantity'], 3);
                $unitModel = $unit?->unit ?? $product->baseUnit;
                if ($qty <= 0) {
                    throw new BusinessException("Quantité invalide pour « {$product->name} ».");
                }
                if ($unitModel && ! $unitModel->allows_decimal && floor($qty) != $qty) {
                    throw new BusinessException("« {$product->name} » se vend par {$unitModel->name} entier(e).");
                }

                [$price, $priceType] = $product->priceFor($unit, $qty, $saleType);
                $lineGross = round($price * $qty, 2);
                $discount = round((float) ($row['discount'] ?? 0), 2);
                $this->checkDiscount($discount, $lineGross, $freeDiscount, $maxDiscountPct);

                $factor = $unit ? (float) $unit->conversion_factor : 1.0;

                $lines[] = [
                    'product' => $product,
                    'unit' => $unit,
                    'unit_name' => $unitModel?->name ?? '',
                    'quantity' => $qty,
                    'factor' => $factor,
                    'base_quantity' => round($qty * $factor, 3),
                    'price' => $price,
                    'price_type' => $priceType,
                    'discount' => $discount,
                    'total' => round($lineGross - $discount, 2),
                ];
                $gross += $lineGross;
                $lineDiscounts += $discount;
            }

            $gross = round($gross, 2);
            $globalDiscount = round((float) ($data['discount_amount'] ?? 0), 2);
            $this->checkDiscount($globalDiscount, $gross - $lineDiscounts, $freeDiscount, $maxDiscountPct);
            $total = round($gross - $lineDiscounts - $globalDiscount, 2);

            // 2. Paiements -------------------------------------------------------
            $payments = [];
            $paid = 0.0;
            $cashPaid = 0.0;

            if (! $hold) {
                foreach ($data['payments'] ?? [] as $p) {
                    $amount = round((float) $p['amount'], 2);
                    if ($amount <= 0) continue;

                    $currency = strtoupper($p['currency'] ?? 'HTG');
                    $r = $currency === 'USD' ? $rate : 1.0;
                    $htg = round($amount * $r, 2);

                    $payments[] = [
                        'method' => $p['method'],
                        'currency' => $currency,
                        'amount' => $amount,
                        'exchange_rate' => $r,
                        'amount_htg' => $htg,
                        'transaction_reference' => $p['reference'] ?? null,
                    ];
                    $paid += $htg;
                    if ($p['method'] === 'cash') $cashPaid += $htg;
                }
            }

            $paid = round($paid, 2);
            $change = max(0, round($paid - $total, 2));
            $due = $hold ? 0.0 : max(0, round($total - $paid, 2));

            if ($change > round($cashPaid, 2)) {
                throw new BusinessException('La monnaie ne peut être rendue que sur un paiement en espèces.');
            }

            if ($due > 0) {
                if (! $customer) {
                    throw new BusinessException('Paiement insuffisant. Choisissez un client pour vendre à crédit.');
                }
                if (! $customer->credit_allowed || ! $cashier->can('sales.credit')) {
                    throw new BusinessException("Le crédit n'est pas autorisé pour ce client ou ce caissier.");
                }
                if ((float) $customer->balance + $due > (float) $customer->credit_limit + 0.005) {
                    throw new BusinessException(sprintf(
                        'Limite de crédit dépassée : %s HTG disponibles.',
                        number_format($customer->availableCredit(), 2, '.', ' ')
                    ));
                }
            }

            // 3. Enregistrement ----------------------------------------------------
            $sale = Sale::create([
                'reference' => ReferenceGenerator::next($hold ? 'ATT' : 'V', 6),
                'customer_id' => $customer?->id,
                'cash_session_id' => $session->id,
                'user_id' => $cashier->id,
                'sale_type' => $saleType,
                'status' => $hold ? Sale::HELD : Sale::COMPLETED,
                'subtotal' => $gross,
                'discount_amount' => round($lineDiscounts + $globalDiscount, 2),
                'total' => $total,
                'paid_amount' => $paid,
                'change_amount' => $change,
                'due_amount' => $due,
                'payment_status' => $hold ? 'unpaid' : ($due > 0 ? ($paid > 0 ? 'partial' : 'unpaid') : 'paid'),
                'exchange_rate' => $rate,
                'notes' => $data['notes'] ?? null,
            ]);

            $costTotal = 0.0;

            foreach ($lines as $l) {
                $item = $sale->items()->create([
                    'product_id' => $l['product']->id,
                    'product_unit_id' => $l['unit']?->id,
                    'product_name' => $l['product']->name,
                    'unit_name' => $l['unit_name'],
                    'quantity' => $l['quantity'],
                    'conversion_factor' => $l['factor'],
                    'base_quantity' => $l['base_quantity'],
                    'price_type' => $l['price_type'],
                    'unit_price' => $l['price'],
                    'discount_amount' => $l['discount'],
                    'total' => $l['total'],
                ]);

                if ($hold) continue; // une vente en attente ne touche pas le stock

                $allocations = $this->stock->consume($l['product'], $l['base_quantity'], StockMovement::SALE, $sale, $cashier);

                $lineCost = 0.0;
                foreach ($allocations as $a) {
                    $item->batchAllocations()->create([
                        'batch_id' => $a['batch']->id,
                        'quantity' => $a['quantity'],
                        'unit_cost' => $a['unit_cost'],
                    ]);
                    $lineCost += $a['quantity'] * $a['unit_cost'];
                }

                $item->update(['unit_cost' => round($lineCost / $l['quantity'], 2)]);
                $costTotal += $lineCost;
            }

            $sale->update(['cost_total' => round($costTotal, 2)]);

            foreach ($payments as $p) {
                $sale->payments()->create($p);
            }

            if ($due > 0) {
                $customer->increment('balance', $due);
            }

            return $sale->load('items', 'payments', 'customer');
        });
    }

    /** Finalise une vente mise en attente : on la supprime et on la rejoue avec les paiements. */
    public function completeHeld(Sale $held, array $payments, User $cashier): Sale
    {
        if ($held->status !== Sale::HELD) {
            throw new BusinessException("Cette vente n'est pas en attente.");
        }

        return DB::transaction(function () use ($held, $payments, $cashier) {
            $held->load('items');
            $data = [
                'customer_id' => $held->customer_id,
                'sale_type' => $held->sale_type,
                'notes' => $held->notes,
                'payments' => $payments,
                'discount_amount' => round((float) $held->discount_amount - (float) $held->items->sum('discount_amount'), 2),
                'items' => $held->items->map(fn (SaleItem $i) => [
                    'product_id' => $i->product_id,
                    'product_unit_id' => $i->product_unit_id,
                    'quantity' => (float) $i->quantity,
                    'discount' => (float) $i->discount_amount,
                ])->all(),
            ];
            $held->delete();

            return $this->create($data, $cashier);
        });
    }

    /** Annule entièrement une vente : stock remis dans les mêmes lots, crédit client annulé. */
    public function cancel(Sale $sale, User $user, string $reason): Sale
    {
        if (! $user->can('sales.cancel')) {
            throw new BusinessException("Vous n'avez pas le droit d'annuler une vente.");
        }
        if (trim($reason) === '') {
            throw new BusinessException("Indiquez la raison de l'annulation.");
        }

        return DB::transaction(function () use ($sale, $user, $reason) {
            $sale = Sale::lockForUpdate()->with('items.batchAllocations.batch')->findOrFail($sale->id);
            if (! $sale->isCancellable()) {
                throw new BusinessException('Seule une vente terminée sans retour peut être annulée.');
            }

            $products = $this->stock->lockProducts($sale->items->pluck('product_id'));

            foreach ($sale->items as $item) {
                foreach ($item->batchAllocations as $alloc) {
                    $this->stock->restore($products[$item->product_id], $alloc->batch, (float) $alloc->quantity,
                        StockMovement::SALE_CANCEL, $sale, $user, $reason);
                }
            }

            if ((float) $sale->due_amount > 0 && $sale->customer_id) {
                Customer::lockForUpdate()->find($sale->customer_id)?->decrement('balance', (float) $sale->due_amount);
            }

            $sale->update([
                'status' => Sale::CANCELLED,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $sale;
        });
    }

    /**
     * Retour partiel ou total.
     *
     * @param array<int, array{sale_item_id: int, quantity: float}> $items
     * @param 'cash'|'customer_balance' $refundMethod  customer_balance = on réduit la dette du client
     */
    public function processReturn(Sale $sale, array $items, string $refundMethod, string $reason, bool $restock, User $user): SaleReturn
    {
        if (! $user->can('sales.return')) {
            throw new BusinessException("Vous n'avez pas le droit d'effectuer un retour.");
        }

        return DB::transaction(function () use ($sale, $items, $refundMethod, $reason, $restock, $user) {
            $sale = Sale::lockForUpdate()->with('items.batchAllocations.batch')->findOrFail($sale->id);
            if (! in_array($sale->status, [Sale::COMPLETED, Sale::PARTIALLY_REFUNDED], true)) {
                throw new BusinessException('Cette vente ne peut pas faire l\'objet d\'un retour.');
            }
            if ($refundMethod === 'customer_balance' && ! $sale->customer_id) {
                throw new BusinessException('Aucun client sur cette vente.');
            }

            $session = CashSession::openFor($user);
            if ($refundMethod === 'cash' && ! $session) {
                throw new BusinessException('Ouvrez votre caisse pour rembourser en espèces.');
            }

            $products = $this->stock->lockProducts($sale->items->pluck('product_id'));

            $return = SaleReturn::create([
                'reference' => ReferenceGenerator::next('RET'),
                'sale_id' => $sale->id,
                'cash_session_id' => $session?->id,
                'total' => 0,
                'refund_method' => $refundMethod,
                'reason' => $reason,
                'restock' => $restock,
                'user_id' => $user->id,
                'approved_by' => $user->id,
            ]);

            $total = 0.0;

            foreach ($items as $row) {
                /** @var SaleItem|null $item */
                $item = $sale->items->firstWhere('id', $row['sale_item_id']);
                $qty = round((float) $row['quantity'], 3);
                if (! $item || $qty <= 0) continue;

                if ($qty > $item->returnableQuantity() + 0.0005) {
                    throw new BusinessException("Quantité retournée trop élevée pour « {$item->product_name} ».");
                }

                $factor = (float) $item->conversion_factor;
                $baseQty = round($qty * $factor, 3);
                $amount = round($item->netUnitPrice() * $qty, 2);

                if ($restock) {
                    // On remet dans les lots d'origine, en sautant ce qui a déjà été retourné
                    $skip = round((float) $item->returned_quantity * $factor, 3);
                    $left = $baseQty;
                    foreach ($item->batchAllocations->sortBy('id') as $alloc) {
                        $cap = (float) $alloc->quantity;
                        $used = min($skip, $cap);
                        $skip -= $used;
                        $put = min($left, $cap - $used);
                        if ($put > 0) {
                            $this->stock->restore($products[$item->product_id], $alloc->batch, $put,
                                StockMovement::SALE_RETURN, $return, $user, $reason);
                            $left = round($left - $put, 3);
                        }
                        if ($left <= 0) break;
                    }
                }

                $return->items()->create([
                    'sale_item_id' => $item->id,
                    'quantity' => $qty,
                    'base_quantity' => $baseQty,
                    'unit_price' => round($item->netUnitPrice(), 2),
                    'total' => $amount,
                ]);

                $item->increment('returned_quantity', $qty);
                $total += $amount;
            }

            if ($total <= 0) {
                throw new BusinessException('Aucun article à retourner.');
            }

            $return->update(['total' => round($total, 2)]);

            if ($refundMethod === 'customer_balance') {
                Customer::lockForUpdate()->find($sale->customer_id)?->decrement('balance', round($total, 2));
            }

            $sale->load('items');
            $allReturned = $sale->items->every(fn (SaleItem $i) => $i->returnableQuantity() <= 0.0005);
            $sale->update(['status' => $allReturned ? Sale::REFUNDED : Sale::PARTIALLY_REFUNDED]);

            return $return->load('items');
        });
    }

    private function checkDiscount(float $discount, float $base, bool $free, float $maxPct): void
    {
        if ($discount < 0 || $discount > $base + 0.005) {
            throw new BusinessException('Remise invalide.');
        }
        if (! $free && $base > 0 && $discount / $base * 100 > $maxPct + 0.0001) {
            throw new BusinessException("Remise maximale autorisée : {$maxPct} %.");
        }
    }
}
