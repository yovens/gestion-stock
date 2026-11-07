<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use App\Models\Supplier;
use App\Services\PurchaseLookup;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public const STATUSES = [
        'draft' => 'Brouillon',
        'sent' => 'Envoyé',
        'partially_received' => 'Reçu en partie',
        'received' => 'Reçu',
        'cancelled' => 'Annulé',
    ];

    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request): View
    {
        return view('purchases.orders.index', [
            'orders' => PurchaseOrder::with('supplier:id,name', 'user:id,name')->withCount('items')
                ->when($request->query('statut'), fn ($q, $s) => $q->where('status', $s))
                ->when($request->query('fournisseur'), fn ($q, $s) => $q->where('supplier_id', $s))
                ->latest('order_date')->latest('id')->paginate(25)->withQueryString(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'statuses' => self::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        // « Commander les produits en stock bas » : pré-remplit avec la quantité pour atteindre le max (ou 2× le min)
        $lines = [];
        if ($request->boolean('stock_bas')) {
            $lines = Product::active()->whereColumn('stock_quantity', '<=', 'min_stock')->with('units.unit', 'baseUnit')->get()
                ->map(function (Product $p) {
                    $target = (float) ($p->max_stock ?: (float) $p->min_stock * 2);
                    $need = max(1, $target - (float) $p->stock_quantity);
                    $unit = $p->units->firstWhere('is_default_purchase_unit', true) ?? $p->units->sortByDesc('conversion_factor')->first();
                    $qty = $unit ? ceil($need / (float) $unit->conversion_factor) : ceil($need);

                    return [
                        'product' => PurchaseLookup::present($p),
                        'product_unit_id' => $unit?->id,
                        'quantity' => $qty,
                        'unit_cost' => round((float) $p->cost_price * ($unit ? (float) $unit->conversion_factor : 1), 2),
                    ];
                })->values()->all();
        }

        return view('purchases.orders.form', [
            'order' => new PurchaseOrder(['order_date' => today(), 'supplier_id' => $request->integer('fournisseur') ?: null]),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'lines' => $lines,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $order = $this->purchases->saveOrder($this->validated($request), $request->user());

        return redirect()->route('purchase-orders.show', $order)->with('success', "Bon de commande {$order->reference} créé.");
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): View
    {
        return view('purchases.orders.show', [
            'order' => $purchaseOrder->load('supplier', 'user', 'items.product.baseUnit', 'items.productUnit.unit', 'receipts'),
            'statuses' => self::STATUSES,
            'print' => $request->boolean('imprimer'),
            'store' => ['name' => Setting::get('store_name', config('app.name')), 'address' => Setting::get('store_address'), 'phone' => Setting::get('store_phone'), 'nif' => Setting::get('store_nif')],
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->with('error', 'Seul un brouillon peut être modifié.');
        }
        $purchaseOrder->load('items.product', 'items.productUnit');

        return view('purchases.orders.form', [
            'order' => $purchaseOrder,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'lines' => $purchaseOrder->items->map(fn ($i) => [
                'product' => PurchaseLookup::present($i->product),
                'product_unit_id' => $i->product_unit_id,
                'quantity' => (float) $i->quantity,
                'unit_cost' => (float) $i->unit_cost,
            ])->all(),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchases->saveOrder($this->validated($request), $request->user(), $purchaseOrder);

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Bon de commande mis à jour.');
    }

    public function status(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchases->setOrderStatus($purchaseOrder, (string) $request->input('status'));

        return back()->with('success', 'Statut : '.self::STATUSES[$purchaseOrder->status].'.');
    }

    /** Recherche produit pour les lignes d'achat. */
    public function products(Request $request): JsonResponse
    {
        $products = Product::active()->with('baseUnit', 'units.unit')
            ->search(trim((string) $request->query('q')))
            ->orderBy('name')->limit(15)->get();

        return response()->json($products->map(fn ($p) => PurchaseLookup::present($p)));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_unit_id' => ['nullable', 'exists:product_units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ], ['items.required' => 'Ajoutez au moins un produit.']);
    }
}
