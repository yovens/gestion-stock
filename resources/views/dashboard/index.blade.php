@extends('layouts.admin')

@section('title', 'Tableau de bord')

@php $F = \App\Support\Format::class; @endphp

@section('actions')
    <nav class="flex rounded-lg bg-slate-100 p-1 text-sm" aria-label="Période">
        @foreach ($periods as $key => $label)
            <a href="{{ route('dashboard', ['periode' => $key]) }}"
               class="rounded-md px-3 py-1.5 font-medium {{ $period['key'] === $key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">{{ $label }}</a>
        @endforeach
    </nav>
@endsection

@section('content')
<div class="space-y-6">

    {{-- ============================ ALERTES ============================ --}}
    @if ($alerts['expired_count'] || $alerts['out_count'] || $alerts['low_count'] || $alerts['expiring']->isNotEmpty())
        <div class="flex flex-wrap gap-3">
            @if ($alerts['expired_count'])
                <div class="flex items-center gap-2 rounded-xl bg-rose-50 px-4 py-2.5 text-sm text-rose-800 ring-1 ring-rose-200">
                    <span aria-hidden="true" class="grid h-5 w-5 place-items-center rounded-full bg-rose-600 text-xs font-bold text-white">!</span>
                    <strong>Expiré :</strong> {{ $alerts['expired_count'] }} lot(s) · {{ $F::money($alerts['expired_value']) }} à retirer des rayons
                </div>
            @endif
            @if ($alerts['out_count'])
                <div class="flex items-center gap-2 rounded-xl bg-rose-50 px-4 py-2.5 text-sm text-rose-800 ring-1 ring-rose-200">
                    <span aria-hidden="true" class="grid h-5 w-5 place-items-center rounded-full bg-rose-600 text-xs font-bold text-white">!</span>
                    <strong>Rupture :</strong> {{ $alerts['out_count'] }} produit(s)
                </div>
            @endif
            @if ($alerts['low_count'])
                <div class="flex items-center gap-2 rounded-xl bg-amber-50 px-4 py-2.5 text-sm text-amber-900 ring-1 ring-amber-200">
                    <span aria-hidden="true" class="grid h-5 w-5 place-items-center rounded-full bg-amber-500 text-xs font-bold text-white">↓</span>
                    <strong>Stock bas :</strong> {{ $alerts['low_count'] }} produit(s)
                </div>
            @endif
            @if ($alerts['expiring']->isNotEmpty())
                <div class="flex items-center gap-2 rounded-xl bg-amber-50 px-4 py-2.5 text-sm text-amber-900 ring-1 ring-amber-200">
                    <span aria-hidden="true" class="grid h-5 w-5 place-items-center rounded-full bg-amber-500 text-xs font-bold text-white">⏱</span>
                    <strong>Expire sous {{ $alerts['expiry_days'] }} j :</strong> {{ $F::money($alerts['expiring_value']) }} de marchandise
                </div>
            @endif
        </div>
    @endif

    {{-- ============================== KPI ============================== --}}
    <section aria-label="Indicateurs clés" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('dashboard._kpi', [
            'label' => "Chiffre d'affaires · ".mb_strtolower($period['label']),
            'value' => $F::money($kpis['revenue']),
            'delta' => $kpis['revenue_delta'], 'compare' => $period['compare'],
            'sub' => $kpis['refunds'] > 0 ? 'retours déduits : '.$F::money($kpis['refunds']) : null,
        ])
        @include('dashboard._kpi', [
            'label' => 'Nombre de ventes',
            'value' => $kpis['sales_count'],
            'delta' => $kpis['count_delta'], 'compare' => $period['compare'],
            'sub' => 'panier moyen '.$F::money($kpis['avg_basket']),
        ])
        @if ($canSeeProfit)
            @include('dashboard._kpi', [
                'label' => 'Bénéfice brut',
                'value' => $F::money($kpis['gross_profit']),
                'delta' => $kpis['profit_delta'], 'compare' => $period['compare'],
                'sub' => 'marge '.$F::percent($kpis['margin']),
            ])
            @include('dashboard._kpi', [
                'label' => 'Bénéfice net',
                'value' => $F::money($kpis['net_profit']),
                'sub' => 'après dépenses ('.$F::money($kpis['expenses']).') et salaires ('.$F::money($kpis['salaries']).')',
            ])
        @else
            @include('dashboard._kpi', ['label' => 'Crédit accordé', 'value' => $F::money($kpis['credit_given']), 'sub' => mb_strtolower($period['label'])])
            @include('dashboard._kpi', ['label' => 'Dépenses', 'value' => $F::money($kpis['expenses']), 'sub' => mb_strtolower($period['label'])])
        @endif

        @include('dashboard._kpi', [
            'label' => 'Valeur du stock',
            'value' => $F::money($kpis['stock_cost_value']),
            'sub' => 'au prix d\'achat · '.$F::money($kpis['stock_retail_value']).' au prix de vente',
        ])
        @include('dashboard._kpi', [
            'label' => 'Crédit clients à encaisser',
            'value' => $F::money($kpis['receivables']),
            'sub' => $kpis['debtors_count'].' client(s) doivent de l\'argent',
        ])
        @include('dashboard._kpi', [
            'label' => 'Dette fournisseurs',
            'value' => $F::money($kpis['payables']),
            'sub' => 'reste à payer',
        ])
        @include('dashboard._kpi', [
            'label' => 'Caisses ouvertes',
            'value' => $sessions->count(),
            'sub' => $sessions->isNotEmpty() ? $F::money($sessions->sum('sales_total')).' encaissés depuis l\'ouverture' : 'aucune caisse en cours',
        ])
    </section>

    {{-- ============================ GRAPHIQUES ============================ --}}
    <section class="grid gap-4 xl:grid-cols-3">
        <figure class="rounded-2xl border border-slate-200 bg-white p-5 xl:col-span-2">
            <figcaption class="flex items-baseline justify-between">
                <h2 class="font-semibold">Ventes des 30 derniers jours</h2>
                <span class="text-xs text-slate-500">HTG par jour</span>
            </figcaption>
            <div class="relative mt-4 h-72"><canvas id="chart-daily" role="img" aria-label="Ventes{{ $canSeeProfit ? ' et bénéfice brut' : '' }} par jour sur 30 jours"></canvas></div>
            <details class="mt-3 text-sm">
                <summary class="cursor-pointer text-slate-500 hover:text-slate-800">Voir le tableau</summary>
                <div class="mt-2 max-h-64 overflow-y-auto">
                    <table class="w-full text-left tabular-nums">
                        <thead class="sticky top-0 bg-white text-xs uppercase text-slate-500"><tr><th class="py-1">Jour</th><th class="text-right">Ventes</th><th class="text-right">Nb</th>@if ($canSeeProfit)<th class="text-right">Bénéfice brut</th>@endif</tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach (array_reverse(array_keys($tables['daily']['labels'])) as $i)
                                <tr><td class="py-1">{{ $tables['daily']['labels'][$i] }}</td><td class="text-right">{{ $F::money($tables['daily']['revenue'][$i]) }}</td><td class="text-right">{{ $tables['daily']['count'][$i] }}</td>@if ($canSeeProfit)<td class="text-right">{{ $F::money($tables['daily']['profit'][$i]) }}</td>@endif</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </figure>

        <div class="grid gap-4">
            @foreach ([['byCategory', 'chart-category', 'Ventes par catégorie'], ['byMethod', 'chart-method', 'Encaissements par mode']] as [$key, $id, $title])
                <figure class="rounded-2xl border border-slate-200 bg-white p-5">
                    <figcaption class="flex items-baseline justify-between">
                        <h2 class="font-semibold">{{ $title }}</h2>
                        <span class="text-xs text-slate-500">{{ mb_strtolower($period['label']) }}</span>
                    </figcaption>
                    @if (count($tables[$key]['values']))
                        <div class="relative mt-3" style="height: {{ max(120, 34 * count($tables[$key]['values']) + 30) }}px"><canvas id="{{ $id }}" role="img" aria-label="{{ $title }}"></canvas></div>
                        <details class="mt-2 text-sm">
                            <summary class="cursor-pointer text-slate-500 hover:text-slate-800">Voir le tableau</summary>
                            <table class="mt-2 w-full tabular-nums">
                                @foreach ($tables[$key]['labels'] as $i => $label)
                                    <tr class="border-t border-slate-100"><td class="py-1">{{ $label }}</td><td class="text-right">{{ $F::money($tables[$key]['values'][$i]) }}</td></tr>
                                @endforeach
                            </table>
                        </details>
                    @else
                        <p class="py-10 text-center text-sm text-slate-500">Aucune vente sur la période.</p>
                    @endif
                </figure>
            @endforeach
        </div>
    </section>

    {{-- ====================== TOP PRODUITS & ALERTES STOCK ====================== --}}
    <section class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white xl:col-span-2">
            <div class="flex items-baseline justify-between p-5 pb-3">
                <h2 class="font-semibold">Top 10 produits</h2>
                <span class="text-xs text-slate-500">{{ mb_strtolower($period['label']) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm tabular-nums">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr><th class="px-5 py-2">#</th><th class="py-2">Produit</th><th class="py-2 text-right">Qté vendue</th><th class="px-5 py-2 text-right">Ventes</th>@if ($canSeeProfit)<th class="px-5 py-2 text-right">Bénéfice</th>@endif</tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php $maxRev = max(1, (float) ($topProducts->max('revenue') ?? 1)); @endphp
                        @forelse ($topProducts as $i => $p)
                            <tr>
                                <td class="px-5 py-2.5 text-slate-400">{{ $i + 1 }}</td>
                                <td class="py-2.5">
                                    <div class="font-medium">{{ $p->name }}</div>
                                    <div class="mt-1 h-1 rounded-full bg-slate-100"><div class="h-1 rounded-full bg-[#2a78d6]" style="width: {{ round((float) $p->revenue / $maxRev * 100) }}%"></div></div>
                                </td>
                                <td class="py-2.5 text-right">{{ $F::qty($p->qty) }} {{ $p->unit }}</td>
                                <td class="px-5 py-2.5 text-right font-semibold">{{ $F::money($p->revenue) }}</td>
                                @if ($canSeeProfit)<td class="px-5 py-2.5 text-right {{ (float) $p->profit < 0 ? 'text-rose-600' : '' }}">{{ $F::money($p->profit) }}</td>@endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Aucune vente sur la période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white">
            <div class="p-5 pb-3"><h2 class="font-semibold">Stock à réapprovisionner</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($alerts['low'] as $p)
                    <li class="flex items-center gap-3 px-5 py-2.5">
                        @if ((float) $p->stock_quantity <= 0)
                            <span class="shrink-0 rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-rose-700">Rupture</span>
                        @else
                            <span class="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-800">Bas</span>
                        @endif
                        <span class="min-w-0 flex-1 truncate">{{ $p->name }}</span>
                        <span class="shrink-0 tabular-nums text-slate-500">{{ $F::qty($p->stock_quantity) }} / {{ $F::qty($p->min_stock) }} {{ $p->baseUnit?->abbreviation }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-emerald-700">✓ Aucun produit sous le seuil.</li>
                @endforelse
            </ul>
        </div>
    </section>

    {{-- ================== EXPIRATIONS, DORMANTS, CRÉDIT ================== --}}
    <section class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white">
            <div class="p-5 pb-3">
                <h2 class="font-semibold">Expire bientôt</h2>
                <p class="text-xs text-slate-500">Lots qui expirent dans les {{ $alerts['expiry_days'] }} prochains jours : à vendre en priorité.</p>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($alerts['expiring'] as $b)
                    @php $d = $b->daysUntilExpiry(); @endphp
                    <li class="flex items-center gap-3 px-5 py-2.5">
                        <span class="w-14 shrink-0 rounded px-1.5 py-0.5 text-center text-[11px] font-bold {{ $d <= 7 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800' }}">{{ $d === 0 ? 'auj.' : $d.' j' }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate">{{ $b->product->name }}</div>
                            <div class="text-xs text-slate-500">Lot {{ $b->batch_number ?: '#'.$b->id }} · {{ $b->expiry_date->format('d/m/Y') }}</div>
                        </div>
                        <span class="shrink-0 tabular-nums text-slate-500">{{ $F::qty($b->remaining_quantity) }} {{ $b->product->baseUnit?->abbreviation }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-emerald-700">✓ Rien n'expire prochainement.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white">
            <div class="p-5 pb-3">
                <h2 class="font-semibold">Clients qui doivent le plus</h2>
                <p class="text-xs text-slate-500">Total à encaisser : {{ $F::money($kpis['receivables']) }}</p>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($debtors as $c)
                    @php $pct = (float) $c->credit_limit > 0 ? min(100, (float) $c->balance / (float) $c->credit_limit * 100) : 100; @endphp
                    <li class="px-5 py-2.5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $c->name }}</div>
                                <div class="truncate text-xs text-slate-500">{{ collect([$c->business_name, $c->phone])->filter()->join(' · ') }}</div>
                            </div>
                            <span class="shrink-0 font-semibold tabular-nums">{{ $F::money($c->balance) }}</span>
                        </div>
                        <div class="mt-1.5 flex items-center gap-2 text-[11px] text-slate-500">
                            <div class="h-1 flex-1 rounded-full bg-slate-100"><div class="h-1 rounded-full {{ $pct >= 90 ? 'bg-rose-500' : 'bg-slate-400' }}" style="width: {{ round($pct) }}%"></div></div>
                            {{ round($pct) }} % de la limite
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-emerald-700">✓ Aucun crédit en cours.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white">
            <div class="p-5 pb-3">
                <h2 class="font-semibold">Produits qui dorment</h2>
                <p class="text-xs text-slate-500">En stock mais aucune vente depuis 30 jours (argent immobilisé).</p>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($sleeping as $p)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <div class="min-w-0">
                            <div class="truncate">{{ $p->name }}</div>
                            <div class="text-xs text-slate-500">{{ $F::qty($p->stock_quantity) }} {{ $p->baseUnit?->abbreviation }} en stock</div>
                        </div>
                        <span class="shrink-0 tabular-nums text-slate-600">{{ $F::money($p->tied_value) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-emerald-700">✓ Tous les produits se vendent.</li>
                @endforelse
            </ul>
        </div>
    </section>

    {{-- =================== CAISSES, CAISSIERS, DERNIÈRES VENTES =================== --}}
    <section class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white">
            <div class="p-5 pb-3"><h2 class="font-semibold">Caisses en cours</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($sessions as $s)
                    <li class="flex items-center gap-3 px-5 py-2.5">
                        <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ $s->register->name }} · {{ $s->user->name }}</div>
                            <div class="text-xs text-slate-500">Ouverte à {{ $s->opened_at->format('H:i') }}{{ $s->opened_at->isToday() ? '' : ' le '.$s->opened_at->format('d/m') }} · {{ $s->sales_count }} vente(s)</div>
                        </div>
                        <span class="shrink-0 font-semibold tabular-nums">{{ $F::money($s->sales_total) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-slate-500">Aucune caisse ouverte.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white">
            <div class="flex items-baseline justify-between p-5 pb-3">
                <h2 class="font-semibold">Ventes par caissier</h2>
                <span class="text-xs text-slate-500">{{ mb_strtolower($period['label']) }}</span>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($cashiers as $c)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <span>{{ $c->name }} <span class="text-xs text-slate-500">· {{ $c->n }} vente(s)</span></span>
                        <span class="font-semibold tabular-nums">{{ $F::money($c->revenue) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-slate-500">Aucune vente sur la période.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white lg:col-span-2 xl:col-span-1">
            <div class="p-5 pb-3"><h2 class="font-semibold">Dernières ventes</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($recent as $s)
                    <li class="flex items-center gap-3 px-5 py-2.5">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('pos.receipt', $s) }}" target="_blank" class="font-medium hover:underline">{{ $s->reference }}</a>
                            <div class="truncate text-xs text-slate-500">{{ $s->created_at->format('d/m H:i') }} · {{ $s->cashier->name }}{{ $s->customer ? ' · '.$s->customer->name : '' }}</div>
                        </div>
                        @if ($s->status === \App\Models\Sale::CANCELLED)
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-500">Annulée</span>
                        @elseif ((float) $s->due_amount > 0)
                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-800">Crédit</span>
                        @endif
                        <span class="shrink-0 font-semibold tabular-nums {{ $s->status === \App\Models\Sale::CANCELLED ? 'text-slate-400 line-through' : '' }}">{{ $F::money($s->total) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-slate-500">Aucune vente.</li>
                @endforelse
            </ul>
        </div>
    </section>
</div>

<script type="application/json" id="dashboard-data">@json($charts)</script>
@endsection
