<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Category;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ReferenceGenerator;
use App\Services\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Mouvements, expirations, pertes/ajustements et inventaires physiques. */
class StockController extends Controller
{
    public function __construct(private StockService $stock) {}

    // ============================================================ Mouvements

    public function movements(Request $request): View
    {
        $from = CarbonImmutable::parse($request->query('du', today()->subDays(6)->toDateString()))->startOfDay();
        $to = CarbonImmutable::parse($request->query('au', today()->toDateString()))->endOfDay();

        return view('stock.movements', [
            'movements' => StockMovement::with('product:id,name,base_unit_id', 'product.baseUnit:id,abbreviation', 'user:id,name', 'batch:id,batch_number')
                ->whereBetween('created_at', [$from, $to])
                ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
                ->when($request->query('utilisateur'), fn ($q, $u) => $q->where('user_id', $u))
                ->when($request->query('q'), fn ($q, $term) => $q->whereHas('product', fn ($p) => $p->search($term)))
                ->latest('id')->paginate(40)->withQueryString(),
            'from' => $from, 'to' => $to,
            'types' => StockMovement::LABELS,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function expiry(Request $request): View
    {
        $days = (int) $request->query('jours', 60);

        return view('stock.expiry', [
            'expired' => Batch::expired()->with('product.baseUnit')->orderBy('expiry_date')->get(),
            'soon' => Batch::expiringWithin($days)->with('product.baseUnit')->orderBy('expiry_date')->get(),
            'days' => $days,
        ]);
    }

    /** Recherche produit + lots en stock (formulaires d'ajustement). */
    public function lookup(Request $request): JsonResponse
    {
        $products = Product::active()->search(trim((string) $request->query('q')))->orderBy('name')->limit(15)->get();

        return response()->json($products->map(fn (Product $p) => $this->present($p)));
    }

    private function present(Product $p): array
    {
        $p->loadMissing(['baseUnit', 'batches' => fn ($q) => $q->inStock()->fefo()]);

        return [
            'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku,
            'stock' => (float) $p->stock_quantity, 'cost' => (float) $p->cost_price,
            'abbr' => $p->baseUnit?->abbreviation, 'decimal' => (bool) $p->baseUnit?->allows_decimal,
            'batches' => $p->batches->map(fn (Batch $b) => [
                'id' => $b->id,
                'label' => ($b->batch_number ?: '#'.$b->id).($b->expiry_date ? ' · exp. '.$b->expiry_date->format('d/m/Y') : '')
                    .' · reste '.\App\Support\Format::qty($b->remaining_quantity),
                'remaining' => (float) $b->remaining_quantity,
                'expired' => $b->isExpired(),
            ])->values()->all(),
        ];
    }

    // ============================================================ Ajustements

    public function adjustments(Request $request): View
    {
        return view('stock.adjustments.index', [
            'adjustments' => StockAdjustment::with('user:id,name', 'approver:id,name')->withCount('items')
                ->with('items')
                ->when($request->query('statut'), fn ($q, $s) => $q->where('status', $s))
                ->latest()->paginate(25)->withQueryString(),
            'types' => StockAdjustment::TYPES,
            'pending' => StockAdjustment::where('status', 'pending')->count(),
        ]);
    }

    public function createAdjustment(Request $request): View
    {
        $prefill = [];
        if ($request->filled('lot')) {
            $b = Batch::with('product.baseUnit')->findOrFail($request->integer('lot'));
            $prefill[] = [
                'product' => $this->present($b->product),
                'batch_id' => $b->id, 'direction' => 'out', 'quantity' => (float) $b->remaining_quantity, 'reason' => $b->isExpired() ? 'Produit expiré' : '',
            ];
        }

        return view('stock.adjustments.form', [
            'types' => StockAdjustment::TYPES,
            'type' => $request->query('type', $request->filled('lot') ? 'expired' : 'loss'),
            'prefill' => $prefill,
        ]);
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(StockAdjustment::TYPES))],
            'adjustment_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.batch_id' => ['nullable', 'exists:batches,id'],
            'items.*.direction' => ['required', 'in:in,out'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ], ['items.required' => 'Ajoutez au moins un produit.']);

        $adjustment = DB::transaction(function () use ($data, $request) {
            $adj = StockAdjustment::create([
                'reference' => ReferenceGenerator::next('AJ'),
                'type' => $data['type'],
                'adjustment_date' => $data['adjustment_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'user_id' => $request->user()->id,
            ]);
            foreach ($data['items'] as $i) {
                $adj->items()->create([
                    'product_id' => $i['product_id'],
                    'batch_id' => $i['batch_id'] ?? null,
                    'quantity' => ($i['direction'] === 'out' ? -1 : 1) * round((float) $i['quantity'], 3),
                    'unit_cost' => (float) Product::whereKey($i['product_id'])->value('cost_price'),
                    'reason' => $i['reason'] ?? null,
                ]);
            }

            return $adj;
        });

        if ($request->boolean('approve_now') && $request->user()->can('stock.approve_adjustment')) {
            $this->stock->approveAdjustment($adjustment, $request->user());

            return redirect()->route('stock-adjustments.show', $adjustment)->with('success', "Ajustement {$adjustment->reference} appliqué au stock.");
        }

        return redirect()->route('stock-adjustments.show', $adjustment)->with('success', "Ajustement {$adjustment->reference} enregistré : en attente d'approbation par l'administrateur.");
    }

    public function showAdjustment(StockAdjustment $adjustment): View
    {
        return view('stock.adjustments.show', [
            'adjustment' => $adjustment->load('items.product.baseUnit', 'items.batch', 'user', 'approver'),
            'types' => StockAdjustment::TYPES,
        ]);
    }

    public function approveAdjustment(Request $request, StockAdjustment $adjustment): RedirectResponse
    {
        $this->stock->approveAdjustment($adjustment, $request->user());

        return back()->with('success', 'Ajustement approuvé : le stock a été mis à jour.');
    }

    public function rejectAdjustment(Request $request, StockAdjustment $adjustment): RedirectResponse
    {
        abort_unless($adjustment->status === 'pending', 422);
        $adjustment->update(['status' => 'rejected', 'approved_by' => $request->user()->id, 'approved_at' => now(),
            'notes' => trim($adjustment->notes."\nRefusé : ".$request->input('reason', ''))]);

        return back()->with('info', 'Ajustement refusé : aucun changement de stock.');
    }

    // ============================================================ Inventaires

    public function inventories(): View
    {
        return view('stock.inventories.index', [
            'counts' => InventoryCount::with('user:id,name', 'validator:id,name', 'category:id,name')->withCount('items')->latest()->paginate(20),
            'categories' => Category::whereNull('parent_id')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function startInventory(Request $request): RedirectResponse
    {
        $data = $request->validate(['category_id' => ['nullable', 'exists:categories,id'], 'notes' => ['nullable', 'string', 'max:500']]);

        $count = DB::transaction(function () use ($data, $request) {
            $catIds = ! empty($data['category_id'])
                ? Category::where('id', $data['category_id'])->orWhere('parent_id', $data['category_id'])->pluck('id')
                : null;

            $count = InventoryCount::create([
                'reference' => ReferenceGenerator::next('INV'),
                'category_id' => $data['category_id'] ?? null,
                'status' => 'in_progress',
                'started_at' => now(),
                'notes' => $data['notes'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            Product::active()->when($catIds, fn ($q) => $q->whereIn('category_id', $catIds))->orderBy('name')
                ->get(['id', 'stock_quantity', 'cost_price'])
                ->each(fn ($p) => $count->items()->create([
                    'product_id' => $p->id, 'system_quantity' => $p->stock_quantity, 'unit_cost' => $p->cost_price,
                ]));

            return $count;
        });

        return redirect()->route('inventories.show', $count)->with('success', "Inventaire {$count->reference} commencé : {$count->items()->count()} produit(s) à compter.");
    }

    public function showInventory(InventoryCount $inventory): View
    {
        $inventory->load(['items' => fn ($q) => $q->join('products', 'products.id', '=', 'inventory_count_items.product_id')
            ->orderBy('products.location')->orderBy('products.name')->select('inventory_count_items.*'),
            'items.product.baseUnit', 'items.product.units.unit', 'user', 'validator', 'category']);

        return view('stock.inventories.show', ['inventory' => $inventory]);
    }

    public function saveInventory(Request $request, InventoryCount $inventory): RedirectResponse
    {
        abort_unless($inventory->status === 'in_progress', 422);
        $data = $request->validate(['counted' => ['array'], 'counted.*' => ['nullable', 'numeric', 'min:0']]);

        DB::transaction(function () use ($data, $inventory) {
            foreach ($data['counted'] ?? [] as $itemId => $value) {
                $inventory->items()->whereKey($itemId)->update([
                    'counted_quantity' => $value === null || $value === '' ? null : round((float) $value, 3),
                ]);
            }
        });

        if ($request->boolean('validate')) {
            abort_unless($request->user()->can('inventory.validate'), 403);
            $this->stock->validateInventory($inventory, $request->user());

            return redirect()->route('inventories.show', $inventory)->with('success', 'Inventaire validé : les écarts ont été appliqués au stock.');
        }

        return back()->with('success', 'Comptage enregistré.');
    }

    public function cancelInventory(InventoryCount $inventory): RedirectResponse
    {
        abort_unless($inventory->status === 'in_progress', 422);
        $inventory->update(['status' => 'cancelled']);

        return redirect()->route('inventories.index')->with('info', 'Inventaire annulé.');
    }
}
