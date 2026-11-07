@extends('layouts.admin')

@section('title', $supplier->name)

@php
    $F = \App\Support\Format::class;
    $badge = ['unpaid' => 'bg-rose-100 text-rose-700', 'partial' => 'bg-amber-100 text-amber-800', 'paid' => 'bg-emerald-100 text-emerald-800'];
    $labels = ['unpaid' => 'Non payé', 'partial' => 'En partie', 'paid' => 'Payé'];
@endphp

@section('actions')
    <a href="{{ route('suppliers.edit', $supplier) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Modifier</a>
    @can('purchases.manage')
        <a href="{{ route('purchase-orders.create', ['fournisseur' => $supplier->id]) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Bon de commande</a>
        <a href="{{ route('goods-receipts.create', ['fournisseur' => $supplier->id]) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Réception</a>
    @endcan
@endsection

@section('content')
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">On lui doit</div>
            <div class="mt-1 text-2xl font-bold tabular-nums {{ (float) $supplier->balance > 0 ? 'text-rose-600' : '' }}">{{ $F::money($supplier->balance) }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ $unpaid->count() }} réception(s) non soldée(s)</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Achats {{ now()->year }}</div>
            <div class="mt-1 text-2xl font-bold tabular-nums">{{ $F::money($stats['year']) }}</div>
            <div class="mt-1 text-xs text-slate-500">depuis le début : {{ $F::money($stats['total']) }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Total payé</div>
            <div class="mt-1 text-2xl font-bold tabular-nums">{{ $F::money($stats['paid']) }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm">
            <div class="font-semibold">{{ $supplier->contact_person ?: 'Contact' }}</div>
            <div class="mt-1 text-slate-600">{{ $supplier->phone }} {{ $supplier->phone_2 ? '· '.$supplier->phone_2 : '' }}</div>
            <div class="text-slate-600">{{ $supplier->email }}</div>
            <div class="text-slate-500">{{ $supplier->address }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ $supplier->payment_terms_days ? 'Crédit '.$supplier->payment_terms_days.' jours' : 'Paiement comptant' }}{{ $supplier->nif ? ' · NIF '.$supplier->nif : '' }}</div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @if ($orders->isNotEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white">
                    <h2 class="p-5 pb-3 font-semibold">Commandes en cours</h2>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($orders as $o)
                            <li class="flex items-center justify-between px-5 py-2.5">
                                <a href="{{ route('purchase-orders.show', $o) }}" class="font-medium hover:underline">{{ $o->reference }}</a>
                                <span class="text-slate-500">{{ $o->order_date->format('d/m/Y') }}{{ $o->expected_date ? ' → '.$o->expected_date->format('d/m/Y') : '' }}</span>
                                <span class="tabular-nums">{{ $F::money($o->total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white">
                <h2 class="p-5 pb-3 font-semibold">Réceptions</h2>
                <table class="w-full text-sm tabular-nums">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-2">N°</th><th class="py-2">Date</th><th class="py-2">Échéance</th><th class="py-2 text-right">Total</th><th class="py-2 text-right">Reste</th><th class="px-5 py-2"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($receipts as $r)
                            @php $deadline = $r->received_date->copy()->addDays($supplier->payment_terms_days); $late = $r->dueAmount() > 0 && $deadline->lt(today()); @endphp
                            <tr>
                                <td class="px-5 py-2.5"><a href="{{ route('goods-receipts.show', $r) }}" class="font-medium hover:underline">{{ $r->reference }}</a></td>
                                <td class="py-2.5">{{ $r->received_date->format('d/m/Y') }}</td>
                                <td class="py-2.5 {{ $late ? 'font-semibold text-rose-600' : 'text-slate-500' }}">{{ $r->dueAmount() > 0 ? $deadline->format('d/m/Y').($late ? ' · en retard' : '') : '—' }}</td>
                                <td class="py-2.5 text-right">{{ $F::money($r->total) }}</td>
                                <td class="py-2.5 text-right {{ $r->dueAmount() > 0 ? 'font-semibold text-rose-600' : 'text-slate-400' }}">{{ $F::money($r->dueAmount()) }}</td>
                                <td class="px-5 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[$r->payment_status] ?? '' }}">{{ $labels[$r->payment_status] ?? '' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">Aucune réception.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($receipts->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $receipts->links() }}</div>@endif
            </div>
        </div>

        <div class="space-y-6">
            @can('supplier_payments.manage')
                @if ((float) $supplier->balance > 0)
                    <form method="POST" action="{{ route('suppliers.pay', $supplier) }}" class="rounded-2xl border border-slate-200 bg-white p-5">
                        @csrf
                        <h2 class="font-semibold">Enregistrer un paiement</h2>
                        <label class="mt-3 block text-sm font-medium">Montant</label>
                        <input name="amount" type="number" step="0.01" min="0.01" max="{{ (float) $supplier->balance }}" required class="mt-1 w-full rounded-lg border-slate-300 text-right text-lg font-semibold">
                        <label class="mt-3 block text-sm font-medium">Affecter à</label>
                        <select name="goods_receipt_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Automatique : les plus anciennes d'abord</option>
                            @foreach ($unpaid as $r)<option value="{{ $r->id }}">{{ $r->reference }} · reste {{ $F::money($r->dueAmount()) }}</option>@endforeach
                        </select>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <select name="method" class="rounded-lg border-slate-300 text-sm">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                            <input type="date" name="payment_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required class="rounded-lg border-slate-300 text-sm">
                        </div>
                        <input name="reference" placeholder="N° transaction / chèque" class="mt-2 w-full rounded-lg border-slate-300 text-sm">
                        @if ($errors->any())<p class="mt-2 text-sm text-rose-600">{{ $errors->first() }}</p>@endif
                        <button class="mt-4 w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Payer</button>
                    </form>
                @endif
            @endcan

            <div class="rounded-2xl border border-slate-200 bg-white">
                <h2 class="p-5 pb-3 font-semibold">Derniers paiements</h2>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($payments as $p)
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                            <span>{{ $p->payment_date->format('d/m/Y') }} · {{ $methods[$p->method] ?? $p->method }}
                                <span class="block text-xs text-slate-500">{{ $p->goodsReceipt?->reference ?? 'Dette initiale' }}{{ $p->reference ? ' · '.$p->reference : '' }} · {{ $p->user?->name }}</span></span>
                            <span class="font-semibold tabular-nums">{{ $F::money($p->amount) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">Aucun paiement.</li>
                    @endforelse
                </ul>
            </div>
            @if ($supplier->notes)<div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm"><h2 class="font-semibold">Notes</h2><p class="mt-2 whitespace-pre-line text-slate-600">{{ $supplier->notes }}</p></div>@endif
        </div>
    </div>
</div>
@endsection
