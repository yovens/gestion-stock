@extends('layouts.admin')

@section('title', $order ? 'Réception du bon '.$order->reference : 'Nouvelle réception de marchandise')

@section('content')
<form method="POST" action="{{ route('goods-receipts.store') }}" enctype="multipart/form-data"
      x-data="purchaseLines(@js(['mode' => 'receipt', 'productsUrl' => route('purchases.products'), 'lines' => $lines]))"
      @submit="if (missingExpiry) { $event.preventDefault(); alert('Indiquez la date d\'expiration des produits surlignés.'); }"
      class="mx-auto max-w-6xl space-y-6">
    @csrf
    @if ($order)<input type="hidden" name="purchase_order_id" value="{{ $order->id }}">@endif

    @if ($errors->any())
        <div class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200">{{ $errors->first() }}</div>
    @endif

    @if ($order)
        <div class="rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-800 ring-1 ring-sky-200">
            Les quantités restant à livrer sur le bon {{ $order->reference }} sont pré-remplies. Corrigez-les selon ce qui est réellement arrivé.
        </div>
    @endif

    <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium">Fournisseur *</label>
            @if ($order)
                <input type="hidden" name="supplier_id" value="{{ $order->supplier_id }}">
                <div class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-sm">{{ $order->supplier->name }}</div>
            @else
                <select name="supplier_id" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Choisir…</option>
                    @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id', $supplierId) == $s->id)>{{ $s->name }}</option>@endforeach
                </select>
            @endif
        </div>
        <div>
            <label class="block text-sm font-medium">Date de réception *</label>
            <input type="date" name="received_date" required max="{{ today()->toDateString() }}" value="{{ old('received_date', today()->toDateString()) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium">N° facture fournisseur</label>
            <input name="supplier_invoice_number" value="{{ old('supplier_invoice_number') }}" maxlength="50" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium">Photo / PDF de la facture</label>
            <input type="file" name="attachment" accept="image/*,application/pdf" class="mt-1 block w-full text-xs file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium">Remarques</label>
            <input name="notes" value="{{ old('notes') }}" maxlength="1000" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Ex. : 2 sacs déchirés refusés">
        </div>
    </section>

    @include('purchases._lines', ['mode' => 'receipt'])

    <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-4">
        <div>
            <label class="block text-sm font-medium">Frais de transport</label>
            <input name="transport_cost" x-model="transport" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border-slate-300 text-right text-sm">
            <p class="mt-1 text-xs text-slate-500">Répartis sur le coût des produits au prorata de leur valeur.</p>
        </div>
        @if ($canPay)
            <div>
                <label class="block text-sm font-medium">Payé maintenant</label>
                <input name="paid_amount" x-model="paid" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border-slate-300 text-right text-sm">
                <button type="button" @click="paid = total" class="mt-1 text-xs font-semibold text-emerald-700 hover:underline">Tout payer</button>
            </div>
            <div>
                <label class="block text-sm font-medium">Mode de paiement</label>
                <select name="payment_method" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    @foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
                <input name="payment_reference" placeholder="N° transaction / chèque" maxlength="100" class="mt-2 w-full rounded-lg border-slate-300 text-sm">
            </div>
        @endif
        <div class="rounded-xl bg-slate-50 p-4 text-right {{ $canPay ? '' : 'md:col-start-4' }}">
            <div class="text-xs uppercase text-slate-500">Total de la réception</div>
            <div class="text-2xl font-bold tabular-nums" x-text="money(total)"></div>
            @if ($canPay)
                <div class="mt-1 text-sm" :class="due > 0 ? 'text-rose-600' : 'text-emerald-700'" x-text="due > 0 ? 'Reste à payer : ' + money(due) : 'Entièrement payé'"></div>
            @endif
        </div>
    </section>

    <div class="flex justify-end gap-3">
        <a href="{{ $order ? route('purchase-orders.show', $order) : route('goods-receipts.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Annuler</a>
        <button :disabled="!lines.length" onclick="return confirm('Valider la réception ? Le stock sera mis à jour immédiatement.')"
                class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-40">Valider la réception et entrer en stock</button>
    </div>
</form>
@endsection
