@extends('layouts.admin')

@section('title', 'Inventaire '.$inventory->reference)

@php
    $F = \App\Support\Format::class;
    $open = $inventory->status === 'in_progress';
    $counted = $inventory->items->whereNotNull('counted_quantity')->count();
    $total = $inventory->items->count();
    $gap = $inventory->items->whereNotNull('difference')->sum(fn ($i) => (float) $i->difference * (float) $i->unit_cost);
@endphp

@section('actions')
    <button onclick="window.print()" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Imprimer la feuille de comptage</button>
    @if ($open)
        <form method="POST" action="{{ route('inventories.cancel', $inventory) }}" onsubmit="return confirm('Annuler cet inventaire ?')">@csrf
            <button class="rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Annuler</button>
        </form>
    @endif
@endsection

@section('content')
<form method="POST" action="{{ route('inventories.save', $inventory) }}" x-data="{ showSystem: false, filter: '' }" class="space-y-4">
    @csrf
    <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm print:hidden">
        <div><strong>{{ $inventory->category?->name ?? 'Tout le dépôt' }}</strong> · commencé le {{ $inventory->started_at->format('d/m/Y H:i') }} par {{ $inventory->user?->name }}</div>
        @if ($open)
            <div class="flex items-center gap-2">
                <div class="h-2 w-40 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" style="width: {{ $total ? round($counted / $total * 100) : 0 }}%"></div></div>
                {{ $counted }} / {{ $total }} compté(s)
            </div>
            <label class="flex items-center gap-2"><input type="checkbox" x-model="showSystem" class="rounded border-slate-300"> Afficher le stock théorique</label>
            <input x-model="filter" placeholder="Filtrer…" class="ml-auto w-48 rounded-lg border-slate-300 text-sm">
        @else
            <div class="ml-auto">Validé par {{ $inventory->validator?->name }} le {{ $inventory->completed_at?->format('d/m/Y H:i') }} · écart total <strong class="{{ $gap < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $F::money($gap) }}</strong></div>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm tabular-nums">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-3">Emplacement</th><th class="py-3">Produit</th>
                    <th class="py-3 text-right" @if ($open) x-show="showSystem" @endif>Théorique</th>
                    <th class="py-3 text-right">Compté</th>
                    @unless ($open)<th class="py-3 text-right">Écart</th><th class="px-4 py-3 text-right">Valeur</th>@endunless
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($inventory->items as $i)
                    @php $p = $i->product; $big = $p->units->sortByDesc('conversion_factor')->first(); @endphp
                    <tr x-show="!filter || @js(mb_strtolower($p->name.' '.$p->sku.' '.$p->location)).includes(filter.toLowerCase())">
                        <td class="px-4 py-2 text-xs text-slate-500">{{ $p->location ?: '—' }}</td>
                        <td class="py-2"><span class="font-medium">{{ $p->name }}</span> <span class="text-xs text-slate-400">{{ $p->sku }}</span>
                            @if ($big)<span class="block text-xs text-slate-400">1 {{ $big->unit?->name }} = {{ $F::qty($big->conversion_factor) }} {{ $p->baseUnit?->abbreviation }}</span>@endif</td>
                        <td class="py-2 text-right text-slate-500" @if ($open) x-show="showSystem" @endif>{{ $F::qty($open ? $p->stock_quantity : $i->system_quantity) }}</td>
                        <td class="py-2 text-right">
                            @if ($open)
                                <input type="number" step="0.001" min="0" name="counted[{{ $i->id }}]" value="{{ $i->counted_quantity !== null ? (float) $i->counted_quantity : '' }}"
                                       data-system="{{ (float) $p->stock_quantity }}" class="w-28 rounded-lg border-slate-300 text-right text-sm print:border-0">
                                <span class="text-xs text-slate-400">{{ $p->baseUnit?->abbreviation }}</span>
                            @else
                                {{ $F::qty($i->counted_quantity) }}
                            @endif
                        </td>
                        @unless ($open)
                            <td class="py-2 text-right font-semibold {{ (float) $i->difference < 0 ? 'text-rose-600' : ((float) $i->difference > 0 ? 'text-emerald-700' : 'text-slate-400') }}">{{ (float) $i->difference > 0 ? '+' : '' }}{{ $F::qty($i->difference) }}</td>
                            <td class="px-4 py-2 text-right text-slate-600">{{ $F::money((float) $i->difference * (float) $i->unit_cost) }}</td>
                        @endunless
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($open)
        <div class="sticky bottom-0 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur print:hidden">
            <button type="button" class="mr-auto text-sm text-slate-600 hover:underline"
                    onclick="if (confirm('Mettre le stock théorique dans toutes les cases vides ?')) document.querySelectorAll('[data-system]').forEach(i => { if (i.value === '') i.value = i.dataset.system; })">
                Remplir les cases vides avec le stock théorique
            </button>
            <button class="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Enregistrer le comptage</button>
            @can('inventory.validate')
                <button name="validate" value="1" onclick="return confirm('Valider ? Chaque écart sera appliqué au stock (pertes ou surplus).')"
                        class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Valider l'inventaire</button>
            @endcan
        </div>
    @endif
</form>
@endsection
