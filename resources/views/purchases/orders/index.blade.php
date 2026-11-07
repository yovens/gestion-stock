@extends('layouts.admin')

@section('title', 'Bons de commande')

@php
    $F = \App\Support\Format::class;
    $badge = ['draft' => 'bg-slate-100 text-slate-700', 'sent' => 'bg-sky-100 text-sky-800', 'partially_received' => 'bg-amber-100 text-amber-800', 'received' => 'bg-emerald-100 text-emerald-800', 'cancelled' => 'bg-slate-100 text-slate-400 line-through'];
@endphp

@section('actions')
    <a href="{{ route('purchase-orders.create', ['stock_bas' => 1]) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Commander le stock bas</a>
    <a href="{{ route('purchase-orders.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Nouveau bon</a>
@endsection

@section('content')
<div class="space-y-4">
    <form method="GET" class="flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <select name="fournisseur" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            <option value="">Tous les fournisseurs</option>
            @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(request('fournisseur') == $s->id)>{{ $s->name }}</option>@endforeach
        </select>
        <select name="statut" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            <option value="">Tous les statuts</option>
            @foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $l }}</option>@endforeach
        </select>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-3">N°</th><th class="py-3">Fournisseur</th><th class="py-3">Date</th><th class="py-3">Livraison</th><th class="py-3 text-right">Lignes</th><th class="py-3 text-right">Total</th><th class="px-4 py-3">Statut</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $o)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3"><a href="{{ route('purchase-orders.show', $o) }}" class="font-semibold hover:underline">{{ $o->reference }}</a></td>
                        <td class="py-3">{{ $o->supplier->name }}</td>
                        <td class="py-3">{{ $o->order_date->format('d/m/Y') }}</td>
                        <td class="py-3 {{ $o->expected_date && $o->expected_date->isPast() && in_array($o->status, ['sent', 'partially_received']) ? 'font-semibold text-rose-600' : '' }}">{{ $o->expected_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="py-3 text-right">{{ $o->items_count }}</td>
                        <td class="py-3 text-right tabular-nums">{{ $F::money($o->total) }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[$o->status] }}">{{ $statuses[$o->status] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">Aucun bon de commande.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($orders->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $orders->links() }}</div>@endif
    </div>
</div>
@endsection
