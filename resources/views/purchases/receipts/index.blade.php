@extends('layouts.admin')

@section('title', 'Réceptions de marchandise')

@php
    $F = \App\Support\Format::class;
    $badge = ['unpaid' => 'bg-rose-100 text-rose-700', 'partial' => 'bg-amber-100 text-amber-800', 'paid' => 'bg-emerald-100 text-emerald-800'];
@endphp

@section('actions')
    <a href="{{ route('goods-receipts.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Nouvelle réception</a>
@endsection

@section('content')
<div class="space-y-4">
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
        <select name="fournisseur" class="rounded-lg border-slate-300 text-sm">
            <option value="">Tous les fournisseurs</option>
            @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(request('fournisseur') == $s->id)>{{ $s->name }}</option>@endforeach
        </select>
        <select name="paiement" class="rounded-lg border-slate-300 text-sm">
            <option value="">Tous les paiements</option>
            @foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected(request('paiement') === $k)>{{ $l }}</option>@endforeach
        </select>
        <label>Du <input type="date" name="du" value="{{ request('du') }}" class="rounded-lg border-slate-300 text-sm"></label>
        <label>au <input type="date" name="au" value="{{ request('au') }}" class="rounded-lg border-slate-300 text-sm"></label>
        <button class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">Filtrer</button>
        <div class="ml-auto text-right">
            <div>Total : <strong>{{ $F::money($totals['total']) }}</strong></div>
            <div class="text-slate-500">Reste à payer : <strong class="text-rose-600">{{ $F::money($totals['total'] - $totals['paid']) }}</strong></div>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm tabular-nums">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-3">N°</th><th class="py-3">Date</th><th class="py-3">Fournisseur</th><th class="py-3">Facture</th><th class="py-3 text-right">Lignes</th><th class="py-3 text-right">Total</th><th class="py-3 text-right">Reste</th><th class="px-4 py-3">Paiement</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($receipts as $r)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3"><a href="{{ route('goods-receipts.show', $r) }}" class="font-semibold hover:underline">{{ $r->reference }}</a></td>
                        <td class="py-3">{{ $r->received_date->format('d/m/Y') }}</td>
                        <td class="py-3">{{ $r->supplier->name }}</td>
                        <td class="py-3 text-slate-500">{{ $r->supplier_invoice_number ?: '—' }}</td>
                        <td class="py-3 text-right">{{ $r->items_count }}</td>
                        <td class="py-3 text-right">{{ $F::money($r->total) }}</td>
                        <td class="py-3 text-right {{ $r->dueAmount() > 0 ? 'font-semibold text-rose-600' : 'text-slate-400' }}">{{ $F::money($r->dueAmount()) }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[$r->payment_status] ?? '' }}">{{ $statuses[$r->payment_status] ?? $r->payment_status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500">Aucune réception.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($receipts->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $receipts->links() }}</div>@endif
    </div>
</div>
@endsection
