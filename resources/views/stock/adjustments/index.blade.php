@extends('layouts.admin')

@section('title', 'Pertes et ajustements')

@php
    $F = \App\Support\Format::class;
    $badge = ['pending' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-emerald-100 text-emerald-800', 'rejected' => 'bg-slate-100 text-slate-500'];
    $labels = ['pending' => 'En attente', 'approved' => 'Appliqué', 'rejected' => 'Refusé'];
@endphp

@section('actions')
    @can('stock.adjust')<a href="{{ route('stock-adjustments.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Nouvel ajustement</a>@endcan
@endsection

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap gap-2 text-sm">
        @foreach (['' => 'Tous', 'pending' => 'En attente ('.$pending.')', 'approved' => 'Appliqués', 'rejected' => 'Refusés'] as $k => $l)
            <a href="{{ route('stock-adjustments.index', array_filter(['statut' => $k])) }}" class="rounded-full px-3 py-1 font-medium {{ request('statut', '') === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">{{ $l }}</a>
        @endforeach
    </div>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm tabular-nums">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-3">N°</th><th class="py-3">Date</th><th class="py-3">Type</th><th class="py-3 text-right">Lignes</th><th class="py-3 text-right">Valeur</th><th class="py-3">Déclaré par</th><th class="px-4 py-3">Statut</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($adjustments as $a)
                    @php $v = $a->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_cost); @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5"><a href="{{ route('stock-adjustments.show', $a) }}" class="font-semibold hover:underline">{{ $a->reference }}</a></td>
                        <td class="py-2.5">{{ $a->adjustment_date->format('d/m/Y') }}</td>
                        <td class="py-2.5">{{ $types[$a->type] ?? $a->type }}</td>
                        <td class="py-2.5 text-right">{{ $a->items_count }}</td>
                        <td class="py-2.5 text-right {{ $v < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $F::money($v) }}</td>
                        <td class="py-2.5">{{ $a->user?->name }}</td>
                        <td class="px-4 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[$a->status] }}">{{ $labels[$a->status] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">Aucun ajustement.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($adjustments->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $adjustments->links() }}</div>@endif
    </div>
</div>
@endsection
