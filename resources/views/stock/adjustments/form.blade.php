@extends('layouts.admin')

@section('title', 'Déclarer une perte / un ajustement')

@section('content')
<form method="POST" action="{{ route('stock-adjustments.store') }}" x-data="adjustmentLines(@js(['url' => route('stock.lookup'), 'lines' => $prefill]))"
      @submit="if (lines.some(l => tooMuch(l))) { $event.preventDefault(); alert('Une quantité dépasse le stock disponible.'); }"
      class="mx-auto max-w-5xl space-y-6">
    @csrf
    @if ($errors->any())<div class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

    <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-3">
        <div>
            <label class="block text-sm font-medium">Type *</label>
            <select name="type" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                @foreach ($types as $k => $l)<option value="{{ $k }}" @selected($type === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">Date *</label>
            <input type="date" name="adjustment_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium">Remarque</label>
            <input name="notes" maxlength="1000" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Ex. : sacs mouillés après la pluie">
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="relative border-b border-slate-200 p-5" @click.outside="open = false">
            <input x-model="query" @input="onInput" @keydown.enter.prevent="onEnter" placeholder="Chercher ou scanner le produit concerné…" class="w-full rounded-lg border-slate-300 text-sm">
            <div x-show="open" x-cloak class="absolute left-5 right-5 z-30 mt-1 max-h-72 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                <template x-for="p in results" :key="p.id">
                    <button type="button" @click="add(p)" class="flex w-full justify-between px-4 py-2.5 text-left text-sm hover:bg-emerald-50">
                        <span x-text="p.name"></span><span class="text-xs text-slate-500" x-text="'stock ' + p.stock + ' ' + p.abbr"></span>
                    </button>
                </template>
                <p x-show="!results.length" class="px-4 py-3 text-sm text-slate-500">Aucun produit.</p>
            </div>
        </div>

        <div class="divide-y divide-slate-100">
            <template x-for="(l, i) in lines" :key="l._key">
                <div class="grid items-end gap-3 p-4 md:grid-cols-12" :class="tooMuch(l) && 'bg-rose-50'">
                    <input type="hidden" :name="`items[${i}][product_id]`" :value="l.product.id">
                    <div class="md:col-span-3">
                        <div class="font-medium" x-text="l.product.name"></div>
                        <div class="text-xs text-slate-500" x-text="'Stock : ' + l.product.stock + ' ' + l.product.abbr"></div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Sens</label>
                        <select :name="`items[${i}][direction]`" x-model="l.direction" class="mt-0.5 w-full rounded-lg border-slate-300 text-sm">
                            <option value="out">Sortie (−)</option>
                            <option value="in">Entrée (+)</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="text-xs text-slate-500">Lot</label>
                        <select :name="`items[${i}][batch_id]`" x-model="l.batch_id" class="mt-0.5 w-full rounded-lg border-slate-300 text-sm">
                            <option value="" x-text="l.direction === 'out' ? 'Automatique (le plus ancien)' : 'Nouveau lot'"></option>
                            <template x-for="b in l.product.batches" :key="b.id">
                                <option :value="b.id" x-text="(b.expired ? '⚠ ' : '') + b.label" :selected="Number(l.batch_id) === b.id"></option>
                            </template>
                        </select>
                    </div>
                    <div class="md:col-span-1">
                        <label class="text-xs text-slate-500">Quantité</label>
                        <input :name="`items[${i}][quantity]`" x-model="l.quantity" type="number" step="0.001" min="0.001" required class="mt-0.5 w-full rounded-lg border-slate-300 text-right text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Motif</label>
                        <input :name="`items[${i}][reason]`" x-model="l.reason" maxlength="255" class="mt-0.5 w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div class="text-right md:col-span-1">
                        <div class="text-xs tabular-nums" :class="value(l) < 0 ? 'text-rose-600' : 'text-emerald-700'" x-text="money(value(l))"></div>
                        <button type="button" @click="remove(i)" class="text-xs text-slate-400 hover:text-rose-600">Retirer</button>
                    </div>
                    <p x-show="tooMuch(l)" class="text-xs font-semibold text-rose-600 md:col-span-12" x-text="'Maximum disponible : ' + maxOut(l)"></p>
                </div>
            </template>
            <p x-show="!lines.length" class="px-5 py-10 text-center text-sm text-slate-500">Ajoutez les produits concernés.</p>
        </div>
        <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-3 text-sm">Impact sur la valeur du stock : <strong class="ml-2 tabular-nums" :class="total < 0 ? 'text-rose-600' : 'text-emerald-700'" x-text="money(total)"></strong></div>
    </section>

    <div class="flex flex-wrap items-center justify-end gap-3">
        @can('stock.approve_adjustment')
            <label class="mr-auto flex items-center gap-2 text-sm"><input type="checkbox" name="approve_now" value="1" checked class="rounded border-slate-300"> Appliquer tout de suite (je suis administrateur)</label>
        @else
            <p class="mr-auto text-sm text-slate-500">L'ajustement sera appliqué après approbation de l'administrateur.</p>
        @endcan
        <a href="{{ route('stock-adjustments.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Annuler</a>
        <button :disabled="!lines.length" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-40">Enregistrer</button>
    </div>
</form>
@endsection
