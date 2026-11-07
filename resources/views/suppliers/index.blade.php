@extends('layouts.admin')

@section('title', 'Fournisseurs')

@php $F = \App\Support\Format::class; @endphp

@section('actions')
    <a href="{{ route('suppliers.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Nouveau fournisseur</a>
@endsection

@section('content')
<div class="space-y-4">
    <form method="GET" class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
        <input name="q" value="{{ $q }}" placeholder="Nom, contact ou téléphone" class="min-w-64 flex-1 rounded-lg border-slate-300 text-sm">
        <label class="flex items-center gap-2"><input type="checkbox" name="filtre" value="dette" @checked(request('filtre') === 'dette') onchange="this.form.submit()" class="rounded border-slate-300"> Seulement ceux à qui on doit</label>
        <button class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">Chercher</button>
        <span class="ml-auto">Dette totale : <strong class="text-rose-600">{{ $F::money($totalDebt) }}</strong></span>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm tabular-nums">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-3">Fournisseur</th><th class="py-3">Contact</th><th class="py-3">Dernière livraison</th><th class="py-3 text-right">Achats total</th><th class="px-4 py-3 text-right">On lui doit</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($suppliers as $s)
                    <tr class="hover:bg-slate-50 {{ $s->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3"><a href="{{ route('suppliers.show', $s) }}" class="font-semibold hover:underline">{{ $s->name }}</a>
                            @if ($s->payment_terms_days)<span class="block text-xs text-slate-500">crédit {{ $s->payment_terms_days }} jours</span>@endif</td>
                        <td class="py-3">{{ $s->contact_person }}<span class="block text-xs text-slate-500">{{ $s->phone }}</span></td>
                        <td class="py-3">{{ $s->last_receipt ? \Illuminate\Support\Carbon::parse($s->last_receipt)->format('d/m/Y') : '—' }}</td>
                        <td class="py-3 text-right">{{ $F::money($s->purchases_total) }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ (float) $s->balance > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ $F::money($s->balance) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">Aucun fournisseur.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($suppliers->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $suppliers->links() }}</div>@endif
    </div>
</div>
@endsection
