<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\SaleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public const STATUSES = [
        'completed' => 'Terminée',
        'partially_refunded' => 'Retour partiel',
        'refunded' => 'Remboursée',
        'cancelled' => 'Annulée',
        'held' => 'En attente',
    ];

    public function __construct(private SaleService $sales) {}

    public function index(Request $request): View
    {
        $from = CarbonImmutable::parse($request->query('du', today()->toDateString()))->startOfDay();
        $to = CarbonImmutable::parse($request->query('au', today()->toDateString()))->endOfDay();

        $query = Sale::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($request->query('q'), fn ($q, $ref) => $q->where('reference', 'like', "%{$ref}%"))
            ->when($request->query('caissier'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->query('client'), fn ($q, $c) => $q->where('customer_id', $c))
            ->when($request->query('statut'), fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', Sale::HELD))
            ->when($request->query('paiement') === 'credit', fn ($q) => $q->where('due_amount', '>', 0))
            ->when($request->query('mode'), fn ($q, $m) => $q->whereHas('payments', fn ($p) => $p->where('method', $m)));

        $counted = (clone $query)->whereIn('status', Sale::COUNTED);

        return view('sales.index', [
            'sales' => (clone $query)->with('customer:id,name', 'cashier:id,name')->withCount('items')->latest()->paginate(30)->withQueryString(),
            'totals' => [
                'count' => (clone $counted)->count(),
                'total' => (float) (clone $counted)->sum('total'),
                'credit' => (float) (clone $counted)->sum('due_amount'),
                'profit' => (float) (clone $counted)->sum('total') - (float) (clone $counted)->sum('cost_total'),
                'cancelled' => (clone $query)->where('status', Sale::CANCELLED)->count(),
            ],
            'from' => $from,
            'to' => $to,
            'cashiers' => User::whereHas('sales')->orderBy('name')->get(['id', 'name']),
            'customers' => $request->query('client') ? Customer::whereKey($request->query('client'))->get(['id', 'name']) : collect(),
            'statuses' => self::STATUSES,
            'methods' => SalePayment::METHODS,
        ]);
    }

    public function show(Sale $sale): View
    {
        return view('sales.show', [
            'sale' => $sale->load('items.batchAllocations.batch', 'items.product.baseUnit', 'payments', 'customer', 'cashier', 'cashSession.register',
                'returns.items.saleItem', 'returns.user', 'canceller'),
            'statuses' => self::STATUSES,
            'methods' => SalePayment::METHODS,
        ]);
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->sales->cancel($sale, $request->user(), $data['reason']);

        activity('ventes')->performedOn($sale)->causedBy($request->user())
            ->withProperties(['total' => (float) $sale->total, 'raison' => $data['reason']])
            ->log("Vente {$sale->reference} annulée");

        return back()->with('success', "Vente {$sale->reference} annulée : le stock a été remis en place.");
    }

    public function storeReturn(Request $request, Sale $sale): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'numeric', 'min:0'],
            'refund_method' => ['required', 'in:cash,customer_balance'],
            'reason' => ['required', 'string', 'max:255'],
            'restock' => ['boolean'],
        ]);

        $items = collect($data['items'])->filter(fn ($q) => (float) $q > 0)
            ->map(fn ($q, $id) => ['sale_item_id' => (int) $id, 'quantity' => (float) $q])->values()->all();

        $return = $this->sales->processReturn($sale, $items, $data['refund_method'], $data['reason'], $request->boolean('restock'), $request->user());

        activity('ventes')->performedOn($sale)->causedBy($request->user())
            ->withProperties(['retour' => $return->reference, 'montant' => (float) $return->total, 'raison' => $data['reason']])
            ->log("Retour {$return->reference} sur la vente {$sale->reference}");

        return back()->with('success', "Retour {$return->reference} enregistré : ".number_format((float) $return->total, 2, ',', ' ').' HTG.');
    }
}
