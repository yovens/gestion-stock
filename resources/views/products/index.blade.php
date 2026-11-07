@extends('layouts.admin')

@section('title', 'Produits')

@php $F = \App\Support\Format::class; @endphp

@section('actions')
    @can('products.create')
        <a href="{{ route('products.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            <span aria-hidden="true">+</span> Nouveau produit
        </a>
    @endcan
@endsection

@section('content')
<div class="space-y-4">
    {{-- Filtres --}}
    <form method="GET" class="rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative min-w-64 flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom, code interne ou code-barres" autofocus
                       class="w-full rounded-lg border-slate-300 pl-9 text-sm">
            </div>
            <select name="category" class="rounded-lg border-slate-300 text-sm" onchange="this.form.submit()">
                <option value="">Toutes les catégories</option>
                @foreach ($categories->whereNull('parent_id') as $parent)
                    <option value="{{ $parent->id }}" @selected(($filters['category'] ?? '') == $parent->id)>{{ $parent->name }}</option>
                    @foreach ($categories->where('parent_id', $parent->id) as $child)
                        <option value="{{ $child->id }}" @selected(($filters['category'] ?? '') == $child->id)>&nbsp;&nbsp;↳ {{ $child->name }}</option>
                    @endforeach
                @endforeach
            </select>
            <select name="sort" class="rounded-lg border-slate-300 text-sm" onchange="this.form.submit()">
                @foreach (['name' => 'Nom A→Z', 'stock' => 'Stock croissant', 'price' => 'Prix décroissant', 'recent' => 'Plus récents'] as $k => $l)
                    <option value="{{ $k }}" @selected(($filters['sort'] ?? 'name') === $k)>{{ $l }}</option>
                @endforeach
            </select>
            <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filtrer</button>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            @php $chip = fn ($active) => $active ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; @endphp
            <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" class="rounded-full px-3 py-1 font-medium {{ $chip(empty($filters['status'])) }}">Actifs · {{ $counts['all'] }}</a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'low', 'page' => null]) }}" class="rounded-full px-3 py-1 font-medium {{ $chip(($filters['status'] ?? '') === 'low') }}">Stock bas · {{ $counts['low'] }}</a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'out', 'page' => null]) }}" class="rounded-full px-3 py-1 font-medium {{ $chip(($filters['status'] ?? '') === 'out') }}">Rupture · {{ $counts['out'] }}</a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'expiring', 'page' => null]) }}" class="rounded-full px-3 py-1 font-medium {{ $chip(($filters['status'] ?? '') === 'expiring') }}">Expire sous {{ $expiryDays }} j</a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'inactive', 'page' => null]) }}" class="rounded-full px-3 py-1 font-medium {{ $chip(($filters['status'] ?? '') === 'inactive') }}">Inactifs · {{ $counts['inactive'] }}</a>
            @can('reports.profit')
                <span class="ml-auto text-slate-500">Valeur du stock : <strong class="text-slate-800">{{ $F::money($stockValue) }}</strong></span>
            @endcan
        </div>
    </form>

    {{-- Liste --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Produit</th>
                        <th class="px-4 py-3">Catégorie</th>
                        <th class="px-4 py-3 text-right">Prix détail</th>
                        <th class="px-4 py-3 text-right">Prix gros</th>
                        <th class="px-4 py-3 text-right">Stock</th>
                        <th class="px-4 py-3">Prochaine exp.</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $p)
                        @php
                            $stock = (float) $p->stock_quantity;
                            $exp = $p->next_expiry ? \Illuminate\Support\Carbon::parse($p->next_expiry) : null;
                            $expDays = $exp ? (int) today()->diffInDays($exp, false) : null;
                        @endphp
                        <tr class="hover:bg-slate-50 {{ $p->is_active ? '' : 'opacity-60' }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('products.show', $p) }}" class="flex items-center gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-100 text-xs font-bold text-slate-400">
                                        @if ($p->image)<img src="{{ asset('storage/'.$p->image) }}" alt="" class="h-full w-full object-cover">@else{{ mb_strtoupper(mb_substr($p->name, 0, 2)) }}@endif
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-slate-900 hover:underline">{{ $p->name }}</span>
                                        <span class="block text-xs text-slate-500">{{ $p->sku }}{{ $p->barcode ? ' · '.$p->barcode : '' }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 text-slate-600">
                                    @if ($p->category?->color)<span class="h-2 w-2 rounded-full" style="background: {{ $p->category->color }}" aria-hidden="true"></span>@endif
                                    {{ $p->category?->name }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ $F::money($p->retail_price) }}<span class="text-xs text-slate-400"> /{{ $p->baseUnit?->abbreviation }}</span>
                                @foreach ($p->units as $u)
                                    <div class="text-xs text-slate-500">{{ $F::money($u->retail_price) }} /{{ $u->unit?->abbreviation }}</div>
                                @endforeach
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600">
                                {{ $p->wholesale_price !== null ? $F::money($p->wholesale_price) : '—' }}
                                @foreach ($p->units as $u)
                                    <div class="text-xs text-slate-500">{{ $u->wholesale_price !== null ? $F::money($u->wholesale_price).' /'.$u->unit?->abbreviation : '' }}</div>
                                @endforeach
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <div class="font-semibold">{{ $F::qty($stock) }} {{ $p->baseUnit?->abbreviation }}</div>
                                @if ($stock <= 0)
                                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-rose-700">Rupture</span>
                                @elseif ($stock <= (float) $p->min_stock)
                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-800">Bas</span>
                                @endif
                                @if (abs($stock - (float) $p->sellable_qty) > 0.001)
                                    <div class="text-xs text-rose-600">dont {{ $F::qty($stock - (float) $p->sellable_qty) }} expiré(s)</div>
                                @endif
                                @php $big = $p->units->sortByDesc('conversion_factor')->first(); @endphp
                                @if ($big && $stock > 0)
                                    <div class="text-xs text-slate-500">≈ {{ $F::qty(floor($stock / (float) $big->conversion_factor)) }} {{ $big->unit?->abbreviation }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if ($exp)
                                    <span class="{{ $expDays < 0 ? 'font-semibold text-rose-600' : ($expDays <= $expiryDays ? 'font-semibold text-amber-700' : 'text-slate-600') }}">
                                        {{ $exp->format('d/m/Y') }}
                                        <span class="block">{{ $expDays < 0 ? 'expiré' : 'dans '.$expDays.' j' }}</span>
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('products.edit')
                                    <a href="{{ route('products.edit', $p) }}" class="rounded-md px-2 py-1 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900">Modifier</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-16 text-center text-slate-500">
                            Aucun produit trouvé.
                            @can('products.create')<a href="{{ route('products.create') }}" class="font-semibold text-emerald-700 hover:underline">Ajouter un produit</a>@endcan
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $products->links() }}</div>
        @endif
    </div>
</div>
@endsection
