@extends('layouts.admin')

@section('title', $product->name)

@php
    $F = \App\Support\Format::class;
    $abbr = $product->baseUnit?->abbreviation;
    $stock = (float) $product->stock_quantity;
    $sellable = (float) $product->sellable_qty;
@endphp

@section('actions')
    @can('products.create')
        <a href="{{ route('products.create', ['copier' => $product->id]) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Dupliquer</a>
    @endcan
    @can('products.edit')
        <form method="POST" action="{{ route('products.toggle', $product) }}">
            @csrf @method('PATCH')
            <button class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">{{ $product->is_active ? 'Désactiver' : 'Réactiver' }}</button>
        </form>
        <a href="{{ route('products.edit', $product) }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Modifier</a>
    @endcan
@endsection

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-4">
        <span class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-2xl bg-slate-100 text-lg font-bold text-slate-400">
            @if ($product->image)<img src="{{ asset('storage/'.$product->image) }}" alt="" class="h-full w-full object-cover">@else{{ mb_strtoupper(mb_substr($product->name, 0, 2)) }}@endif
        </span>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <a href="{{ route('products.index') }}" class="hover:underline">Produits</a> ›
                {{ $product->category?->parent ? $product->category->parent->name.' › ' : '' }}{{ $product->category?->name }}
                @if ($product->brand)· {{ $product->brand->name }}@endif
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded bg-slate-100 px-2 py-0.5 font-mono">{{ $product->sku }}</span>
                @if ($product->barcode)<span class="rounded bg-slate-100 px-2 py-0.5 font-mono">▮▯▮ {{ $product->barcode }}</span>@endif
                @unless ($product->is_active)<span class="rounded bg-slate-200 px-2 py-0.5 font-semibold uppercase text-slate-600">Inactif</span>@endunless
                @if ($product->location)<span class="text-slate-500">📍 {{ $product->location }}</span>@endif
            </div>
        </div>
    </div>

    {{-- Chiffres clés --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Stock</div>
            <div class="mt-1 text-2xl font-bold tabular-nums">{{ $F::qty($stock) }} <span class="text-base font-medium text-slate-500">{{ $abbr }}</span></div>
            <div class="mt-1 flex flex-wrap gap-2 text-xs">
                @if ($stock <= 0)
                    <span class="rounded bg-rose-100 px-1.5 py-0.5 font-bold uppercase text-rose-700">Rupture</span>
                @elseif ($stock <= (float) $product->min_stock)
                    <span class="rounded bg-amber-100 px-1.5 py-0.5 font-bold uppercase text-amber-800">Sous le minimum ({{ $F::qty($product->min_stock) }})</span>
                @else
                    <span class="text-slate-500">minimum {{ $F::qty($product->min_stock) }}{{ $product->max_stock ? ' · max '.$F::qty($product->max_stock) : '' }}</span>
                @endif
            </div>
            @foreach ($product->units as $u)
                <div class="mt-1 text-xs text-slate-500">≈ {{ $F::qty(floor($stock / (float) $u->conversion_factor)) }} {{ $u->unit?->name }}(s)</div>
            @endforeach
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Vendable (non expiré)</div>
            <div class="mt-1 text-2xl font-bold tabular-nums">{{ $F::qty($sellable) }} <span class="text-base font-medium text-slate-500">{{ $abbr }}</span></div>
            @if ($stock - $sellable > 0.001)
                <div class="mt-1 text-xs font-semibold text-rose-600">{{ $F::qty($stock - $sellable) }} {{ $abbr }} expiré(s) à retirer</div>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Vendu sur 30 jours</div>
            <div class="mt-1 text-2xl font-bold tabular-nums">{{ $F::qty($sold30) }} <span class="text-base font-medium text-slate-500">{{ $abbr }}</span></div>
            <div class="mt-1 text-xs text-slate-500">
                @if ($daysOfStock !== null)
                    Le stock actuel tient environ <strong class="{{ $daysOfStock < 7 ? 'text-rose-600' : 'text-slate-700' }}">{{ $daysOfStock }} jour(s)</strong>
                @else
                    Aucune vente récente
                @endif
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Prix détail / gros</div>
            <div class="mt-1 text-2xl font-bold tabular-nums">{{ $F::money($product->retail_price) }}</div>
            <div class="mt-1 text-xs text-slate-500">
                gros {{ $product->wholesale_price !== null ? $F::money($product->wholesale_price) : '—' }}{{ $product->wholesale_min_qty ? ' dès '.$F::qty($product->wholesale_min_qty).' '.$abbr : '' }}
                @can('reports.profit')
                    <br>coût moyen {{ $F::money($product->cost_price) }} · marge {{ $F::percent($product->marginPercent()) }}
                @endcan
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        {{-- Conditionnements --}}
        <div class="rounded-2xl border border-slate-200 bg-white">
            <h2 class="p-5 pb-3 font-semibold">Conditionnements</h2>
            <table class="w-full text-sm tabular-nums">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-5 py-2">Unité</th><th class="py-2">Contenu</th><th class="py-2 text-right">Détail</th><th class="py-2 text-right">Gros</th><th class="px-5 py-2">Code-barres</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="px-5 py-2.5 font-medium">{{ $product->baseUnit?->name }} <span class="text-xs text-slate-400">(base)</span></td>
                        <td class="py-2.5">1 {{ $abbr }}</td>
                        <td class="py-2.5 text-right">{{ $F::money($product->retail_price) }}</td>
                        <td class="py-2.5 text-right">{{ $product->wholesale_price !== null ? $F::money($product->wholesale_price) : '—' }}</td>
                        <td class="px-5 py-2.5 font-mono text-xs">{{ $product->barcode ?: '—' }}</td>
                    </tr>
                    @foreach ($product->units as $u)
                        <tr>
                            <td class="px-5 py-2.5 font-medium">{{ $u->unit?->name }} @if ($u->is_default_sale_unit)<span class="rounded bg-emerald-50 px-1 text-[10px] font-bold uppercase text-emerald-700">défaut</span>@endif</td>
                            <td class="py-2.5">{{ $F::qty($u->conversion_factor) }} {{ $abbr }}</td>
                            <td class="py-2.5 text-right">{{ $F::money($u->retail_price) }}</td>
                            <td class="py-2.5 text-right">{{ $u->wholesale_price !== null ? $F::money($u->wholesale_price) : '—' }}{{ $u->wholesale_min_qty ? ' dès '.$F::qty($u->wholesale_min_qty) : '' }}</td>
                            <td class="px-5 py-2.5 font-mono text-xs">{{ $u->barcode ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Lots --}}
        <div class="rounded-2xl border border-slate-200 bg-white">
            <div class="p-5 pb-3">
                <h2 class="font-semibold">Lots en stock</h2>
                <p class="text-xs text-slate-500">Dans l'ordre de sortie à la caisse (le plus proche de l'expiration d'abord).</p>
            </div>
            <table class="w-full text-sm tabular-nums">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-5 py-2">Lot</th><th class="py-2">Expiration</th><th class="py-2 text-right">Reste</th>@can('reports.profit')<th class="px-5 py-2 text-right">Coût</th>@endcan</tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($batches as $b)
                        @php $d = $b->daysUntilExpiry(); @endphp
                        <tr class="{{ $b->isExpired() ? 'bg-rose-50' : '' }}">
                            <td class="px-5 py-2.5">{{ $b->batch_number ?: '#'.$b->id }} <span class="block text-xs text-slate-400">entré le {{ $b->created_at->format('d/m/Y') }}</span></td>
                            <td class="py-2.5">
                                @if ($b->expiry_date)
                                    {{ $b->expiry_date->format('d/m/Y') }}
                                    <span class="block text-xs {{ $d < 0 ? 'font-semibold text-rose-600' : ($d <= $product->expiry_alert_days ? 'font-semibold text-amber-700' : 'text-slate-400') }}">{{ $d < 0 ? 'EXPIRÉ' : 'dans '.$d.' j' }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-2.5 text-right">{{ $F::qty($b->remaining_quantity) }} <span class="text-xs text-slate-400">/ {{ $F::qty($b->initial_quantity) }}</span></td>
                            @can('reports.profit')<td class="px-5 py-2.5 text-right">{{ $F::money($b->unit_cost) }}</td>@endcan
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">Aucun lot en stock.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mouvements --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        <div class="p-5 pb-3">
            <h2 class="font-semibold">Historique des mouvements</h2>
            <p class="text-xs text-slate-500">Chaque entrée et sortie, avec la personne et le document d'origine.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm tabular-nums">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-5 py-2">Date</th><th class="py-2">Type</th><th class="py-2">Lot</th><th class="py-2 text-right">Quantité</th><th class="py-2 text-right">Stock après</th><th class="py-2">Par</th><th class="px-5 py-2">Motif / document</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($movements as $m)
                        @php $in = (float) $m->quantity > 0; @endphp
                        <tr>
                            <td class="whitespace-nowrap px-5 py-2.5">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2.5">{{ $m->label() }}</td>
                            <td class="py-2.5 text-xs text-slate-500">{{ $m->batch?->batch_number ?: ($m->batch_id ? '#'.$m->batch_id : '—') }}</td>
                            <td class="py-2.5 text-right font-semibold {{ $in ? 'text-emerald-700' : 'text-rose-600' }}">{{ $in ? '+' : '−' }}{{ $F::qty(abs((float) $m->quantity)) }}</td>
                            <td class="py-2.5 text-right">{{ $F::qty($m->quantity_after) }}</td>
                            <td class="py-2.5">{{ $m->user?->name }}</td>
                            <td class="px-5 py-2.5 text-xs text-slate-500">
                                {{ $m->reason }}
                                @if ($m->reference_type === (new \App\Models\Sale)->getMorphClass() && Route::has('pos.receipt'))
                                    <a href="{{ route('pos.receipt', $m->reference_id) }}" target="_blank" class="text-emerald-700 hover:underline">voir la vente</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-slate-500">Aucun mouvement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($movements->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $movements->links() }}</div>@endif
    </div>

    @can('products.delete')
        @if ($stock <= 0)
            <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Supprimer définitivement « {{ addslashes($product->name) }} » de la liste ?')" class="text-right">
                @csrf @method('DELETE')
                <button class="text-sm font-medium text-rose-600 hover:underline">Supprimer ce produit</button>
            </form>
        @endif
    @endcan
</div>
@endsection
