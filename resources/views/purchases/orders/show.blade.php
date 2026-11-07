@extends('layouts.admin')

@section('title', 'Bon de commande '.$order->reference)

@php
    $F = \App\Support\Format::class;
    $badge = ['draft' => 'bg-slate-100 text-slate-700', 'sent' => 'bg-sky-100 text-sky-800', 'partially_received' => 'bg-amber-100 text-amber-800', 'received' => 'bg-emerald-100 text-emerald-800', 'cancelled' => 'bg-slate-100 text-slate-500'];
@endphp

@section('actions')
    <button onclick="window.print()" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Imprimer</button>
    @if ($order->status === 'draft')
        <a href="{{ route('purchase-orders.edit', $order) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Modifier</a>
        <form method="POST" action="{{ route('purchase-orders.status', $order) }}">@csrf @method('PATCH')
            <button name="status" value="sent" class="rounded-lg px-3 py-2 text-sm font-medium text-sky-700 hover:bg-sky-50">Marquer envoyé</button>
        </form>
    @endif
    @if (in_array($order->status, ['draft', 'sent']))
        <form method="POST" action="{{ route('purchase-orders.status', $order) }}" onsubmit="return confirm('Annuler ce bon de commande ?')">@csrf @method('PATCH')
            <button name="status" value="cancelled" class="rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Annuler le bon</button>
        </form>
    @endif
    @if ($order->status === 'partially_received')
        <form method="POST" action="{{ route('purchase-orders.status', $order) }}" onsubmit="return confirm('Le reste ne sera pas livré : clôturer ce bon ?')">@csrf @method('PATCH')
            <button name="status" value="received" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Clôturer le reliquat</button>
        </form>
    @endif
    @if (in_array($order->status, ['draft', 'sent', 'partially_received']))
        <a href="{{ route('goods-receipts.create', ['commande' => $order->id]) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Réceptionner</a>
    @endif
@endsection

@section('content')
<div class="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-8 print:border-0 print:p-0">
    <div class="flex flex-wrap items-start justify-between gap-6">
        <div>
            <div class="text-xl font-bold">{{ $store['name'] }}</div>
            <div class="text-sm text-slate-500">{{ $store['address'] }}<br>{{ $store['phone'] ? 'Tél. '.$store['phone'] : '' }} {{ $store['nif'] ? '· NIF '.$store['nif'] : '' }}</div>
        </div>
        <div class="text-right">
            <div class="text-xs uppercase tracking-wide text-slate-500">Bon de commande</div>
            <div class="text-2xl font-bold">{{ $order->reference }}</div>
            <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-semibold print:hidden {{ $badge[$order->status] }}">{{ $statuses[$order->status] }}</span>
        </div>
    </div>

    <div class="mt-6 grid gap-6 text-sm sm:grid-cols-3">
        <div>
            <div class="text-xs uppercase text-slate-500">Fournisseur</div>
            <div class="font-semibold">{{ $order->supplier->name }}</div>
            <div class="text-slate-600">{{ $order->supplier->contact_person }}<br>{{ $order->supplier->phone }}</div>
        </div>
        <div>
            <div class="text-xs uppercase text-slate-500">Date de commande</div>
            <div class="font-semibold">{{ $order->order_date->format('d/m/Y') }}</div>
        </div>
        <div>
            <div class="text-xs uppercase text-slate-500">Livraison souhaitée</div>
            <div class="font-semibold">{{ $order->expected_date?->format('d/m/Y') ?? '—' }}</div>
        </div>
    </div>

    <table class="mt-6 w-full text-sm tabular-nums">
        <thead class="border-y border-slate-200 text-left text-xs uppercase text-slate-500">
            <tr><th class="py-2">Produit</th><th class="py-2 text-right">Quantité</th><th class="py-2 text-right print:hidden">Reçu</th><th class="py-2 text-right">Prix unit.</th><th class="py-2 text-right">Total</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($order->items as $i)
                @php $unit = $i->productUnit?->unit?->name ?? $i->product->baseUnit?->name; @endphp
                <tr>
                    <td class="py-2">{{ $i->product->name }} <span class="text-xs text-slate-400">{{ $i->product->sku }}</span></td>
                    <td class="py-2 text-right">{{ $F::qty($i->quantity) }} {{ $unit }}</td>
                    <td class="py-2 text-right print:hidden {{ (float) $i->received_quantity >= (float) $i->quantity ? 'text-emerald-700' : ((float) $i->received_quantity > 0 ? 'text-amber-700' : 'text-slate-400') }}">{{ $F::qty($i->received_quantity) }}</td>
                    <td class="py-2 text-right">{{ $F::money($i->unit_cost) }}</td>
                    <td class="py-2 text-right font-medium">{{ $F::money($i->total) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-slate-300"><td colspan="3" class="py-3 text-right font-semibold print:hidden"></td><td class="py-3 text-right font-semibold">TOTAL</td><td class="py-3 text-right text-lg font-bold">{{ $F::money($order->total) }}</td></tr>
        </tfoot>
    </table>

    @if ($order->notes)<p class="mt-4 text-sm"><strong>Remarques :</strong> {{ $order->notes }}</p>@endif

    <div class="mt-12 hidden grid-cols-2 gap-12 text-sm print:grid">
        <div class="border-t border-slate-400 pt-2">Émis par : {{ $order->user->name }}</div>
        <div class="border-t border-slate-400 pt-2">Reçu / accepté par le fournisseur</div>
    </div>

    @if ($order->receipts->isNotEmpty())
        <div class="mt-8 print:hidden">
            <h3 class="text-sm font-semibold">Réceptions liées</h3>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($order->receipts as $r)
                    <li><a href="{{ route('goods-receipts.show', $r) }}" class="font-medium text-emerald-700 hover:underline">{{ $r->reference }}</a> · {{ $r->received_date->format('d/m/Y') }} · {{ $F::money($r->total) }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

@if ($print)<script>window.addEventListener('load', () => window.print());</script>@endif
@endsection
