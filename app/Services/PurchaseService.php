<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bons de commande, réceptions de marchandise et paiements fournisseurs.
 * L'entrée en stock elle-même est faite par StockService::receive().
 */
class PurchaseService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param array{supplier_id: int, order_date: string, expected_date?: ?string, notes?: ?string,
     *   items: array<int, array{product_id: int, product_unit_id?: ?int, quantity: float, unit_cost: float}>} $data
     */
    public function saveOrder(array $data, User $user, ?PurchaseOrder $order = null): PurchaseOrder
    {
        if ($order && $order->status !== 'draft') {
            throw new BusinessException('Seul un bon de commande en brouillon peut être modifié.');
        }

        return DB::transaction(function () use ($data, $user, $order) {
            $lines = $this->buildLines($data['items']);

            $attrs = [
                'supplier_id' => $data['supplier_id'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total' => round($lines->sum('total'), 2),
            ];

            if ($order) {
                $order->update($attrs);
                $order->items()->delete();
            } else {
                $order = PurchaseOrder::create($attrs + [
                    'reference' => ReferenceGenerator::next('BC'),
                    'status' => 'draft',
                    'user_id' => $user->id,
                ]);
            }

            foreach ($lines as $l) {
                $order->items()->create([
                    'product_id' => $l['product_id'],
                    'product_unit_id' => $l['product_unit_id'],
                    'quantity' => $l['quantity'],
                    'conversion_factor' => $l['conversion_factor'],
                    'unit_cost' => $l['unit_cost'],
                    'total' => $l['total'],
                ]);
            }

            return $order->load('items');
        });
    }

    public function setOrderStatus(PurchaseOrder $order, string $status): PurchaseOrder
    {
        $allowed = [
            'draft' => ['sent', 'cancelled'],
            'sent' => ['cancelled', 'draft'],
            'partially_received' => ['received'], // clôturer un reliquat qui ne viendra pas
        ];
        if (! in_array($status, $allowed[$order->status] ?? [], true)) {
            throw new BusinessException('Ce changement de statut n\'est pas possible.');
        }
        $order->update(['status' => $status]);

        return $order;
    }

    /**
     * Enregistre une réception, l'entre en stock (lots + dates d'expiration) et paie éventuellement une avance.
     *
     * @param array{supplier_id: int, purchase_order_id?: ?int, supplier_invoice_number?: ?string, received_date: string,
     *   transport_cost?: ?float, notes?: ?string, paid_amount?: ?float, payment_method?: ?string, payment_reference?: ?string,
     *   items: array<int, array{product_id: int, product_unit_id?: ?int, quantity: float, unit_cost: float,
     *     batch_number?: ?string, manufacture_date?: ?string, expiry_date?: ?string}>} $data
     */
    public function receive(array $data, User $user, ?string $attachment = null): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $user, $attachment) {
            $order = null;
            if (! empty($data['purchase_order_id'])) {
                $order = PurchaseOrder::lockForUpdate()->findOrFail($data['purchase_order_id']);
                if (in_array($order->status, ['received', 'cancelled'], true)) {
                    throw new BusinessException('Ce bon de commande est déjà clôturé.');
                }
                if ((int) $order->supplier_id !== (int) $data['supplier_id']) {
                    throw new BusinessException('Le fournisseur ne correspond pas au bon de commande.');
                }
            }

            $lines = $this->buildLines($data['items'], withBatch: true);

            // Produit avec gestion des lots : la date d'expiration est obligatoire
            $products = Product::whereIn('id', $lines->pluck('product_id'))->get()->keyBy('id');
            foreach ($lines as $l) {
                $p = $products[$l['product_id']];
                if ($p->track_expiry && empty($l['expiry_date'])) {
                    throw new BusinessException("Date d'expiration manquante pour « {$p->name} ».");
                }
            }

            $receipt = GoodsReceipt::create([
                'reference' => ReferenceGenerator::next('REC'),
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $order?->id,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'received_date' => $data['received_date'],
                'transport_cost' => round((float) ($data['transport_cost'] ?? 0), 2),
                'subtotal' => 0,
                'total' => 0,
                'paid_amount' => 0,
                'attachment' => $attachment,
                'notes' => $data['notes'] ?? null,
                'user_id' => $user->id,
            ]);

            foreach ($lines as $l) {
                $receipt->items()->create([
                    'product_id' => $l['product_id'],
                    'product_unit_id' => $l['product_unit_id'],
                    'quantity' => $l['quantity'],
                    'conversion_factor' => $l['conversion_factor'],
                    'base_quantity' => round($l['quantity'] * $l['conversion_factor'], 3),
                    'unit_cost' => $l['unit_cost'],
                    'total' => $l['total'],
                    'batch_number' => $l['batch_number'] ?? null,
                    'manufacture_date' => $l['manufacture_date'] ?? null,
                    'expiry_date' => $l['expiry_date'] ?? null,
                ]);
            }

            // Entrée en stock, coût moyen, dette fournisseur (+ total)
            $this->stock->receive($receipt->load('items', 'purchaseOrder'), $user);

            $paid = round((float) ($data['paid_amount'] ?? 0), 2);
            if ($paid > 0) {
                if (! $user->can('supplier_payments.manage')) {
                    throw new BusinessException("Vous n'avez pas le droit d'enregistrer un paiement fournisseur.");
                }
                $this->pay($receipt->supplier, $paid, $data['payment_method'] ?? 'cash', $user,
                    $data['payment_reference'] ?? null, $data['received_date'], $receipt->fresh());
            }

            return $receipt->fresh(['items.product', 'supplier']);
        });
    }

    /**
     * Paiement fournisseur. Sans réception précisée, il solde les factures les plus anciennes d'abord.
     * Le surplus éventuel (dette initiale saisie à la création du fournisseur) est enregistré sans facture.
     *
     * @return Collection<int, SupplierPayment>
     */
    public function pay(Supplier $supplier, float $amount, string $method, User $user, ?string $reference = null,
                        ?string $date = null, ?GoodsReceipt $receipt = null, ?string $notes = null): Collection
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new BusinessException('Le montant doit être supérieur à zéro.');
        }

        return DB::transaction(function () use ($supplier, $amount, $method, $user, $reference, $date, $receipt, $notes) {
            $supplier = Supplier::lockForUpdate()->findOrFail($supplier->id);
            if ($amount > (float) $supplier->balance + 0.005) {
                throw new BusinessException(sprintf('Le paiement dépasse la dette envers ce fournisseur (%s HTG).',
                    number_format((float) $supplier->balance, 2, ',', ' ')));
            }

            $targets = $receipt
                ? collect([GoodsReceipt::lockForUpdate()->findOrFail($receipt->id)])
                : GoodsReceipt::where('supplier_id', $supplier->id)->where('payment_status', '!=', 'paid')
                    ->orderBy('received_date')->orderBy('id')->lockForUpdate()->get();

            if ($receipt && (int) $receipt->supplier_id !== (int) $supplier->id) {
                throw new BusinessException('Cette réception appartient à un autre fournisseur.');
            }

            $left = $amount;
            $payments = collect();
            $base = [
                'supplier_id' => $supplier->id, 'method' => $method, 'reference' => $reference,
                'payment_date' => $date ?? today()->toDateString(), 'notes' => $notes, 'user_id' => $user->id,
            ];

            foreach ($targets as $r) {
                if ($left <= 0) break;
                $part = round(min($left, $r->dueAmount()), 2);
                if ($part <= 0) continue;

                $paid = round((float) $r->paid_amount + $part, 2);
                $r->update([
                    'paid_amount' => $paid,
                    'payment_status' => $paid + 0.005 >= (float) $r->total ? 'paid' : 'partial',
                ]);
                $payments->push(SupplierPayment::create($base + ['goods_receipt_id' => $r->id, 'amount' => $part]));
                $left = round($left - $part, 2);
            }

            if ($receipt && $left > 0) {
                throw new BusinessException('Le montant dépasse ce qui reste à payer sur cette réception.');
            }
            if ($left > 0) {
                $payments->push(SupplierPayment::create($base + ['goods_receipt_id' => null, 'amount' => $left]));
            }

            $supplier->decrement('balance', $amount);

            return $payments;
        });
    }

    /** Lignes de commande/réception à partir d'un BC : quantités restant à recevoir. */
    public function remainingLines(PurchaseOrder $order): array
    {
        return $order->items()->with('product.units.unit', 'product.baseUnit', 'productUnit.unit')->get()
            ->filter(fn ($i) => (float) $i->quantity - (float) $i->received_quantity > 0.0005)
            ->map(fn ($i) => [
                'product' => PurchaseLookup::present($i->product),
                'product_unit_id' => $i->product_unit_id,
                'quantity' => round((float) $i->quantity - (float) $i->received_quantity, 3),
                'unit_cost' => (float) $i->unit_cost,
            ])->values()->all();
    }

    // ------------------------------------------------------------------

    private function buildLines(array $items, bool $withBatch = false): Collection
    {
        if (empty($items)) {
            throw new BusinessException('Ajoutez au moins un produit.');
        }

        $units = ProductUnit::whereIn('id', array_filter(array_column($items, 'product_unit_id')))->get()->keyBy('id');

        return collect($items)->map(function ($row) use ($units, $withBatch) {
            $unit = ! empty($row['product_unit_id']) ? $units[$row['product_unit_id']] ?? null : null;
            if (! empty($row['product_unit_id']) && (! $unit || (int) $unit->product_id !== (int) $row['product_id'])) {
                throw new BusinessException('Conditionnement invalide sur une ligne.');
            }
            $qty = round((float) $row['quantity'], 3);
            $cost = round((float) $row['unit_cost'], 2);
            if ($qty <= 0) throw new BusinessException('Une quantité est invalide.');

            $line = [
                'product_id' => (int) $row['product_id'],
                'product_unit_id' => $unit?->id,
                'quantity' => $qty,
                'conversion_factor' => $unit ? (float) $unit->conversion_factor : 1.0,
                'unit_cost' => $cost,
                'total' => round($qty * $cost, 2),
            ];

            return $withBatch ? $line + [
                'batch_number' => $row['batch_number'] ?? null,
                'manufacture_date' => $row['manufacture_date'] ?? null,
                'expiry_date' => $row['expiry_date'] ?? null,
            ] : $line;
        });
    }
}
