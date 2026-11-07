<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(private SaleService $sales) {}

    public function index(Request $request): View|RedirectResponse
    {
        $session = CashSession::openFor($request->user());
        if (! $session) {
            return redirect()->route('cash.open')->with('info', 'Ouvrez votre caisse pour commencer à vendre.');
        }

        return view('pos.index', [
            'session' => $session->load('register'),
            'categories' => Category::where('is_active', true)->whereNull('parent_id')->orderBy('name')->get(['id', 'name', 'color']),
            'config' => [
                'rate' => ExchangeRate::current(),
                'maxDiscount' => (float) Setting::get('max_discount_percent', 10),
                'freeDiscount' => $request->user()->can('sales.discount'),
                'canCredit' => $request->user()->can('sales.credit'),
                'methods' => SalePayment::METHODS,
                'heldCount' => $session->sales()->where('status', Sale::HELD)->count(),
            ],
        ]);
    }

    /**
     * Recherche produit. Un code-barres exact (produit OU caisse/sac) renvoie `exact`
     * pour que la caisse ajoute l'article directement au panier.
     */
    public function products(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $categoryId = $request->integer('category') ?: null;

        if ($term !== '') {
            $unit = ProductUnit::where('barcode', $term)->first();
            $product = $unit
                ? $this->baseQuery()->find($unit->product_id)
                : $this->baseQuery()->where(fn ($q) => $q->where('barcode', $term)->orWhere('sku', $term))->first();

            if ($product) {
                return response()->json(['exact' => $this->present($product), 'unit_id' => $unit?->id, 'results' => []]);
            }
        }

        $products = $this->baseQuery()
            ->when($term !== '', fn ($q) => $q->search($term))
            ->when($categoryId, fn ($q) => $q->whereIn('category_id',
                Category::where('id', $categoryId)->orWhere('parent_id', $categoryId)->pluck('id')))
            ->orderBy('name')
            ->limit(40)
            ->get();

        return response()->json(['exact' => null, 'results' => $products->map(fn ($p) => $this->present($p))]);
    }

    public function customers(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $customers = Customer::where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                ->orWhere('business_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->orderBy('name')->limit(15)
            ->get(['id', 'name', 'business_name', 'phone', 'type', 'credit_allowed', 'credit_limit', 'balance']);

        return response()->json($customers->map(fn (Customer $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'business_name' => $c->business_name,
            'phone' => $c->phone,
            'type' => $c->type,
            'balance' => (float) $c->balance,
            'available_credit' => $c->availableCredit(),
        ]));
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->sales->create($request->validated(), $request->user());

        return response()->json([
            'id' => $sale->id,
            'reference' => $sale->reference,
            'status' => $sale->status,
            'total' => (float) $sale->total,
            'paid' => (float) $sale->paid_amount,
            'change' => (float) $sale->change_amount,
            'due' => (float) $sale->due_amount,
            'receipt_url' => $sale->status === Sale::HELD ? null : route('pos.receipt', $sale),
        ], 201);
    }

    /** Ventes en attente de la session courante. */
    public function held(Request $request): JsonResponse
    {
        $session = CashSession::openFor($request->user());

        $sales = $session
            ? $session->sales()->where('status', Sale::HELD)->with('customer:id,name')->withCount('items')->latest()->get()
            : collect();

        return response()->json($sales->map(fn (Sale $s) => [
            'id' => $s->id,
            'reference' => $s->reference,
            'customer' => $s->customer?->name,
            'items_count' => $s->items_count,
            'total' => (float) $s->total,
            'time' => $s->created_at->format('H:i'),
            'notes' => $s->notes,
        ]));
    }

    /** Reprend une vente en attente : renvoie le panier et supprime l'attente. */
    public function resume(Request $request, Sale $sale): JsonResponse
    {
        $this->authorizeHeld($request, $sale);

        $payload = DB::transaction(function () use ($sale) {
            $sale->load('items', 'customer');
            $products = $this->baseQuery()->whereIn('id', $sale->items->pluck('product_id'))->get()->keyBy('id');

            $data = [
                'customer' => $sale->customer ? [
                    'id' => $sale->customer->id,
                    'name' => $sale->customer->name,
                    'type' => $sale->customer->type,
                    'balance' => (float) $sale->customer->balance,
                    'available_credit' => $sale->customer->availableCredit(),
                ] : null,
                'sale_type' => $sale->sale_type,
                'notes' => $sale->notes,
                'discount' => round((float) $sale->discount_amount - (float) $sale->items->sum('discount_amount'), 2),
                'lines' => $sale->items->filter(fn ($i) => isset($products[$i->product_id]))->map(fn ($i) => [
                    'product' => $this->present($products[$i->product_id]),
                    'unit_id' => $i->product_unit_id,
                    'quantity' => (float) $i->quantity,
                    'discount' => (float) $i->discount_amount,
                ])->values(),
            ];
            $sale->delete();

            return $data;
        });

        return response()->json($payload);
    }

    public function destroyHeld(Request $request, Sale $sale): JsonResponse
    {
        $this->authorizeHeld($request, $sale);
        $sale->delete();

        return response()->json(['ok' => true]);
    }

    public function receipt(Request $request, Sale $sale): View
    {
        $user = $request->user();
        abort_unless((int) $sale->user_id === (int) $user->id || $user->can('sales.view_all'), 403);
        abort_if($sale->status === Sale::HELD, 404);

        return view('pos.receipt', [
            'sale' => $sale->load('items', 'payments', 'customer', 'cashier'),
            'store' => [
                'name' => Setting::get('store_name', 'Dépôt'),
                'address' => Setting::get('store_address'),
                'phone' => Setting::get('store_phone'),
                'nif' => Setting::get('store_nif'),
                'logo' => Setting::get('store_logo'),
                'footer' => Setting::get('receipt_footer', 'Merci pour votre achat !'),
            ],
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    // ------------------------------------------------------------------

    private function baseQuery()
    {
        return Product::query()
            ->active()
            ->with(['baseUnit:id,name,abbreviation,allows_decimal', 'units.unit:id,name,abbreviation,allows_decimal'])
            ->withSum(['batches as sellable_qty' => fn ($q) => $q->sellable()], 'remaining_quantity');
    }

    private function present(Product $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'image' => $p->image ? asset('storage/'.$p->image) : null,
            'stock' => round((float) ($p->sellable_qty ?? 0), 3),
            'low' => (float) ($p->sellable_qty ?? 0) <= (float) $p->min_stock,
            'base' => [
                'name' => $p->baseUnit?->name,
                'abbr' => $p->baseUnit?->abbreviation,
                'decimal' => (bool) $p->baseUnit?->allows_decimal,
                'retail' => (float) $p->retail_price,
                'wholesale' => $p->wholesale_price !== null ? (float) $p->wholesale_price : null,
                'min' => $p->wholesale_min_qty !== null ? (float) $p->wholesale_min_qty : null,
            ],
            'units' => $p->units->map(fn (ProductUnit $u) => [
                'id' => $u->id,
                'name' => $u->unit?->name,
                'abbr' => $u->unit?->abbreviation,
                'decimal' => (bool) $u->unit?->allows_decimal,
                'factor' => (float) $u->conversion_factor,
                'retail' => (float) $u->retail_price,
                'wholesale' => $u->wholesale_price !== null ? (float) $u->wholesale_price : null,
                'min' => $u->wholesale_min_qty !== null ? (float) $u->wholesale_min_qty : null,
                'default' => (bool) $u->is_default_sale_unit,
            ])->values(),
        ];
    }

    private function authorizeHeld(Request $request, Sale $sale): void
    {
        abort_unless($sale->status === Sale::HELD, 404);
        $session = CashSession::openFor($request->user());
        abort_unless($session && (int) $sale->cash_session_id === (int) $session->id, 403);
    }
}
