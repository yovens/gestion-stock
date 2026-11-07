@extends('layouts.admin')

@section('title', 'Clients')

@php $F = \App\Support\Format::class; @endphp

@section('actions')
    @can('customers.manage')
        <a href="{{ route('customers.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Nouveau client</a>
    @endcan
@endsection

@section('content')
<div class="space-y-4">
    <form method="GET" class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
        <input name="q" value="{{ $q }}" placeholder="Nom, boutique ou téléphone" autofocus class="min-w-64 flex-1 rounded-lg border-slate-300 text-sm">
        <select name="type" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            <option value="">Détail et gros</option>
            <option value="retail" @selected(request('type') === 'retail')>Détail</option>
            <option value="wholesale" @selected(request('type') === 'wholesale')>Gros (revendeurs)</option>
        </select>
        <select name="filtre" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            <option value="">Actifs</option>
            <option value="dette" @selected(request('filtre') === 'dette')>Qui doivent de l'argent</option>
            <option value="inactifs" @selected(request('filtre') === 'inactifs')>Inactifs</option>
        </select>
        <button class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">Chercher</button>
        <span class="ml-auto">Crédit total à encaisser : <strong class="text-rose-600">{{ $F::money($totalDebt) }}</strong></span>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm tabular-nums">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-3">Client</th><th class="py-3">Téléphone</th><th class="py-3 text-right">Achats</th><th class="py-3 text-right">Limite</th><th class="py-3 text-right">Doit</th><th class="px-4 py-3">Depuis</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($customers as $c)
                    @php $days = $c->oldest_debt ? (int) $c->oldest_debt->diffInDays(now()) : null; @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('customers.show', $c) }}" class="font-semibold hover:underline">{{ $c->name }}</a>
                            @if ($c->type === 'wholesale')<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-800">Gros</span>@endif
                            @if ($c->business_name)<span class="block text-xs text-slate-500">{{ $c->business_name }}</span>@endif
                        </td>
                        <td class="py-3">{{ $c->phone ?: '—' }}</td>
                        <td class="py-3 text-right">{{ $F::money($c->purchases_total) }}</td>
                        <td class="py-3 text-right text-slate-500">{{ $c->credit_allowed ? $F::money($c->credit_limit) : 'pas de crédit' }}</td>
                        <td class="py-3 text-right font-semibold {{ (float) $c->balance > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ $F::money($c->balance) }}</td>
                        <td class="px-4 py-3">
                            @if ($days !== null)
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $days > 60 ? 'bg-rose-100 text-rose-700' : ($days > 30 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">{{ $days }} j</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">Aucun client.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($customers->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $customers->links() }}</div>@endif
    </div>
</div>
@endsection
