@extends('layouts.admin')

@section('title', 'Réception '.$receipt->reference)

@php
    $F = \App\Support\Format::class;
    $badge = ['unpaid' => 'bg-rose-100 text-rose-700', 'partial' => 'bg-amber-100 text-amber-800', 'paid' => 'bg-emerald-100 text-emerald-800'];
    $due = $receipt->dueAmount();
@endphp

@section('actions')
    <button onclick="window.print()" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Imprimer</button>
    <a href="{{ route('suppliers.show', $receipt->supplier) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Fiche fournisseur</a>
@endsection

@section('content')
<div class="mx-auto grid max-w-6xl gap-6 xl:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 xl:col-span-2 print:col-span-3 print:border-0 print:p-0">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-xs uppercase tracking-wide text-slate-500">Bon de réception</div>
                <div class="text-2xl font-bold">{{ $receipt->reference }}</div>
                <div class="mt-1 text-sm text-slate-600">
                    {{ $receipt->received_date->format('d/m/Y') }} · reçu par {{ $receipt->user->name }}
                    @if ($receipt->purchaseOrder) · bon <a href="{{ route('purchase-orders.show', $receipt->purchaseOrder) }}" class="font-medium text-emerald-700 hover:underline">{{ $receipt->purchaseOrder->reference }}</a>@endif
                </div>
            </div>
            <div class="text-right text-sm">
                <div class="font-semibold">{{ $receipt->supplier->name }}</div>
                @if ($receipt->supplier_invoice_number)<div class="text-slate-500">Facture n° {{ $receipt->supplier_invoice_number }}</div>@endif
                <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[$receipt->payment_status] ?? '' }}">{{ $statuses[$receipt->payment_status] ?? '' }}</span>
            </div>
        </div>

        <table class="mt-6 w-full text-sm tabular-nums">
            <thead class="border-y border-slate-200 text-left text-xs uppercase text-slate-500">
                <tr><th class="py-2">Produit</th><th class="py-2">Lot / expiration</th><th class="py-2 text-right">Quantité</th><th class="py-2 text-right">Prix unit.</th><th class="py-2 text-right">Total</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($receipt->items as $i)
                    @php $unit = $i->productUnit?->unit?->name ?? $i->product->baseUnit?->name; @endphp
                    <tr>
                        <td class="py-2"><a href="{{ route('products.show', $i->product) }}" class="font-medium hover:underline">{{ $i->product->name }}</a></td>
                        <td class="py-2 text-xs text-slate-600">{{ $i->batch_number ?: '—' }}{{ $i->expiry_date ? ' · exp. '.$i->expiry_date->format('d/m/Y') : '' }}</td>
                        <td class="py-2 text-right">{{ $F::qty($i->quantity) }} {{ $unit }}
                            @if ((float) $i->conversion_factor != 1)<span class="block text-xs text-slate-400">= {{ $F::qty($i->base_quantity) }} {{ $i->product->baseUnit?->abbreviation }}</span>@endif
                        </td>
                        <td class="py-2 text-right">{{ $F::money($i->unit_cost) }}</td>
                        <td class="py-2 text-right font-medium">{{ $F::money($i->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="text-right">
                <tr class="border-t border-slate-200"><td colspan="4" class="pt-3">Sous-total</td><td class="pt-3">{{ $F::money($receipt->subtotal) }}</td></tr>
                @if ((float) $receipt->transport_cost > 0)<tr><td colspan="4">Transport</td><td>{{ $F::money($receipt->transport_cost) }}</td></tr>@endif
                <tr class="text-base font-bold"><td colspan="4" class="pt-1">TOTAL</td><td class="pt-1">{{ $F::money($receipt->total) }}</td></tr>
                <tr class="text-slate-600"><td colspan="4">Payé</td><td>{{ $F::money($receipt->paid_amount) }}</td></tr>
                <tr class="font-semibold {{ $due > 0 ? 'text-rose-600' : 'text-emerald-700' }}"><td colspan="4">Reste à payer</td><td>{{ $F::money($due) }}</td></tr>
            </tfoot>
        </table>

        @if ($receipt->notes)<p class="mt-4 text-sm"><strong>Remarques :</strong> {{ $receipt->notes }}</p>@endif
        @if ($receipt->attachment)
            <a href="{{ asset('storage/'.$receipt->attachment) }}" target="_blank" class="mt-4 inline-block text-sm font-medium text-emerald-700 hover:underline print:hidden">📎 Voir la facture du fournisseur</a>
        @endif

        <div class="mt-12 hidden grid-cols-2 gap-12 text-sm print:grid">
            <div class="border-t border-slate-400 pt-2">Livré par (fournisseur)</div>
            <div class="border-t border-slate-400 pt-2">Reçu et vérifié par : {{ $receipt->user->name }}</div>
        </div>
    </div>

    <div class="space-y-6 print:hidden">
        @can('supplier_payments.manage')
            @if ($due > 0)
                <form method="POST" action="{{ route('suppliers.pay', $receipt->supplier) }}" class="rounded-2xl border border-slate-200 bg-white p-5">
                    @csrf
                    <input type="hidden" name="goods_receipt_id" value="{{ $receipt->id }}">
                    <h2 class="font-semibold">Payer cette réception</h2>
                    <label class="mt-3 block text-sm font-medium">Montant</label>
                    <input name="amount" type="number" step="0.01" min="0.01" max="{{ $due }}" value="{{ $due }}" required class="mt-1 w-full rounded-lg border-slate-300 text-right text-lg font-semibold">
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <select name="method" class="rounded-lg border-slate-300 text-sm">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                        <input type="date" name="payment_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <input name="reference" placeholder="N° transaction / chèque" class="mt-2 w-full rounded-lg border-slate-300 text-sm">
                    <button class="mt-4 w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Enregistrer le paiement</button>
                </form>
            @endif
        @endcan

        <div class="rounded-2xl border border-slate-200 bg-white">
            <h2 class="p-5 pb-3 font-semibold">Paiements</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($receipt->payments as $p)
                    <li class="flex items-center justify-between px-5 py-2.5">
                        <span>{{ $p->payment_date->format('d/m/Y') }} · {{ $methods[$p->method] ?? $p->method }}<span class="block text-xs text-slate-500">{{ $p->user?->name }}{{ $p->reference ? ' · '.$p->reference : '' }}</span></span>
                        <span class="font-semibold tabular-nums">{{ $F::money($p->amount) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-slate-500">Aucun paiement.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
