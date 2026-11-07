@extends('layouts.admin')

@section('title', $order->exists ? 'Modifier '.$order->reference : 'Nouveau bon de commande')

@section('content')
<form method="POST" action="{{ $order->exists ? route('purchase-orders.update', $order) : route('purchase-orders.store') }}"
      x-data="purchaseLines(@js(['mode' => 'order', 'productsUrl' => route('purchases.products'), 'lines' => $lines]))"
      class="mx-auto max-w-6xl space-y-6">
    @csrf
    @if ($order->exists) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200">{{ $errors->first() }}</div>
    @endif

    <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium">Fournisseur *</label>
            <select name="supplier_id" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                <option value="">Choisir…</option>
                @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id', $order->supplier_id) == $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">Date de commande *</label>
            <input type="date" name="order_date" required value="{{ old('order_date', $order->order_date?->toDateString()) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium">Livraison prévue</label>
            <input type="date" name="expected_date" value="{{ old('expected_date', $order->expected_date?->toDateString()) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
        </div>
        <div class="md:col-span-4">
            <label class="block text-sm font-medium">Remarques pour le fournisseur</label>
            <input name="notes" value="{{ old('notes', $order->notes) }}" maxlength="1000" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
        </div>
        @unless ($order->exists || count($lines))
            <p class="text-sm text-slate-500 md:col-span-4">
                Astuce : <a href="{{ route('purchase-orders.create', ['stock_bas' => 1]) }}" class="font-semibold text-emerald-700 hover:underline">pré-remplir avec tous les produits en stock bas</a>.
            </p>
        @endunless
    </section>

    @include('purchases._lines', ['mode' => 'order'])

    <div class="flex justify-end gap-3">
        <a href="{{ $order->exists ? route('purchase-orders.show', $order) : route('purchase-orders.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Annuler</a>
        <button :disabled="!lines.length" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-40">Enregistrer le bon</button>
    </div>
</form>
@endsection
