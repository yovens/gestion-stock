@extends('layouts.pos')

@section('title', 'Fermer la caisse')

@php $m = fn ($v) => number_format((float) $v, 2, '.', ' '); @endphp

@section('content')
<div class="h-full overflow-y-auto p-4">
    <div class="mx-auto grid max-w-4xl gap-6 md:grid-cols-2">
        <div class="rounded-2xl bg-white p-6 shadow">
            <h2 class="text-lg font-bold">Résumé de la session</h2>
            <p class="text-sm text-slate-500">{{ $session->register->name }} · ouverte le {{ $session->opened_at->format('d/m/Y à H:i') }}</p>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt>Nombre de ventes</dt><dd class="font-semibold">{{ $summary['sales_count'] }}</dd></div>
                <div class="flex justify-between"><dt>Total des ventes</dt><dd class="font-semibold">{{ $m($summary['sales_total']) }} HTG</dd></div>
                @foreach ($summary['by_method'] as $method => $amount)
                    <div class="flex justify-between pl-4 text-slate-500"><dt>{{ \App\Models\SalePayment::METHODS[$method] ?? $method }}</dt><dd>{{ $m($amount) }}</dd></div>
                @endforeach
                <div class="flex justify-between"><dt>Ventes à crédit</dt><dd>{{ $m($summary['credit_sales']) }}</dd></div>
                <div class="flex justify-between"><dt>Dépenses payées en caisse</dt><dd>{{ $m($summary['expenses']) }}</dd></div>
                <div class="flex justify-between"><dt>Remboursements</dt><dd>{{ $m($summary['refunds']) }}</dd></div>
            </dl>

            <p class="mt-6 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                Le montant attendu n'est affiché qu'après le comptage, pour un comptage honnête.
            </p>
        </div>

        <form method="POST" action="{{ route('cash.update') }}" class="rounded-2xl bg-white p-6 shadow"
              onsubmit="return confirm('Confirmer la fermeture de la caisse ?')">
            @csrf
            <h2 class="text-lg font-bold">Comptage du tiroir</h2>
            <p class="text-sm text-slate-500">Comptez les espèces présentes, fond de caisse inclus.</p>

            <label class="mt-4 block text-sm font-medium">Espèces HTG comptées</label>
            <input name="counted_amount_htg" type="number" step="0.01" min="0" required autofocus value="{{ old('counted_amount_htg') }}"
                   class="mt-1 w-full rounded-lg border-slate-300 text-right text-2xl font-bold">

            <label class="mt-4 block text-sm font-medium">Espèces USD comptées</label>
            <input name="counted_amount_usd" type="number" step="0.01" min="0" value="{{ old('counted_amount_usd', 0) }}"
                   class="mt-1 w-full rounded-lg border-slate-300 text-right text-xl font-semibold">

            <label class="mt-4 block text-sm font-medium">Remarques</label>
            <textarea name="closing_notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300">{{ old('closing_notes') }}</textarea>

            @if ($errors->any())
                <div class="mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>
            @endif

            <div class="mt-6 flex gap-3">
                <a href="{{ route('pos.index') }}" class="flex-1 rounded-xl bg-slate-100 py-3 text-center font-semibold text-slate-700 hover:bg-slate-200">Retour</a>
                <button class="flex-1 rounded-xl bg-rose-600 py-3 font-bold text-white hover:bg-rose-700">Fermer la caisse</button>
            </div>
        </form>
    </div>
</div>
@endsection
