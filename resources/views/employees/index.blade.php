@extends('layouts.admin')

@section('title', 'Employés')

@php
    $F = \App\Support\Format::class;
    $badge = ['active' => 'bg-emerald-100 text-emerald-800', 'suspended' => 'bg-amber-100 text-amber-800', 'terminated' => 'bg-slate-100 text-slate-500'];
    $labels = ['active' => 'Actif', 'suspended' => 'Suspendu', 'terminated' => 'Parti'];
@endphp

@section('actions')
    <a href="{{ route('employees.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Nouvel employé</a>
@endsection

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3 text-sm">
        @foreach (['active' => 'Actifs', 'suspended' => 'Suspendus', 'terminated' => 'Partis', 'tous' => 'Tous'] as $k => $l)
            <a href="{{ route('employees.index', ['statut' => $k]) }}" class="rounded-full px-3 py-1 font-medium {{ request('statut', 'active') === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">{{ $l }}</a>
        @endforeach
        <span class="ml-auto">Masse salariale mensuelle : <strong>{{ $F::money($payroll) }}</strong></span>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm tabular-nums">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Employé</th><th class="py-3">Poste</th><th class="py-3">Téléphone</th><th class="py-3">Compte</th><th class="py-3 text-right">Salaire</th><th class="py-3 text-right">Payé ce mois</th><th class="px-4 py-3">Statut</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employees as $e)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5"><a href="{{ route('employees.show', $e) }}" class="font-semibold hover:underline">{{ $e->fullName() }}</a><span class="block text-xs text-slate-400">{{ $e->employee_number }} · depuis {{ $e->hire_date->format('m/Y') }}</span></td>
                        <td class="py-2.5">{{ $e->position }}</td>
                        <td class="py-2.5">{{ $e->phone }}</td>
                        <td class="py-2.5 text-xs">{{ $e->user?->name ?? '—' }}</td>
                        <td class="py-2.5 text-right">{{ $F::money($e->salary) }}</td>
                        <td class="py-2.5 text-right {{ (float) $e->paid_this_month >= (float) $e->salary ? 'text-emerald-700' : 'text-slate-600' }}">{{ $F::money($e->paid_this_month) }}</td>
                        <td class="px-4 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[$e->status] }}">{{ $labels[$e->status] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">Aucun employé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
