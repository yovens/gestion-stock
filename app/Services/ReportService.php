<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Tous les rapports renvoient la même structure, affichée par une seule vue et exportable en CSV :
 * ['title', 'columns' => [clé => libellé], 'money' => [clés monétaires], 'rows' => [...], 'totals' => [...], 'period' => bool]
 */
class ReportService
{
    /** clé => [titre, description, permission, utilise une période] */
    public const REPORTS = [
        'ventes-jour' => ['Ventes par jour', 'Chiffre d\'affaires, nombre de ventes, crédit et bénéfice jour par jour.', 'reports.view', true],
        'ventes-produit' => ['Ventes par produit', 'Quantités vendues, chiffre d\'affaires et marge de chaque produit.', 'reports.view', true],
        'ventes-categorie' => ['Ventes par catégorie', 'Ce que rapporte chaque rayon.', 'reports.view', true],
        'ventes-caissier' => ['Ventes par caissier', 'Performance de chaque caissier.', 'reports.view', true],
        'resultat' => ['Compte de résultat', 'Ventes − coût des marchandises − dépenses − salaires = bénéfice net.', 'reports.profit', true],
        'stock' => ['Valeur du stock', 'Stock actuel de chaque produit, au prix d\'achat et au prix de vente.', 'reports.view', false],
        'expirations' => ['Expirations', 'Lots expirés et qui expirent dans les 90 jours.', 'reports.view', false],
        'pertes' => ['Pertes et ajustements', 'Casse, vol, produits expirés et corrections d\'inventaire.', 'reports.view', true],
        'credit-clients' => ['Crédit clients (ancienneté)', 'Qui doit combien, et depuis combien de temps.', 'reports.view', false],
        'dettes-fournisseurs' => ['Dettes fournisseurs', 'Ce que le dépôt doit à chaque fournisseur.', 'reports.view', false],
        'depenses' => ['Dépenses', 'Toutes les dépenses de la période par catégorie.', 'expenses.view', true],
        'caisses' => ['Sessions de caisse', 'Ouvertures, fermetures, écarts de caisse.', 'cash.view_all', true],
    ];

