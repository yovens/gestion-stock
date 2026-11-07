@extends('layouts.admin')

@section('title', 'Inventaires')

@php
    $badge = ['in_progress' => 'bg-sky-100 text-sky-800', 'completed' => 'bg-emerald-100 text-emerald-800', 'cancelled' => 'bg-slate-100 text-slate-500'];
    $labels = ['in_progress' => 'En cours', 'completed' => 'Validé', 'cancelled' => 'Annulé'];
@endphp

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <form method="POST" action="{{ route('inventories.store') }}" class="h-fit rounded-2xl border border-slate-200 bg-white p-5">
        @csrf
        <h2 class="font-semibold">Commencer un inventaire</h2>
        <p class="mt-1 text-sm text-slate-500">Le système prépare la liste des produits à compter. Vous pouvez compter une seule catégorie à la fois.</p>
        <label class="mt-4 block text-sm font-medium">Catégorie</label>
        <select name="category_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
            <option value="">Tout le dépôt</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
        </select>
        <label class="mt-3 block text-sm font-medium">Remarque</label>
        <input name="notes" maxlength="500" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Ex. : inventaire de fin de mois">
        <button class="mt-4 w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Commencer</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white lg:col-span-2">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">N°</th><th class="py-3">Début</th><th class="py-3">Périmètre</th><th class="py-3 text-right">Produits</th><th class="py-3">Par</th><th class="px-4 py-3">Statut</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($counts as $c)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5"><a href="{{ route('inventories.show', $c) }}" class="font-semibold hover:underline">{{ $c->reference }}</a></td>
                        <td class="py-2.5">{{ $c->started_at->format('d/m/Y H:i') }}</td>
                        <td class="py-2.5">{{ $c->category?->name ?? 'Tout le dépôt' }}</td>
                        <td class="py-2.5 text-right">{{ $c->items_count }}</td>
                        <td class="py-2.5">{{ $c->user?->name }}</td>
                        <td class="px-4 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[$c->status] }}">{{ $labels[$c->status] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">Aucun inventaire.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($counts->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $counts->links() }}</div>@endif
    </div>
</div>
@endsection
