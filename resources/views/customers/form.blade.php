@extends('layouts.admin')

@section('title', $customer->exists ? 'Modifier · '.$customer->name : 'Nouveau client')

@php $in = 'mt-1 w-full rounded-lg border-slate-300 text-sm'; $canCredit = auth()->user()->can('settings.manage'); @endphp

@section('content')
<form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}"
      class="mx-auto max-w-3xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6">
    @csrf
    @if ($customer->exists) @method('PUT') @endif
    @if ($errors->any())<div class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

    <div class="grid gap-4 md:grid-cols-2">
        <div><label class="text-sm font-medium">Nom *</label><input name="name" required value="{{ old('name', $customer->name) }}" class="{{ $in }}"></div>
        <div>
            <label class="text-sm font-medium">Type *</label>
            <select name="type" class="{{ $in }}">
                <option value="retail" @selected(old('type', $customer->type) === 'retail')>Détail</option>
                <option value="wholesale" @selected(old('type', $customer->type) === 'wholesale')>Gros (revendeur) : prix gros automatiques</option>
            </select>
        </div>
        <div><label class="text-sm font-medium">Nom de la boutique</label><input name="business_name" value="{{ old('business_name', $customer->business_name) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">Téléphone</label><input name="phone" value="{{ old('phone', $customer->phone) }}" class="{{ $in }}" placeholder="+509 …"></div>
        <div><label class="text-sm font-medium">E-mail</label><input type="email" name="email" value="{{ old('email', $customer->email) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">NIF</label><input name="nif" value="{{ old('nif', $customer->nif) }}" class="{{ $in }}"></div>
        <div class="md:col-span-2"><label class="text-sm font-medium">Adresse</label><input name="address" value="{{ old('address', $customer->address) }}" class="{{ $in }}"></div>

        <div class="rounded-xl bg-slate-50 p-4 md:col-span-2">
            <div class="text-sm font-semibold">Crédit</div>
            @if ($canCredit)
                <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="credit_allowed" value="1" @checked(old('credit_allowed', $customer->credit_allowed)) class="rounded border-slate-300"> Autoriser les achats à crédit</label>
                <label class="mt-3 block text-sm font-medium">Limite de crédit (HTG)</label>
                <input type="number" name="credit_limit" step="0.01" min="0" value="{{ old('credit_limit', (float) $customer->credit_limit) }}" class="{{ $in }} max-w-xs text-right">
            @else
                <input type="hidden" name="credit_limit" value="{{ (float) $customer->credit_limit }}">
                <p class="mt-1 text-sm text-slate-500">{{ $customer->credit_allowed ? 'Crédit autorisé jusqu\'à '.\App\Support\Format::money($customer->credit_limit) : 'Pas de crédit.' }} Seul l'administrateur peut modifier le crédit.</p>
            @endif
            @unless ($customer->exists)
                <label class="mt-3 block text-sm font-medium">Dette existante (mise en service)</label>
                <input type="number" name="opening_balance" step="0.01" min="0" value="{{ old('opening_balance', 0) }}" class="{{ $in }} max-w-xs text-right">
            @endunless
        </div>

        <div class="md:col-span-2"><label class="text-sm font-medium">Notes</label><textarea name="notes" rows="2" class="{{ $in }}">{{ old('notes', $customer->notes) }}</textarea></div>
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active ?? true)) class="rounded border-slate-300"> Actif</label>
    </div>

    <div class="flex justify-between border-t border-slate-200 pt-4">
        <div>@if ($customer->exists)<button form="del" class="text-sm font-medium text-rose-600 hover:underline">Supprimer</button>@endif</div>
        <div class="flex gap-3">
            <a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Annuler</a>
            <button class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Enregistrer</button>
        </div>
    </div>
</form>
@if ($customer->exists)
    <form id="del" method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Supprimer ce client ?')">@csrf @method('DELETE')</form>
@endif
@endsection
