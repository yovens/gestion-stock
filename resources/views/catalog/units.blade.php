@extends('layouts.admin')

@section('title', 'Unités de mesure')

@section('content')
<div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-3" x-data="{ editing: null }">
    <form method="POST" action="{{ route('units.store') }}" class="h-fit rounded-2xl border border-slate-200 bg-white p-5">
        @csrf
        <h2 class="font-semibold">Nouvelle unité</h2>
        <label class="mt-4 block text-sm font-medium">Nom</label>
        <input name="name" required maxlength="50" value="{{ old('name') }}" placeholder="Ex. : Marmite" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
        <label class="mt-3 block text-sm font-medium">Abréviation</label>
        <input name="abbreviation" required maxlength="15" value="{{ old('abbreviation') }}" placeholder="mmt" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
        <label class="mt-3 flex items-start gap-2 text-sm">
            <input type="checkbox" name="allows_decimal" value="1" class="mt-0.5 rounded border-slate-300">
            <span>Accepte les quantités décimales <span class="block text-xs text-slate-500">Oui pour livre, marmite, gallon (2,5 lb). Non pour bouteille, sac, caisse.</span></span>
        </label>
        @if ($errors->any())<p class="mt-3 text-sm text-rose-600">{{ $errors->first() }}</p>@endif
        <button class="mt-4 w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Ajouter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white lg:col-span-2">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-5 py-2">Unité</th><th class="py-2">Abrév.</th><th class="py-2">Décimales</th><th class="py-2">Utilisée par</th><th class="px-5 py-2"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($units as $u)
                    <tr x-show="editing !== {{ $u->id }}">
                        <td class="px-5 py-2.5 font-medium">{{ $u->name }}</td>
                        <td class="py-2.5 font-mono text-xs">{{ $u->abbreviation }}</td>
                        <td class="py-2.5">{{ $u->allows_decimal ? 'Oui' : 'Non' }}</td>
                        <td class="py-2.5 text-xs text-slate-500">{{ $u->base_count }} produit(s) · {{ $u->pack_count }} conditionnement(s)</td>
                        <td class="px-5 py-2.5 text-right">
                            <button type="button" @click="editing = {{ $u->id }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">Modifier</button>
                            @if (! $u->base_count && ! $u->pack_count)
                                <form method="POST" action="{{ route('units.destroy', $u) }}" class="inline" onsubmit="return confirm('Supprimer cette unité ?')">
                                    @csrf @method('DELETE')
                                    <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Supprimer</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    <tr x-show="editing === {{ $u->id }}" x-cloak>
                        <td colspan="5" class="px-5 py-2.5">
                            <form method="POST" action="{{ route('units.update', $u) }}" class="flex flex-wrap items-center gap-2">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $u->name }}" required class="min-w-32 flex-1 rounded-lg border-slate-300 text-sm">
                                <input name="abbreviation" value="{{ $u->abbreviation }}" required class="w-24 rounded-lg border-slate-300 text-sm">
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="allows_decimal" value="1" @checked($u->allows_decimal) class="rounded border-slate-300"> Décimales</label>
                                <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">OK</button>
                                <button type="button" @click="editing = null" class="text-xs text-slate-500">Annuler</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Aucune unité. Lancez <code>php artisan db:seed --class=CatalogSeeder</code>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
