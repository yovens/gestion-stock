@extends('layouts.admin')

@section('title', 'Mouvements de stock')

@php $F = \App\Support\Format::class; @endphp

@section('actions')
    @can('stock.adjust')<a href="{{ route('stock-adjustments.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Déclarer une perte / un ajustement</a>@endcan
@endsection

@section('content')
<div class="space-y-4">
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
        <label>Du <input type="date" name="du" value="{{ $from->toDateString() }}" class="rounded-lg border-slate-300 text-sm"></label>
        <label>au <input type="date" name="au" value="{{ $to->toDateString() }}" class="rounded-lg border-slate-300 text-sm"></label>
        <input name="q" value="{{ request('q') }}" placeholder="Produit ou code" class="w-48 rounded-lg border-slate-300 text-sm">
        <select name="type" class="rounded-lg border-slate-300 text-sm">
            <option value="">Tous les types</option>
            @foreach ($types as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="utilisateur" class="rounded-lg border-slate-300 text-sm">
            <option value="">Tous les utilisateurs</option>
            @foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('utilisateur') == $u->id)>{{ $u->name }}</option>@endforeach
        </select>
        <button class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">Filtrer</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm tabular-nums">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-3">Date</th><th class="py-3">Produit</th><th class="py-3">Type</th><th class="py-3">Lot</th><th class="py-3 text-right">Quantité</th><th class="py-3 text-right">Stock après</th><th class="py-3">Par</th><th class="px-4 py-3">Motif</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($movements as $m)
                    @php $in = (float) $m->quantity > 0; @endphp
                    <tr>
                        <td class="whitespace-nowrap px-4 py-2.5">{{ $m->created_at->format('d/m H:i') }}</td>
                        <td class="py-2.5"><a href="{{ route('products.show', $m->product_id) }}" class="font-medium hover:underline">{{ $m->product?->name }}</a></td>
                        <td class="py-2.5">{{ $m->label() }}</td>
                        <td class="py-2.5 text-xs text-slate-500">{{ $m->batch?->batch_number ?: ($m->batch_id ? '#'.$m->batch_id : '—') }}</td>
                        <td class="py-2.5 text-right font-semibold {{ $in ? 'text-emerald-700' : 'text-rose-600' }}">{{ $in ? '+' : '−' }}{{ $F::qty(abs((float) $m->quantity)) }} <span class="text-xs font-normal text-slate-400">{{ $m->product?->baseUnit?->abbreviation }}</span></td>
                        <td class="py-2.5 text-right">{{ $F::qty($m->quantity_after) }}</td>
                        <td class="py-2.5">{{ $m->user?->name }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $m->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500">Aucun mouvement sur la période.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($movements->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $movements->links() }}</div>@endif
    </div>
</div>
@endsection
