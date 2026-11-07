<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public const STATUSES = [
        'low' => 'Stock bas',
        'out' => 'Rupture',
        'expiring' => 'Expire bientôt',
        'inactive' => 'Inactifs',
    ];

    public function __construct(private ProductService $products) {}

    public function index(Request $request): View
    {
        $filters = $request->only('q', 'category', 'status', 'sort');
        $expiryDays = (int) \App\Models\Setting::get('expiry_alert_days', 30);

        $query = Product::query()
            ->with('category:id,name,color', 'baseUnit:id,abbreviation', 'units.unit:id,name,abbreviation')
            ->withSum(['batches as sellable_qty' => fn ($q) => $q->sellable()], 'remaining_quantity')
            ->withMin(['batches as next_expiry' => fn ($q) => $q->inStock()->whereNotNull('expiry_date')], 'expiry_date')
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, fn ($q, $cat) => $q->whereIn('category_id',
                Category::where('id', $cat)->orWhere('parent_id', $cat)->pluck('id')))
            ->when(($filters['status'] ?? null) !== 'inactive', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'low', fn ($q) => $q->lowStock())
            ->when(($filters['status'] ?? null) === 'out', fn ($q) => $q->outOfStock())
            ->when(($filters['status'] ?? null) === 'expiring', fn ($q) => $q->whereHas('batches',
                fn ($b) => $b->inStock()->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', today()->addDays($expiryDays))));

        match ($filters['sort'] ?? 'name') {
            'stock' => $query->orderBy('stock_quantity'),
            'price' => $query->orderByDesc('retail_price'),
            'recent' => $query->latest(),
            default => $query->orderBy('name'),
        };

        return view('products.index', [
            'products' => $query->paginate(25)->withQueryString(),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'parent_id']),
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'counts' => [
                'all' => Product::active()->count(),
                'low' => Product::active()->lowStock()->count(),
                'out' => Product::active()->outOfStock()->count(),
                'inactive' => Product::where('is_active', false)->count(),
            ],
            'stockValue' => (float) Product::active()->sum(DB::raw('stock_quantity * cost_price')),
            'expiryDays' => $expiryDays,
        ]);
    }

    public function create(Request $request): View
    {
        $copy = $request->filled('copier') ? Product::with('units', 'brand')->find($request->integer('copier')) : null;

        return view('products.form', $this->formData($copy ? $copy->replicate(['sku', 'barcode', 'image', 'stock_quantity']) : new Product([
            'is_active' => true, 'track_expiry' => true, 'expiry_alert_days' => 30, 'min_stock' => 0,
        ]), $copy));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->products->save($request->validated(), null, $request->user(), $request->file('image'));

        return $request->boolean('add_another')
            ? redirect()->route('products.create')->with('success', "« {$product->name} » enregistré. Produit suivant :")
            : redirect()->route('products.show', $product)->with('success', "« {$product->name} » enregistré.");
    }

    public function show(Product $product): View
    {
        $product->load('category.parent', 'brand', 'baseUnit', 'units.unit', 'creator:id,name')
            ->loadSum(['batches as sellable_qty' => fn ($q) => $q->sellable()], 'remaining_quantity');

        $since = now()->subDays(30);
        $sold30 = (float) $product->saleItems()
            ->whereHas('sale', fn ($q) => $q->counted()->where('created_at', '>=', $since))
            ->sum('base_quantity');

        return view('products.show', [
            'product' => $product,
            'batches' => $product->batches()->inStock()->fefo()->get(),
            'movements' => $product->stockMovements()->with('user:id,name', 'batch:id,batch_number')
                ->latest('id')->paginate(15),
            'sold30' => $sold30,
            'daysOfStock' => $sold30 > 0 ? (int) floor((float) $product->stock_quantity / ($sold30 / 30)) : null,
            'labels' => StockMovement::LABELS,
        ]);
    }

    public function edit(Product $product): View
    {
        return view('products.form', $this->formData($product->load('units', 'brand')));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->products->save($request->validated(), $product, $request->user(), $request->file('image'));

        return redirect()->route('products.show', $product)->with('success', 'Produit mis à jour.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', $product->is_active ? 'Produit réactivé.' : 'Produit désactivé : il n\'apparaît plus en caisse.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->products->delete($product);

        return redirect()->route('products.index')->with('success', "« {$product->name} » supprimé.");
    }

    private function formData(Product $product, ?Product $copiedFrom = null): array
    {
        $units = collect($copiedFrom?->units ?? $product->units ?? [])->map(fn ($u) => [
            'id' => $copiedFrom ? null : $u->id,
            'unit_id' => $u->unit_id,
            'conversion_factor' => (float) $u->conversion_factor,
            'barcode' => $copiedFrom ? '' : $u->barcode,
            'retail_price' => (float) $u->retail_price,
            'wholesale_price' => $u->wholesale_price !== null ? (float) $u->wholesale_price : null,
            'wholesale_min_qty' => $u->wholesale_min_qty !== null ? (float) $u->wholesale_min_qty : null,
            'is_default_sale_unit' => (bool) $u->is_default_sale_unit,
        ])->values()->all();

        // Après une erreur de validation, on réaffiche ce que l'utilisateur avait saisi
        $oldUnits = collect(old('units', []))->map(fn ($u) => [
            'id' => $u['id'] ?? null,
            'unit_id' => $u['unit_id'] ?? '',
            'conversion_factor' => $u['conversion_factor'] ?? '',
            'barcode' => $u['barcode'] ?? '',
            'retail_price' => $u['retail_price'] ?? '',
            'wholesale_price' => $u['wholesale_price'] ?? null,
            'wholesale_min_qty' => $u['wholesale_min_qty'] ?? null,
            'is_default_sale_unit' => ! empty($u['is_default_sale_unit']),
        ])->values()->all();
        $hasOld = session()->hasOldInput();

        return [
            'product' => $product,
            'copiedFrom' => $copiedFrom,
            'categories' => Category::with('children:id,name,parent_id')->whereNull('parent_id')->orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'abbreviation', 'allows_decimal']),
            'brands' => Brand::orderBy('name')->pluck('name'),
            'canEditPrices' => ! $product->exists || auth()->user()->can('products.edit_prices'),
            'hasMovements' => $product->exists && $product->stockMovements()->exists(),
            'initial' => [
                'cost' => old('cost_price', (float) ($product->cost_price ?? 0)),
                'retail' => old('retail_price', (float) ($product->retail_price ?? 0)),
                'wholesale' => old('wholesale_price', $product->wholesale_price !== null ? (float) $product->wholesale_price : null),
                'baseUnitId' => old('base_unit_id', $product->base_unit_id),
                'trackExpiry' => $hasOld ? (bool) old('track_expiry') : (bool) $product->track_expiry,
                'openingQty' => old('opening_quantity', ''),
                'openingCost' => old('opening_unit_cost', ''),
                'units' => $hasOld ? $oldUnits : $units,
            ],
        ];
    }
}
