@extends('layouts.admin')

@section('title', 'Ventes')

@php
    $F = \App\Support\Format::class;
    $badge = ['completed' => 'bg-emerald-100 text-emerald-800', 'partially_refunded' => 'bg-amber-100 text-amber-800', 'refunded' => 'bg-slate-200 text-slate-700', 'cancelled' => 'bg-rose-100 text-rose-700', 'held' => 'bg-sky-100 text-sky-800'];
@endphp

@section('content')
<div class="space-y-4">
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
        <label>Du <input type="date" name="du" value="{{ $from->toDateString() }}" class="rounded-lg border-slate-300 text-sm"></label>
        <label>au <input type="date" name="au" value="{{ $to->toDateString() }}" class="rounded-lg border-slate-300 text-sm"></label>
        <input name="q" value="{{ request('q') }}" placeholder="N° de vente" class="w-36 rounded-lg border-slate-300 text-sm">
        <select name="caissier" class="rounded-lg border-slate-300 text-sm">
            <option value="">Tous les caissiers</option>
            @foreach ($cashiers as $c)<option value="{{ $c->id }}" @selected(request('caissier') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
        <select name="statut" class="rounded-lg border-slate-300 text-sm">
            <option value="">Tous les statuts</option>
            @foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="mode" class="rounded-lg border-slate-300 text-sm">
            <option value="">Tous les paiements</option>
            @foreach ($methods as $k => $l)<option value="{{ $k }}" @selected(request('mode') === $k)>{{ $l }}</option>@endforeach
        </select>
        <label class="flex items-center gap-1"><input type="checkbox" name="paiement" value="credit" @checked(request('paiement') === 'credit') class="rounded border-slate-300"> À crédit</label>
        @foreach ($customers as $c)<input type="hidden" name="client" value="{{ $c->id }}"><span class="rounded-full bg-slate-100 px-3 py-1">Client : {{ $c->name }}</span>@endforeach
        <button class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">Filtrer</button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4"><div class="text-sm text-slate-500">Ventes</div><div class="text-xl font-bold">{{ $totals['count'] }}</div>@if ($totals['cancelled'])<div class="text-xs text-rose-600">+ {{ $totals['cancelled'] }} annulée(s)</div>@endif</div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4"><div class="text-sm text-slate-500">Chiffre d'affaires</div><div class="text-xl font-bold tabular-nums">{{ $F::money($totals['total']) }}</div></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4"><div class="text-sm text-slate-500">Dont à crédit</div><div class="text-xl font-bold tabular-nums">{{ $F::money($totals['credit']) }}</div></div>
        @can('reports.profit')<div class="rounded-2xl border border-slate-200 bg-white p-4"><div class="text-sm text-slate-500">Bénéfice brut</div><div class="text-xl font-bold tabular-nums">{{ $F::money($totals['profit']) }}</div></div>@endcan
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm tabular-nums">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-3">N°</th><th class="py-3">Date</th><th class="py-3">Caissier</th><th class="py-3">Client</th><th class="py-3 text-right">Articles</th><th class="py-3 text-right">Total</th><th class="py-3 text-right">Crédit</th><th class="px-4 py-3">Statut</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sales as $s)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5"><a href="{{ route('sales.show', $s) }}" class="font-semibold hover:underline">{{ $s->reference }}</a></td>
                        <td class="py-2.5">{{ $s->created_at->format('d/m H:i') }}</td>
                        <td class="py-2.5">{{ $s->cashier?->name }}</td>
                        <td class="py-2.5">{{ $s->customer?->name ?? '—' }}</td>
                        <td class="py-2.5 text-right">{{ $s->items_count }}</td>
                        <td class="py-2.5 text-right font-medium {{ $s->status === 'cancelled' ? 'text-slate-400 line-through' : '' }}">{{ $F::money($s->total) }}</td>
                        <td class="py-2.5 text-right {{ (float) $s->due_amount > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ (float) $s->due_amount > 0 ? $F::money($s->due_amount) : '—' }}</td>
                        <td class="px-4 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[$s->status] ?? '' }}">{{ $statuses[$s->status] ?? $s->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500">Aucune vente sur la période.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($sales->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $sales->links() }}</div>@endif
    </div>
</div>
@endsection
