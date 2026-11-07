<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Chiffres du tableau de bord. Toutes les ventes comptées = completed, partially_refunded, refunded
 * (les ventes annulées et en attente sont exclues).
 */
class DashboardService
{
    public const PERIODS = [
        'today' => "Aujourd'hui",
        '7d' => '7 derniers jours',
        '30d' => '30 derniers jours',
        'month' => 'Ce mois',
    ];

    /** @return array{key: string, label: string, from: CarbonImmutable, to: CarbonImmutable, prevFrom: CarbonImmutable, prevTo: CarbonImmutable, compare: string} */
    public function period(string $key): array
    {
        $now = CarbonImmutable::now();
        $key = array_key_exists($key, self::PERIODS) ? $key : 'today';

        [$from, $to, $prevFrom, $prevTo, $compare] = match ($key) {
            'today' => [$now->startOfDay(), $now->endOfDay(), $now->subDay()->startOfDay(), $now->subDay()->endOfDay(), 'hier'],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay(), $now->subDays(13)->startOfDay(), $now->subDays(7)->endOfDay(), '7 jours précédents'],
            '30d' => [$now->subDays(29)->startOfDay(), $now->endOfDay(), $now->subDays(59)->startOfDay(), $now->subDays(30)->endOfDay(), '30 jours précédents'],
            'month' => [$now->startOfMonth(), $now->endOfDay(), $now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfDay(), 'même période le mois dernier'],
        };

