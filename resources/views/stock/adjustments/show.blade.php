@extends('layouts.admin')

@section('title', 'Ajustement '.$adjustment->reference)

@php
    $F = \App\Support\Format::class;
    $labels = ['pending' => 'En attente d\'approbation', 'approved' => 'Appliqué au stock', 'rejected' => 'Refusé'];
    $badge = ['pending' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-emerald-100 text-emerald-800', 'rejected' => 'bg-slate-100 text-slate-500'];
@endphp

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[$adjustment->status] }}">{{ $labels[$adjustment->status] }}</span>
                <div class="mt-2 text-lg font-semibold">{{ $types[$adjustment->type] ?? $adjustment->type }}</div>
                <div class="text-sm text-slate-600">{{ $adjustment->adjustment_date->format('d/m/Y') }} · déclaré par {{ $adjustment->user?->name }}
                    @if ($adjustment->approver) · {{ $adjustment->status === 'approved' ? 'approuvé' : 'refusé' }} par {{ $adjustment->approver->name }} le {{ $adjustment->approved_at?->format('d/m/Y H:i') }}@endif</div>
                @if ($adjustment->notes)<p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $adjustment->notes }}</p>@endif
            </div>
            <div class="text-right"><div class="text-xs uppercase text-slate-500">Valeur</div><div class="text-2xl font-bold tabular-nums {{ $adjustment->totalValue() < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $F::money($adjustment->totalValue()) }}</div></div>
        </div>

        <table class="mt-6 w-full text-sm tabular-nums">
            <thead class="border-y border-slate-200 text-left text-xs uppercase text-slate-500"><tr><th class="py-2">Produit</th><th class="py-2">Lot</th><th class="py-2 text-right">Quantité</th><th class="py-2 text-right">Coût unit.</th><th class="py-2">Motif</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($adjustment->items as $i)
                    <tr>
                        <td class="py-2">{{ $i->product->name }}</td>
                        <td class="py-2 text-xs text-slate-500">{{ $i->batch ? ($i->batch->batch_number ?: '#'.$i->batch_id).($i->batch->expiry_date ? ' · exp. '.$i->batch->expiry_date->format('d/m/Y') : '') : 'automatique' }}</td>
                        <td class="py-2 text-right font-semibold {{ (float) $i->quantity < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ (float) $i->quantity > 0 ? '+' : '' }}{{ $F::qty($i->quantity) }} {{ $i->product->baseUnit?->abbreviation }}</td>
                        <td class="py-2 text-right">{{ $F::money($i->unit_cost) }}</td>
                        <td class="py-2 text-slate-600">{{ $i->reason }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($adjustment->status === 'pending')
        @can('stock.approve_adjustment')
            <div class="flex flex-wrap justify-end gap-3">
                <form method="POST" action="{{ route('stock-adjustments.reject', $adjustment) }}" class="flex gap-2">
                    @csrf
                    <input name="reason" placeholder="Raison du refus" class="rounded-lg border-slate-300 text-sm">
                    <button class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Refuser</button>
                </form>
                <form method="POST" action="{{ route('stock-adjustments.approve', $adjustment) }}" onsubmit="return confirm('Appliquer cet ajustement au stock ?')">
                    @csrf
                    <button class="rounded-lg bg-emerald-600 px-6 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approuver et appliquer</button>
                </form>
            </div>
        @else
            <p class="text-right text-sm text-slate-500">En attente de l'approbation de l'administrateur.</p>
        @endcan
    @endif
</div>
@endsection