    public function run(string $key, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $method = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $key))));
        $report = $this->{$method}($from->startOfDay(), $to->endOfDay());

        return $report + ['title' => self::REPORTS[$key][0], 'money' => [], 'totals' => [], 'period' => self::REPORTS[$key][3]];
    }

    // ------------------------------------------------------------------

    private function ventesJour(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Sale::counted()->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as n, SUM(total) as revenue, SUM(due_amount) as credit, SUM(cost_total) as cost, SUM(total - cost_total) as profit')
            ->groupBy('day')->orderBy('day')->get()
            ->map(fn ($r) => ['day' => CarbonImmutable::parse($r->day)->format('d/m/Y'), 'n' => (int) $r->n, 'revenue' => (float) $r->revenue,
                'avg' => $r->n ? round((float) $r->revenue / $r->n, 2) : 0, 'credit' => (float) $r->credit, 'cost' => (float) $r->cost, 'profit' => (float) $r->profit])->all();

        return [
            'columns' => ['day' => 'Jour', 'n' => 'Ventes', 'revenue' => 'Chiffre d\'affaires', 'avg' => 'Panier moyen', 'credit' => 'Dont crédit', 'cost' => 'Coût marchandises', 'profit' => 'Bénéfice brut'],
            'money' => ['revenue', 'avg', 'credit', 'cost', 'profit'],
            'profit' => ['cost', 'profit'],
            'rows' => $rows,
            'totals' => $this->sum($rows, ['n', 'revenue', 'credit', 'cost', 'profit']) + ['day' => 'TOTAL'],
        ];
    }

    private function ventesProduit(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->items($from, $to)
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('units', 'units.id', '=', 'products.base_unit_id')
            ->selectRaw('products.name, products.sku, units.abbreviation as unit, SUM(sale_items.base_quantity) as qty, SUM(sale_items.total) as revenue,
                SUM(sale_items.unit_cost * sale_items.quantity) as cost')
            ->groupBy('products.id', 'products.name', 'products.sku', 'units.abbreviation')->orderByDesc('revenue')->get()
            ->map(fn ($r) => ['name' => $r->name, 'sku' => $r->sku, 'qty' => round((float) $r->qty, 3).' '.$r->unit, 'revenue' => (float) $r->revenue,
                'cost' => (float) $r->cost, 'profit' => round((float) $r->revenue - (float) $r->cost, 2),
                'margin' => (float) $r->revenue > 0 ? round(((float) $r->revenue - (float) $r->cost) / (float) $r->revenue * 100, 1).' %' : '—'])->all();

        return [
            'columns' => ['name' => 'Produit', 'sku' => 'Code', 'qty' => 'Quantité', 'revenue' => 'Ventes', 'cost' => 'Coût', 'profit' => 'Bénéfice', 'margin' => 'Marge'],
            'money' => ['revenue', 'cost', 'profit'],
            'profit' => ['cost', 'profit', 'margin'],
            'rows' => $rows,
            'totals' => $this->sum($rows, ['revenue', 'cost', 'profit']) + ['name' => 'TOTAL'],
        ];
    }

    private function ventesCategorie(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->items($from, $to)
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('categories as c', 'c.id', '=', 'products.category_id')
            ->leftJoin('categories as p', 'p.id', '=', 'c.parent_id')
            ->selectRaw('COALESCE(p.name, c.name) as name, COUNT(DISTINCT sale_items.sale_id) as n, SUM(sale_items.total) as revenue, SUM(sale_items.unit_cost * sale_items.quantity) as cost')
            ->groupBy('name')->orderByDesc('revenue')->get();
        $all = (float) $rows->sum('revenue') ?: 1;

        $rows = $rows->map(fn ($r) => ['name' => $r->name, 'n' => (int) $r->n, 'revenue' => (float) $r->revenue, 'share' => round((float) $r->revenue / $all * 100, 1).' %',
            'cost' => (float) $r->cost, 'profit' => round((float) $r->revenue - (float) $r->cost, 2)])->all();

        return [
            'columns' => ['name' => 'Catégorie', 'n' => 'Ventes', 'revenue' => 'Chiffre d\'affaires', 'share' => 'Part', 'cost' => 'Coût', 'profit' => 'Bénéfice'],
            'money' => ['revenue', 'cost', 'profit'], 'profit' => ['cost', 'profit'],
            'rows' => $rows, 'totals' => $this->sum($rows, ['revenue', 'cost', 'profit']) + ['name' => 'TOTAL'],
        ];
    }

    private function ventesCaissier(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Sale::counted()->whereBetween('sales.created_at', [$from, $to])->join('users', 'users.id', '=', 'sales.user_id')
            ->selectRaw('users.name, COUNT(*) as n, SUM(sales.total) as revenue, SUM(sales.due_amount) as credit, SUM(sales.discount_amount) as discounts')
            ->groupBy('users.id', 'users.name')->orderByDesc('revenue')->get()
            ->map(fn ($r) => ['name' => $r->name, 'n' => (int) $r->n, 'revenue' => (float) $r->revenue, 'avg' => round((float) $r->revenue / max(1, $r->n), 2),
                'discounts' => (float) $r->discounts, 'credit' => (float) $r->credit])->all();

        $cancelled = Sale::where('status', Sale::CANCELLED)->whereBetween('created_at', [$from, $to])
            ->selectRaw('user_id, COUNT(*) as n')->groupBy('user_id')->pluck('n', 'user_id');

        return [
            'columns' => ['name' => 'Caissier', 'n' => 'Ventes', 'revenue' => 'Chiffre d\'affaires', 'avg' => 'Panier moyen', 'discounts' => 'Remises accordées', 'credit' => 'Crédit accordé'],
            'money' => ['revenue', 'avg', 'discounts', 'credit'],
            'rows' => $rows, 'totals' => $this->sum($rows, ['n', 'revenue', 'discounts', 'credit']) + ['name' => 'TOTAL'],
            'note' => $cancelled->isNotEmpty() ? 'Ventes annulées sur la période : '.$cancelled->sum() : null,
        ];
    }

    private function resultat(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $k = app(DashboardService::class)->kpis(['from' => $from, 'to' => $to, 'prevFrom' => $from, 'prevTo' => $to]);
        $sales = Sale::counted()->whereBetween('created_at', [$from, $to]);
        $grossSales = (float) (clone $sales)->sum('total');

        $rows = [
            ['label' => 'Ventes brutes', 'amount' => $grossSales],
            ['label' => '− Retours clients', 'amount' => -$k['refunds']],
            ['label' => '= Chiffre d\'affaires net', 'amount' => $k['revenue']],
            ['label' => '− Coût des marchandises vendues', 'amount' => -round($k['revenue'] - $k['gross_profit'], 2)],
            ['label' => '= Bénéfice brut', 'amount' => $k['gross_profit']],
        ];
        $expenses = DB::table('expenses')->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('expense_categories.name, SUM(expenses.amount) as total')->groupBy('expense_categories.name')->orderByDesc('total')->get();
        foreach ($expenses as $e) {
            $rows[] = ['label' => '   − '.$e->name, 'amount' => -(float) $e->total];
        }
        $rows[] = ['label' => '− Salaires et primes', 'amount' => -$k['salaries']];

        $losses = (float) DB::table('stock_adjustment_items')->join('stock_adjustments', 'stock_adjustments.id', '=', 'stock_adjustment_items.stock_adjustment_id')
            ->where('stock_adjustments.status', 'approved')->whereBetween('stock_adjustments.approved_at', [$from, $to])
            ->sum(DB::raw('stock_adjustment_items.quantity * stock_adjustment_items.unit_cost'));
        $rows[] = ['label' => '± Pertes et ajustements de stock', 'amount' => round($losses, 2)];
        $rows[] = ['label' => '= BÉNÉFICE NET', 'amount' => round($k['net_profit'] + $losses, 2)];

        return [
            'columns' => ['label' => 'Poste', 'amount' => 'Montant'],
            'money' => ['amount'],
            'rows' => $rows,
            'note' => "Marge brute : ".($k['margin'] ?? 0).' % · '.$k['sales_count'].' vente(s) · crédit accordé : '.number_format($k['credit_given'], 2, ',', ' ').' HTG',
        ];
    }

    private function stock(): array
    {
        $rows = Product::active()->with('category:id,name', 'baseUnit:id,abbreviation')->orderBy('name')->get()
            ->map(fn ($p) => ['name' => $p->name, 'sku' => $p->sku, 'category' => $p->category?->name, 'location' => $p->location,
                'qty' => round((float) $p->stock_quantity, 3).' '.$p->baseUnit?->abbreviation, 'min' => (float) $p->min_stock,
                'cost' => (float) $p->cost_price, 'value_cost' => round((float) $p->stock_quantity * (float) $p->cost_price, 2),
                'value_retail' => round((float) $p->stock_quantity * (float) $p->retail_price, 2),
                'status' => (float) $p->stock_quantity <= 0 ? 'Rupture' : ((float) $p->stock_quantity <= (float) $p->min_stock ? 'Bas' : 'OK')])->all();

        return [
            'columns' => ['name' => 'Produit', 'sku' => 'Code', 'category' => 'Catégorie', 'location' => 'Emplacement', 'qty' => 'Stock', 'min' => 'Minimum', 'cost' => 'Coût moyen', 'value_cost' => 'Valeur (achat)', 'value_retail' => 'Valeur (vente)', 'status' => 'État'],
            'money' => ['cost', 'value_cost', 'value_retail'], 'profit' => ['cost', 'value_cost'],
            'rows' => $rows, 'totals' => $this->sum($rows, ['value_cost', 'value_retail']) + ['name' => 'TOTAL'],
        ];
    }

    private function expirations(): array
    {
        $rows = Batch::inStock()->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', today()->addDays(90))
            ->with('product:id,name,base_unit_id', 'product.baseUnit:id,abbreviation')->orderBy('expiry_date')->get()
            ->map(fn ($b) => ['name' => $b->product->name, 'batch' => $b->batch_number ?: '#'.$b->id, 'expiry' => $b->expiry_date->format('d/m/Y'),
                'days' => $b->daysUntilExpiry() < 0 ? 'EXPIRÉ' : $b->daysUntilExpiry().' j',
                'qty' => round((float) $b->remaining_quantity, 3).' '.$b->product->baseUnit?->abbreviation,
                'value' => round((float) $b->remaining_quantity * (float) $b->unit_cost, 2)])->all();

        return [
            'columns' => ['name' => 'Produit', 'batch' => 'Lot', 'expiry' => 'Expiration', 'days' => 'Délai', 'qty' => 'Quantité', 'value' => 'Valeur'],
            'money' => ['value'], 'rows' => $rows, 'totals' => $this->sum($rows, ['value']) + ['name' => 'TOTAL'],
        ];
    }

    private function pertes(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $types = \App\Models\StockAdjustment::TYPES;
        $rows = DB::table('stock_adjustment_items')
            ->join('stock_adjustments as a', 'a.id', '=', 'stock_adjustment_items.stock_adjustment_id')
            ->join('products', 'products.id', '=', 'stock_adjustment_items.product_id')
            ->where('a.status', 'approved')->whereBetween('a.adjustment_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('a.adjustment_date')
            ->get(['a.reference', 'a.adjustment_date', 'a.type', 'products.name', 'stock_adjustment_items.quantity', 'stock_adjustment_items.unit_cost', 'stock_adjustment_items.reason'])
            ->map(fn ($r) => ['date' => CarbonImmutable::parse($r->adjustment_date)->format('d/m/Y'), 'reference' => $r->reference, 'type' => $types[$r->type] ?? $r->type,
                'name' => $r->name, 'qty' => (float) $r->quantity, 'value' => round((float) $r->quantity * (float) $r->unit_cost, 2), 'reason' => $r->reason])->all();

        return [
            'columns' => ['date' => 'Date', 'reference' => 'N°', 'type' => 'Type', 'name' => 'Produit', 'qty' => 'Quantité', 'value' => 'Valeur', 'reason' => 'Motif'],
            'money' => ['value'], 'rows' => $rows, 'totals' => $this->sum($rows, ['value']) + ['date' => 'TOTAL'],
        ];
    }

    private function creditClients(): array
    {
        $svc = app(CustomerService::class);
        $rows = Customer::where('balance', '>', 0)->orderByDesc('balance')->get()->map(function (Customer $c) use ($svc) {
            $date = $svc->oldestUnpaidDate($c);
            $days = $date ? (int) $date->diffInDays(now()) : 0;

            return ['name' => $c->name, 'phone' => $c->phone, 'limit' => (float) $c->credit_limit, 'balance' => (float) $c->balance,
                'since' => $date?->format('d/m/Y'), 'days' => $days,
                'bucket' => $days <= 30 ? '0-30 j' : ($days <= 60 ? '31-60 j' : ($days <= 90 ? '61-90 j' : '+90 j'))];
        })->all();

        $buckets = collect($rows)->groupBy('bucket')->map(fn ($g) => number_format($g->sum('balance'), 2, ',', ' '))->all();

        return [
            'columns' => ['name' => 'Client', 'phone' => 'Téléphone', 'limit' => 'Limite', 'balance' => 'Doit', 'since' => 'Plus ancienne dette', 'days' => 'Jours', 'bucket' => 'Tranche'],
            'money' => ['limit', 'balance'], 'rows' => $rows, 'totals' => $this->sum($rows, ['balance']) + ['name' => 'TOTAL'],
            'note' => collect($buckets)->map(fn ($v, $k) => "$k : $v HTG")->join(' · '),
        ];
    }

    private function dettesFournisseurs(): array
    {
        $rows = Supplier::where('balance', '>', 0)->orderByDesc('balance')->get()->map(function (Supplier $s) {
            $oldest = $s->goodsReceipts()->where('payment_status', '!=', 'paid')->min('received_date');
            $deadline = $oldest ? CarbonImmutable::parse($oldest)->addDays($s->payment_terms_days) : null;

            return ['name' => $s->name, 'phone' => $s->phone, 'terms' => $s->payment_terms_days.' j', 'balance' => (float) $s->balance,
                'oldest' => $oldest ? CarbonImmutable::parse($oldest)->format('d/m/Y') : '—',
                'deadline' => $deadline ? $deadline->format('d/m/Y').($deadline->isPast() ? ' (en retard)' : '') : '—'];
        })->all();

        return [
            'columns' => ['name' => 'Fournisseur', 'phone' => 'Téléphone', 'terms' => 'Délai', 'balance' => 'On doit', 'oldest' => 'Plus ancienne facture', 'deadline' => 'Échéance'],
            'money' => ['balance'], 'rows' => $rows, 'totals' => $this->sum($rows, ['balance']) + ['name' => 'TOTAL'],
        ];
    }

    private function depenses(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('expenses')->join('expense_categories as c', 'c.id', '=', 'expenses.expense_category_id')
            ->leftJoin('users', 'users.id', '=', 'expenses.user_id')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->orderBy('expense_date')
            ->get(['expenses.expense_date', 'expenses.reference', 'c.name as category', 'expenses.description', 'expenses.method', 'users.name as user', 'expenses.amount'])
            ->map(fn ($r) => ['date' => CarbonImmutable::parse($r->expense_date)->format('d/m/Y'), 'reference' => $r->reference, 'category' => $r->category,
                'description' => $r->description, 'method' => $r->method, 'user' => $r->user, 'amount' => (float) $r->amount])->all();

        return [
            'columns' => ['date' => 'Date', 'reference' => 'N°', 'category' => 'Catégorie', 'description' => 'Description', 'method' => 'Mode', 'user' => 'Par', 'amount' => 'Montant'],
            'money' => ['amount'], 'rows' => $rows, 'totals' => $this->sum($rows, ['amount']) + ['date' => 'TOTAL'],
        ];
    }

    private function caisses(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = CashSession::with('user:id,name', 'register:id,name')->whereBetween('opened_at', [$from, $to])->orderBy('opened_at')->get()
            ->map(fn ($s) => ['id' => $s->id, 'register' => $s->register->name, 'user' => $s->user->name,
                'opened' => $s->opened_at->format('d/m H:i'), 'closed' => $s->closed_at?->format('d/m H:i') ?? 'ouverte',
                'expected' => (float) $s->expected_amount_htg, 'counted' => (float) $s->counted_amount_htg,
                'difference' => (float) $s->difference_htg, 'difference_usd' => (float) $s->difference_usd])->all();

        return [
            'columns' => ['id' => '#', 'register' => 'Caisse', 'user' => 'Caissier', 'opened' => 'Ouverture', 'closed' => 'Fermeture', 'expected' => 'Attendu HTG', 'counted' => 'Compté HTG', 'difference' => 'Écart HTG', 'difference_usd' => 'Écart USD'],
            'money' => ['expected', 'counted', 'difference', 'difference_usd'],
            'rows' => $rows, 'totals' => $this->sum($rows, ['difference', 'difference_usd']) + ['id' => 'TOTAL'],
            'link' => 'cash.report',
        ];
    }

    // ------------------------------------------------------------------

    private function items(CarbonImmutable $from, CarbonImmutable $to)
    {
        return DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.status', Sale::COUNTED)->whereBetween('sales.created_at', [$from, $to]);
    }

    private function sum(array $rows, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn ($k) => [$k => round(collect($rows)->sum($k), 2)])->all();
    }
}
