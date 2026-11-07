@extends('layouts.admin')

@section('title', $supplier->exists ? 'Modifier · '.$supplier->name : 'Nouveau fournisseur')

@php $in = 'mt-1 w-full rounded-lg border-slate-300 text-sm'; @endphp

@section('content')
<form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}"
      class="mx-auto max-w-3xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6">
    @csrf
    @if ($supplier->exists) @method('PUT') @endif

    @if ($errors->any())<div class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

    <div class="grid gap-4 md:grid-cols-2">
        <div class="md:col-span-2"><label class="text-sm font-medium">Nom / entreprise *</label><input name="name" required value="{{ old('name', $supplier->name) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">Personne de contact</label><input name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">NIF</label><input name="nif" value="{{ old('nif', $supplier->nif) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">Téléphone</label><input name="phone" value="{{ old('phone', $supplier->phone) }}" class="{{ $in }}" placeholder="+509 …"></div>
        <div><label class="text-sm font-medium">Téléphone 2 / WhatsApp</label><input name="phone_2" value="{{ old('phone_2', $supplier->phone_2) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">E-mail</label><input type="email" name="email" value="{{ old('email', $supplier->email) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">Délai de paiement accordé (jours)</label><input type="number" name="payment_terms_days" min="0" max="365" required value="{{ old('payment_terms_days', $supplier->payment_terms_days) }}" class="{{ $in }}"><span class="text-xs text-slate-500">0 = paiement comptant</span></div>
        <div class="md:col-span-2"><label class="text-sm font-medium">Adresse</label><input name="address" value="{{ old('address', $supplier->address) }}" class="{{ $in }}"></div>
        <div class="md:col-span-2"><label class="text-sm font-medium">Notes</label><textarea name="notes" rows="2" class="{{ $in }}">{{ old('notes', $supplier->notes) }}</textarea></div>
        @unless ($supplier->exists)
            <div class="rounded-xl bg-amber-50 p-4 md:col-span-2">
                <label class="text-sm font-medium">Dette existante envers ce fournisseur (HTG)</label>
                <input type="number" name="opening_balance" step="0.01" min="0" value="{{ old('opening_balance', 0) }}" class="{{ $in }} max-w-xs text-right">
                <p class="mt-1 text-xs text-amber-900/70">À la mise en service seulement : ce que le dépôt lui doit déjà aujourd'hui.</p>
            </div>
        @endunless
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active ?? true)) class="rounded border-slate-300"> Actif</label>
    </div>

    <div class="flex justify-between gap-3 border-t border-slate-200 pt-4">
        <div>
            @if ($supplier->exists)
                <button form="delete-supplier" class="text-sm font-medium text-rose-600 hover:underline">Supprimer</button>
            @endif
        </div>
        <div class="flex gap-3">
            <a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Annuler</a>
            <button class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Enregistrer</button>
        </div>
    </div>
</form>
@if ($supplier->exists)
    <form id="delete-supplier" method="POST" action="{{ route('suppliers.destroy', $supplier) }}" onsubmit="return confirm('Supprimer ce fournisseur ?')">@csrf @method('DELETE')</form>
@endif
@endsection
