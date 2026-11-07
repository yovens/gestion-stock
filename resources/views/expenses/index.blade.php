@extends('layouts.admin')

@section('title', 'Dépenses')

@php $F = \App\Support\Format::class; $methods = ['cash' => 'Espèces', 'moncash' => 'MonCash', 'natcash' => 'NatCash', 'bank' => 'Virement', 'cheque' => 'Chèque']; @endphp

@section('content')
<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6">
        @can('expenses.manage')
            <form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-5" x-data="{ newCat: false }">
                @csrf
                <h2 class="font-semibold">Nouvelle dépense</h2>
                @if ($errors->any())<p class="mt-2 text-sm text-rose-600">{{ $errors->first() }}</p>@endif
                <label class="mt-3 block text-sm font-medium">Catégorie</label>
                <select name="expense_category_id" x-show="!newCat" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
                <input name="new_category" x-show="newCat" x-cloak placeholder="Nouvelle catégorie" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                <button type="button" @click="newCat = !newCat" class="mt-1 text-xs font-semibold text-emerald-700" x-text="newCat ? 'Choisir dans la liste' : '+ Nouvelle catégorie'"></button>
                <label class="mt-3 block text-sm font-medium">Description *</label>
                <input name="description" required maxlength="255" placeholder="Ex. : 5 gallons diesel génératrice" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div><label class="block text-sm font-medium">Montant *</label><input name="amount" type="number" step="0.01" min="0.01" required class="mt-1 w-full rounded-lg border-slate-300 text-right text-sm"></div>
                    <div><label class="block text-sm font-medium">Date *</label><input name="expense_date" type="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required class="mt-1 w-full rounded-lg border-slate-300 text-sm"></div>
                </div>
                <label class="mt-3 block text-sm font-medium">Payé par</label>
                <select name="method" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                @if ($hasSession)
                    <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="from_register" value="1" class="rounded border-slate-300"> Payé avec l'argent de ma caisse</label>
                @endif
                <label class="mt-3 block text-sm font-medium">Reçu (photo)</label>
                <input type="file" name="attachment" accept="image/*,application/pdf" class="mt-1 block w-full text-xs file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs">
                <button class="mt-4 w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Enregistrer</button>
            </form>
        @endcan

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Par catégorie</h2>
            <p class="text-xs text-slate-500">du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</p>
            @php $max = max(1, (float) ($byCategory->max('total') ?? 1)); @endphp
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($byCategory as $c)
                    <li>
                        <div class="flex justify-between"><span>{{ $c->name }}</span><span class="tabular-nums">{{ $F::money($c->total) }}</span></div>
                        <div class="mt-1 h-1 rounded-full bg-slate-100"><div class="h-1 rounded-full bg-[#2a78d6]" style="width: {{ round((float) $c->total / $max * 100) }}%"></div></div>
                    </li>
                @empty
                    <li class="text-slate-500">Aucune dépense.</li>
                @endforelse
            </ul>
            <div class="mt-4 flex justify-between border-t border-slate-200 pt-3 font-semibold"><span>Total</span><span class="tabular-nums">{{ $F::money($total) }}</span></div>
        </div>
    </div>

    <div class="space-y-4 xl:col-span-2">
        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <label>Du <input type="date" name="du" value="{{ $from->toDateString() }}" class="rounded-lg border-slate-300 text-sm"></label>
            <label>au <input type="date" name="au" value="{{ $to->toDateString() }}" class="rounded-lg border-slate-300 text-sm"></label>
            <select name="categorie" class="rounded-lg border-slate-300 text-sm"><option value="">Toutes les catégories</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('categorie') == $c->id)>{{ $c->name }}</option>@endforeach</select>
            <button class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">Filtrer</button>
        </form>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="w-full text-sm tabular-nums">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Date</th><th class="py-3">Catégorie</th><th class="py-3">Description</th><th class="py-3">Par</th><th class="py-3 text-right">Montant</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($expenses as $e)
                        <tr>
                            <td class="px-4 py-2.5">{{ $e->expense_date->format('d/m/Y') }}</td>
                            <td class="py-2.5">{{ $e->category?->name }}</td>
                            <td class="py-2.5">{{ $e->description }}
                                <span class="block text-xs text-slate-400">{{ $e->reference }} · {{ $methods[$e->method] ?? $e->method }}{{ $e->cash_session_id ? ' · caisse' : '' }}
                                    @if ($e->attachment) · <a href="{{ asset('storage/'.$e->attachment) }}" target="_blank" class="text-emerald-700 hover:underline">reçu</a>@endif</span></td>
                            <td class="py-2.5">{{ $e->user?->name }}</td>
                            <td class="py-2.5 text-right font-medium">{{ $F::money($e->amount) }}</td>
                            <td class="px-4 py-2.5 text-right">
                                @can('expenses.manage')
                                    <form method="POST" action="{{ route('expenses.destroy', $e) }}" onsubmit="return confirm('Supprimer cette dépense ?')">@csrf @method('DELETE')<button class="text-xs text-slate-400 hover:text-rose-600">Supprimer</button></form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">Aucune dépense sur la période.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($expenses->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $expenses->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
