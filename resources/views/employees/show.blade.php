@extends('layouts.admin')

@section('title', $employee->fullName())

@php $F = \App\Support\Format::class; $methods = ['cash' => 'Espèces', 'moncash' => 'MonCash', 'natcash' => 'NatCash', 'bank' => 'Virement', 'cheque' => 'Chèque']; @endphp

@section('actions')
    <a href="{{ route('employees.edit', $employee) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Modifier</a>
@endsection

@section('content')
<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm">
            <div class="grid gap-4 sm:grid-cols-3">
                <div><div class="text-xs uppercase text-slate-500">Poste</div><div class="font-semibold">{{ $employee->position }}</div><div class="text-slate-500">{{ $employee->employee_number }}</div></div>
                <div><div class="text-xs uppercase text-slate-500">Embauché le</div><div class="font-semibold">{{ $employee->hire_date->format('d/m/Y') }}</div><div class="text-slate-500">{{ $employee->hire_date->diffForHumans(null, true) }} d'ancienneté</div></div>
                <div><div class="text-xs uppercase text-slate-500">Contact</div><div>{{ $employee->phone }}</div><div class="text-slate-500">Urgence : {{ $employee->emergency_contact_name }} {{ $employee->emergency_contact_phone }}</div></div>
            </div>
            @if ($employee->user)<p class="mt-4 text-slate-500">Compte logiciel : <strong>{{ $employee->user->name }}</strong> ({{ $employee->user->getRoleNames()->join(', ') }})</p>@endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white">
            <h2 class="p-5 pb-3 font-semibold">Historique des paiements</h2>
            <table class="w-full text-sm tabular-nums">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-2">Date</th><th class="py-2">Type</th><th class="py-2">Mois</th><th class="py-2">Mode</th><th class="py-2 text-right">Montant</th><th class="px-5 py-2"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $p)
                        <tr>
                            <td class="px-5 py-2.5">{{ $p->payment_date->format('d/m/Y') }}</td>
                            <td class="py-2.5">{{ $types[$p->type] ?? $p->type }}</td>
                            <td class="py-2.5">{{ $p->period }}</td>
                            <td class="py-2.5">{{ $methods[$p->method] ?? $p->method }}</td>
                            <td class="py-2.5 text-right font-medium {{ $p->type === 'deduction' ? 'text-rose-600' : '' }}">{{ $p->type === 'deduction' ? '−' : '' }}{{ $F::money($p->amount) }}</td>
                            <td class="px-5 py-2.5 text-right">@if ($p->created_at->isToday())<form method="POST" action="{{ route('salary-payments.destroy', $p) }}" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="text-xs text-slate-400 hover:text-rose-600">Supprimer</button></form>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">Aucun paiement.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($payments->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $payments->links() }}</div>@endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm">
            <form method="GET" class="flex items-center justify-between">
                <h2 class="font-semibold">Mois</h2>
                <input type="month" name="periode" value="{{ $period }}" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            </form>
            <dl class="mt-3 space-y-1.5 tabular-nums">
                <div class="flex justify-between"><dt>Salaire</dt><dd>{{ $F::money($summary['salary']) }}</dd></div>
                <div class="flex justify-between text-slate-500"><dt>Avances</dt><dd>− {{ $F::money($summary['advances']) }}</dd></div>
                <div class="flex justify-between text-slate-500"><dt>Retenues</dt><dd>− {{ $F::money($summary['deductions']) }}</dd></div>
                <div class="flex justify-between text-slate-500"><dt>Salaire déjà versé</dt><dd>− {{ $F::money($summary['paid']) }}</dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 font-bold"><dt>Reste à payer</dt><dd>{{ $F::money($summary['remaining']) }}</dd></div>
                @if ($summary['bonus'] > 0)<div class="flex justify-between text-emerald-700"><dt>Primes versées</dt><dd>{{ $F::money($summary['bonus']) }}</dd></div>@endif
            </dl>
        </div>

        <form method="POST" action="{{ route('employees.pay', $employee) }}" class="rounded-2xl border border-slate-200 bg-white p-5" x-data="{ type: 'salary' }">
            @csrf
            <h2 class="font-semibold">Enregistrer un paiement</h2>
            @if ($errors->any())<p class="mt-2 text-sm text-rose-600">{{ $errors->first() }}</p>@endif
            <div class="mt-3 grid grid-cols-2 gap-2">
                <select name="type" x-model="type" class="rounded-lg border-slate-300 text-sm">@foreach ($types as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                <input type="month" name="period" value="{{ $period }}" required class="rounded-lg border-slate-300 text-sm">
            </div>
            <input name="amount" type="number" step="0.01" min="0.01" required :value="type === 'salary' ? {{ $summary['remaining'] }} : ''" class="mt-2 w-full rounded-lg border-slate-300 text-right text-lg font-semibold">
            <div class="mt-2 grid grid-cols-2 gap-2">
                <select name="method" class="rounded-lg border-slate-300 text-sm">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                <input type="date" name="payment_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required class="rounded-lg border-slate-300 text-sm">
            </div>
            <input name="notes" placeholder="Remarque" class="mt-2 w-full rounded-lg border-slate-300 text-sm">
            <button class="mt-4 w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Enregistrer</button>
        </form>
    </div>
</div>
@endsection
