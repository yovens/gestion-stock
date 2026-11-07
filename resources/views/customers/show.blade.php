@extends('layouts.admin')

@section('title', $customer->name)

@php
    $F = \App\Support\Format::class;
    $days = $oldest ? (int) $oldest->diffInDays(now()) : null;
    $used = (float) $customer->credit_limit > 0 ? min(100, (float) $customer->balance / (float) $customer->credit_limit * 100) : 0;
@endphp

@section('actions')
    <a href="{{ route('customers.statement', $customer) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Relevé de compte</a>
    @can('customers.manage')<a href="{{ route('customers.edit', $customer) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Modifier</a>@endcan
@endsection

@section('content')
@if (session('print_payment'))
    <script>window.open(@js(route('customers.payment-receipt', session('print_payment')).'?print=1'), 'recu', 'width=420,height=600');</script>
@endif
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Doit actuellement</div>
            <div class="mt-1 text-2xl font-bold tabular-nums {{ (float) $customer->balance > 0 ? 'text-rose-600' : '' }}">{{ $F::money($customer->balance) }}</div>
            @if ($days !== null)
                <div class="mt-1 text-xs {{ $days > 60 ? 'font-semibold text-rose-600' : ($days > 30 ? 'text-amber-700' : 'text-slate-500') }}">dette la plus ancienne : {{ $days }} jour(s)</div>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Crédit</div>
            @if ($customer->credit_allowed)
                <div class="mt-1 text-2xl font-bold tabular-nums">{{ $F::money($customer->availableCredit()) }}</div>
                <div class="mt-2 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full {{ $used >= 90 ? 'bg-rose-500' : 'bg-slate-500' }}" style="width: {{ round($used) }}%"></div></div>
                <div class="mt-1 text-xs text-slate-500">disponible sur {{ $F::money($customer->credit_limit) }}</div>
            @else
                <div class="mt-1 text-lg font-semibold text-slate-500">Non autorisé</div>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Achats</div>
            <div class="mt-1 text-2xl font-bold tabular-nums">{{ $F::money($stats['total']) }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ $stats['count'] }} achat(s) · 90 derniers jours : {{ $F::money($stats['last90']) }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm">
            <div class="flex items-center gap-2 font-semibold">{{ $customer->business_name ?: $customer->name }}
                @if ($customer->type === 'wholesale')<span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-800">Gros</span>@endif</div>
            <div class="mt-1 text-slate-600">{{ $customer->phone }}</div>
            <div class="text-slate-500">{{ $customer->address }}</div>
            <div class="mt-1 text-xs text-slate-500">Dernier achat : {{ $stats['last'] ? \Illuminate\Support\Carbon::parse($stats['last'])->format('d/m/Y') : '—' }}</div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white xl:col-span-2">
            <h2 class="p-5 pb-3 font-semibold">Achats</h2>
            <table class="w-full text-sm tabular-nums">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-2">Vente</th><th class="py-2">Date</th><th class="py-2">Caissier</th><th class="py-2 text-right">Total</th><th class="px-5 py-2 text-right">À crédit</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sales as $s)
                        <tr class="{{ $s->status === 'cancelled' ? 'text-slate-400 line-through' : '' }}">
                            <td class="px-5 py-2.5">
                                @if (Route::has('sales.show') && auth()->user()->can('sales.view_all'))
                                    <a href="{{ route('sales.show', $s) }}" class="font-medium hover:underline">{{ $s->reference }}</a>
                                @else
                                    {{ $s->reference }}
                                @endif
                            </td>
                            <td class="py-2.5">{{ $s->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2.5">{{ $s->cashier?->name }}</td>
                            <td class="py-2.5 text-right">{{ $F::money($s->total) }}</td>
                            <td class="px-5 py-2.5 text-right {{ (float) $s->due_amount > 0 ? 'font-semibold text-rose-600' : 'text-slate-400' }}">{{ $F::money($s->due_amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">Aucun achat.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($sales->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $sales->links() }}</div>@endif
        </div>

        <div class="space-y-6">
            @can('customer_payments.manage')
                @if ((float) $customer->balance > 0)
                    <form method="POST" action="{{ route('customers.pay', $customer) }}" class="rounded-2xl border border-slate-200 bg-white p-5" x-data="{ currency: 'HTG' }">
                        @csrf
                        <h2 class="font-semibold">Encaisser un paiement</h2>
                        <div class="mt-3 flex gap-2">
                            <input name="amount" type="number" step="0.01" min="0.01" required placeholder="Montant" class="w-full rounded-lg border-slate-300 text-right text-lg font-semibold">
                            <select name="currency" x-model="currency" class="w-24 rounded-lg border-slate-300 text-sm"><option>HTG</option><option>USD</option></select>
                        </div>
                        <button type="button" x-show="currency === 'HTG'" @click="$el.closest('form').amount.value = {{ (float) $customer->balance }}" class="mt-1 text-xs font-semibold text-emerald-700 hover:underline">Tout solder ({{ $F::money($customer->balance) }})</button>
                        <select name="method" class="mt-3 w-full rounded-lg border-slate-300 text-sm">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                        <input name="reference" placeholder="N° transaction (MonCash…)" class="mt-2 w-full rounded-lg border-slate-300 text-sm">
                        @if ($errors->any())<p class="mt-2 text-sm text-rose-600">{{ $errors->first() }}</p>@endif
                        <button class="mt-4 w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Encaisser et imprimer le reçu</button>
                        <p class="mt-2 text-xs text-slate-500">Les espèces sont comptées dans votre caisse ouverte.</p>
                    </form>
                @endif
            @endcan

            <div class="rounded-2xl border border-slate-200 bg-white">
                <h2 class="p-5 pb-3 font-semibold">Paiements reçus</h2>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($payments as $p)
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                            <span>{{ $p->created_at->format('d/m/Y') }} · {{ $methods[$p->method] ?? $p->method }}
                                <span class="block text-xs text-slate-500">{{ $p->reference }} · {{ $p->user?->name }}</span></span>
                            <span class="text-right">
                                <span class="block font-semibold tabular-nums">{{ $F::money($p->amount_htg) }}</span>
                                <a href="{{ route('customers.payment-receipt', $p) }}" target="_blank" class="text-xs text-emerald-700 hover:underline">reçu</a>
                            </span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">Aucun paiement.</li>
                    @endforelse
                </ul>
            </div>
            @if ($customer->notes)<div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm"><h2 class="font-semibold">Notes</h2><p class="mt-2 whitespace-pre-line text-slate-600">{{ $customer->notes }}</p></div>@endif
        </div>
    </div>
</div>
@endsection
