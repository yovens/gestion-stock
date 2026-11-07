@extends('layouts.admin')

@section('title', 'Dates d\'expiration')

@php $F = \App\Support\Format::class; @endphp

@section('actions')
    <form method="GET" class="flex items-center gap-2 text-sm">
        <label>Afficher ce qui expire dans</label>
        <select name="jours" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            @foreach ([15, 30, 60, 90, 180] as $d)<option value="{{ $d }}" @selected($days === $d)>{{ $d }} jours</option>@endforeach
        </select>
    </form>
@endsection

@section('content')
<div class="space-y-6">
    @foreach ([['Expirés : à retirer des rayons', $expired, true], ['Expire dans les '.$days.' prochains jours : à vendre en priorité', $soon, false]] as [$title, $batches, $isExpired])
        <div class="overflow-hidden rounded-2xl border {{ $isExpired && $batches->isNotEmpty() ? 'border-rose-200' : 'border-slate-200' }} bg-white">
            <div class="flex items-baseline justify-between p-5 pb-3">
                <h2 class="font-semibold {{ $isExpired && $batches->isNotEmpty() ? 'text-rose-700' : '' }}">{{ $title }}</h2>
                <span class="text-sm text-slate-500">{{ $batches->count() }} lot(s) · valeur {{ $F::money($batches->sum(fn ($b) => (float) $b->remaining_quantity * (float) $b->unit_cost)) }}</span>
            </div>
            <table class="w-full text-sm tabular-nums">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-5 py-2">Produit</th><th class="py-2">Lot</th><th class="py-2">Expiration</th><th class="py-2 text-right">Quantité</th><th class="py-2 text-right">Valeur</th><th class="px-5 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($batches as $b)
                        @php $d = $b->daysUntilExpiry(); @endphp
                        <tr>
                            <td class="px-5 py-2.5"><a href="{{ route('products.show', $b->product) }}" class="font-medium hover:underline">{{ $b->product->name }}</a>
                                @if ($b->product->location)<span class="block text-xs text-slate-400">📍 {{ $b->product->location }}</span>@endif</td>
                            <td class="py-2.5">{{ $b->batch_number ?: '#'.$b->id }}</td>
                            <td class="py-2.5">{{ $b->expiry_date->format('d/m/Y') }}
                                <span class="ml-1 rounded px-1.5 py-0.5 text-[11px] font-bold {{ $d < 0 ? 'bg-rose-100 text-rose-700' : ($d <= 7 ? 'bg-rose-50 text-rose-700' : 'bg-amber-100 text-amber-800') }}">{{ $d < 0 ? 'depuis '.abs($d).' j' : ($d === 0 ? 'aujourd\'hui' : 'dans '.$d.' j') }}</span></td>
                            <td class="py-2.5 text-right">{{ $F::qty($b->remaining_quantity) }} {{ $b->product->baseUnit?->abbreviation }}</td>
                            <td class="py-2.5 text-right">{{ $F::money((float) $b->remaining_quantity * (float) $b->unit_cost) }}</td>
                            <td class="px-5 py-2.5 text-right">
                                @can('stock.adjust')
                                    <a href="{{ route('stock-adjustments.create', ['lot' => $b->id]) }}" class="text-xs font-semibold {{ $isExpired ? 'text-rose-700' : 'text-slate-600' }} hover:underline">{{ $isExpired ? 'Déclarer en perte' : 'Retirer' }}</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-emerald-700">✓ Rien à signaler.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
</div>
@endsection