        return ['key' => $key, 'label' => self::PERIODS[$key], 'from' => $from, 'to' => $to, 'prevFrom' => $prevFrom, 'prevTo' => $prevTo, 'compare' => $compare];
    }

    /** Indicateurs clés pour une période, avec la période précédente pour comparer. */
    public function kpis(array $p): array
    {
        $cur = $this->figures($p['from'], $p['to']);
        $prev = $this->figures($p['prevFrom'], $p['prevTo']);

        $delta = fn (float $a, float $b) => $b != 0.0 ? round(($a - $b) / abs($b) * 100, 1) : null;

        return [
            'revenue' => $cur['revenue'],
            'revenue_delta' => $delta($cur['revenue'], $prev['revenue']),
            'sales_count' => $cur['count'],
            'count_delta' => $delta($cur['count'], $prev['count']),
            'avg_basket' => $cur['count'] ? round($cur['revenue'] / $cur['count'], 2) : 0.0,
            'gross_profit' => $cur['gross_profit'],
            'profit_delta' => $delta($cur['gross_profit'], $prev['gross_profit']),
            'margin' => $cur['revenue'] > 0 ? round($cur['gross_profit'] / $cur['revenue'] * 100, 1) : null,
            'expenses' => $cur['expenses'],
            'salaries' => $cur['salaries'],
            'net_profit' => round($cur['gross_profit'] - $cur['expenses'] - $cur['salaries'], 2),
            'refunds' => $cur['refunds'],
            'credit_given' => $cur['credit_given'],
            'stock_cost_value' => round((float) Batch::inStock()->sum(DB::raw('remaining_quantity * unit_cost')), 2),
            'stock_retail_value' => round((float) Product::active()->where('stock_quantity', '>', 0)->sum(DB::raw('stock_quantity * retail_price')), 2),
            'receivables' => round((float) Customer::where('balance', '>', 0)->sum('balance'), 2),
            'debtors_count' => Customer::where('balance', '>', 0)->count(),
            'payables' => round((float) Supplier::where('balance', '>', 0)->sum('balance'), 2),
        ];
    }

    /** CA et bénéfice brut par jour (jours sans vente = 0). */
    public function dailySeries(int $days = 30): array
    {
        $from = CarbonImmutable::today()->subDays($days - 1);

        $rows = Sale::counted()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, SUM(total) as revenue, SUM(total - cost_total) as profit, COUNT(*) as n')
            ->groupBy('day')->get()->keyBy(fn ($r) => (string) $r->day);

        $out = ['labels' => [], 'dates' => [], 'revenue' => [], 'profit' => [], 'count' => []];
        for ($d = $from; $d->lte(CarbonImmutable::today()); $d = $d->addDay()) {
            $r = $rows->get($d->toDateString());
            $out['dates'][] = $d->toDateString();
            $out['labels'][] = $d->format('d/m');
            $out['revenue'][] = round((float) ($r->revenue ?? 0), 2);
            $out['profit'][] = round((float) ($r->profit ?? 0), 2);
            $out['count'][] = (int) ($r->n ?? 0);
        }

        return $out;
    }

    /** CA par catégorie (catégorie racine), 7 premières + « Autres ». */
    public function revenueByCategory(array $p): array
    {
        $rows = $this->soldItems($p)
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('categories as c', 'c.id', '=', 'products.category_id')
            ->leftJoin('categories as parent', 'parent.id', '=', 'c.parent_id')
            ->selectRaw('COALESCE(parent.name, c.name) as name, SUM(sale_items.total) as revenue')
            ->groupBy('name')->orderByDesc('revenue')->get();

        $top = $rows->take(7);
        $rest = (float) $rows->slice(7)->sum('revenue');

        $labels = $top->pluck('name')->all();
        $values = $top->map(fn ($r) => round((float) $r->revenue, 2))->all();
        if ($rest > 0) {
            $labels[] = 'Autres';
            $values[] = round($rest, 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function revenueByPaymentMethod(array $p): array
    {
        $rows = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereIn('sales.status', Sale::COUNTED)
            ->whereBetween('sales.created_at', [$p['from'], $p['to']])
            ->selectRaw('sale_payments.method, SUM(sale_payments.amount_htg) as total')
            ->groupBy('sale_payments.method')->orderByDesc('total')->get();

        $labels = $rows->map(fn ($r) => SalePayment::METHODS[$r->method] ?? $r->method)->all();
        $values = $rows->map(fn ($r) => round((float) $r->total, 2))->all();

        $credit = (float) Sale::counted()->whereBetween('created_at', [$p['from'], $p['to']])->sum('due_amount');
        if ($credit > 0) {
            $labels[] = 'Crédit client';
            $values[] = round($credit, 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function topProducts(array $p, int $limit = 10): Collection
    {
        return $this->soldItems($p)
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('units', 'units.id', '=', 'products.base_unit_id')
            ->selectRaw('sale_items.product_id, products.name, units.abbreviation as unit,
                SUM(sale_items.base_quantity) as qty,
                SUM(sale_items.total) as revenue,
                SUM(sale_items.total - sale_items.unit_cost * sale_items.quantity) as profit')
            ->groupBy('sale_items.product_id', 'products.name', 'units.abbreviation')
            ->orderByDesc('revenue')->limit($limit)->get();
    }

    /** Produits en stock qui ne se sont pas vendus depuis N jours (argent qui dort). */
    public function sleepingProducts(int $days = 30, int $limit = 8): Collection
    {
        $since = CarbonImmutable::today()->subDays($days);

        return Product::active()->with('baseUnit:id,abbreviation')
            ->where('stock_quantity', '>', 0)
            ->whereDoesntHave('saleItems', fn ($q) => $q->whereHas('sale',
                fn ($s) => $s->whereIn('status', Sale::COUNTED)->where('created_at', '>=', $since)))
            ->select('*', DB::raw('stock_quantity * cost_price as tied_value'))
            ->orderByDesc('tied_value')->limit($limit)->get();
    }

    public function stockAlerts(int $limit = 10): array
    {
        $days = (int) Setting::get('expiry_alert_days', 30);

        return [
            'out_count' => Product::active()->outOfStock()->count(),
            'low_count' => Product::active()->lowStock()->count(),
            'low' => Product::active()->with('baseUnit:id,abbreviation')
                ->whereColumn('stock_quantity', '<=', 'min_stock')
                ->orderBy('stock_quantity')->limit($limit)->get(),
            'expiry_days' => $days,
            'expiring' => Batch::expiringWithin($days)->with('product:id,name,base_unit_id', 'product.baseUnit:id,abbreviation')
                ->orderBy('expiry_date')->limit($limit)->get(),
            'expiring_value' => round((float) Batch::expiringWithin($days)->sum(DB::raw('remaining_quantity * unit_cost')), 2),
            'expired_count' => Batch::expired()->count(),
            'expired_value' => round((float) Batch::expired()->sum(DB::raw('remaining_quantity * unit_cost')), 2),
        ];
    }

    public function topDebtors(int $limit = 6): Collection
    {
        return Customer::withDebt()->orderByDesc('balance')->limit($limit)
            ->get(['id', 'name', 'business_name', 'phone', 'balance', 'credit_limit']);
    }

    public function openSessions(): Collection
    {
        return CashSession::where('status', 'open')
            ->with('user:id,name', 'register:id,name')
            ->withSum(['sales as sales_total' => fn ($q) => $q->whereIn('status', Sale::COUNTED)], 'total')
            ->withCount(['sales as sales_count' => fn ($q) => $q->whereIn('status', Sale::COUNTED)])
            ->orderBy('opened_at')->get();
    }

    public function cashierPerformance(array $p): Collection
    {
        return Sale::counted()->whereBetween('sales.created_at', [$p['from'], $p['to']])
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->selectRaw('users.name, COUNT(*) as n, SUM(sales.total) as revenue')
            ->groupBy('users.id', 'users.name')->orderByDesc('revenue')->get();
    }

    public function recentSales(int $limit = 8): Collection
    {
        return Sale::whereIn('status', [...Sale::COUNTED, Sale::CANCELLED])
            ->with('customer:id,name', 'cashier:id,name')
            ->latest()->limit($limit)->get();
    }

    // ------------------------------------------------------------------

    private function figures(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sales = Sale::counted()->whereBetween('created_at', [$from, $to]);

        $revenue = (float) (clone $sales)->sum('total');
        $cost = (float) (clone $sales)->sum('cost_total');

        // Retours de la période : on retire le montant remboursé et on rajoute le coût remis en stock
        $refunds = (float) DB::table('sale_returns')->whereBetween('created_at', [$from, $to])->sum('total');
        $restockedCost = (float) DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->where('sale_returns.restock', true)
            ->whereBetween('sale_returns.created_at', [$from, $to])
            ->sum(DB::raw('sale_return_items.quantity * sale_items.unit_cost'));

        return [
            'revenue' => round($revenue - $refunds, 2),
            'count' => (clone $sales)->count(),
            'gross_profit' => round($revenue - $cost - $refunds + $restockedCost, 2),
            'refunds' => round($refunds, 2),
            'credit_given' => round((float) (clone $sales)->sum('due_amount'), 2),
            'expenses' => round((float) DB::table('expenses')->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->sum('amount'), 2),
            'salaries' => round((float) DB::table('salary_payments')->where('type', '!=', 'deduction')
                ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])->sum('amount'), 2),
        ];
    }

    private function soldItems(array $p)
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.status', Sale::COUNTED)
            ->whereBetween('sales.created_at', [$p['from'], $p['to']]);
    }
}
