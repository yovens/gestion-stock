@extends('layouts.admin')

@section('title', 'Relevé · '.$customer->name)

@php $F = \App\Support\Format::class; @endphp

@section('actions')
    <form method="GET" class="flex items-center gap-2 text-sm">
        <input type="date" name="du" value="{{ $from->toDateString() }}" class="rounded-lg border-slate-300 text-sm">
        <input type="date" name="au" value="{{ $to->toDateString() }}" class="rounded-lg border-slate-300 text-sm">
        <button class="rounded-lg bg-slate-100 px-3 py-2 font-medium hover:bg-slate-200">Afficher</button>
    </form>
    <button onclick="window.print()" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Imprimer</button>
@endsection

@section('content')
<div class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-8 print:border-0 print:p-0">
    <div class="flex flex-wrap justify-between gap-6">
        <div>
            <div class="text-xl font-bold">{{ $store['name'] }}</div>
            <div class="text-sm text-slate-500">{{ $store['address'] }}<br>{{ $store['phone'] }} {{ $store['nif'] ? '· NIF '.$store['nif'] : '' }}</div>
        </div>
        <div class="text-right">
            <div class="text-xs uppercase tracking-wide text-slate-500">Relevé de compte client</div>
            <div class="text-lg font-bold">{{ $customer->name }}</div>
            <div class="text-sm text-slate-600">{{ $customer->business_name }} {{ $customer->phone ? '· '.$customer->phone : '' }}</div>
            <div class="text-sm text-slate-500">du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</div>
        </div>
    </div>

    <table class="mt-8 w-full text-sm tabular-nums">
        <thead class="border-y border-slate-200 text-left text-xs uppercase text-slate-500">
            <tr><th class="py-2">Date</th><th class="py-2">Opération</th><th class="py-2 text-right">Débit</th><th class="py-2 text-right">Crédit</th><th class="py-2 text-right">Solde</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <tr class="bg-slate-50 font-medium"><td class="py-2" colspan="4">Solde au {{ $from->format('d/m/Y') }}</td><td class="py-2 text-right">{{ $F::money($statement['opening']) }}</td></tr>
            @forelse ($statement['lines'] as $l)
                <tr>
                    <td class="py-2">{{ $l['date']->format('d/m/Y') }}</td>
                    <td class="py-2">{{ $l['label'] }}</td>
                    <td class="py-2 text-right">{{ $l['debit'] ? $F::money($l['debit']) : '' }}</td>
                    <td class="py-2 text-right text-emerald-700">{{ $l['credit'] ? $F::money($l['credit']) : '' }}</td>
                    <td class="py-2 text-right font-medium">{{ $F::money($l['balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-slate-500">Aucune opération sur la période.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr class="border-t-2 border-slate-300 text-base font-bold"><td colspan="4" class="py-3">Solde au {{ $to->format('d/m/Y') }}</td><td class="py-3 text-right">{{ $F::money($statement['closing']) }}</td></tr></tfoot>
    </table>

    <p class="mt-8 text-sm text-slate-600">Débit = achats à crédit · Crédit = paiements et retours. Merci de régler votre solde dans les meilleurs délais.</p>
    <div class="mt-12 hidden grid-cols-2 gap-12 text-sm print:grid">
        <div class="border-t border-slate-400 pt-2">Pour {{ $store['name'] }}</div>
        <div class="border-t border-slate-400 pt-2">Signature du client</div>
    </div>
</div>
@endsection
