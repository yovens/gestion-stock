@extends('layouts.admin')

@section('title', 'Utilisateurs')

@section('actions')
    <a href="{{ route('users.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Nouveau compte</a>
@endsection

@section('content')
<div class="space-y-4">
    <div class="grid gap-3 text-sm sm:grid-cols-3">
        @foreach ($roles as $k => $l)
            <div class="rounded-xl border border-slate-200 bg-white p-3"><strong>{{ ucfirst($k) }}</strong><span class="block text-xs text-slate-500">{{ \Illuminate\Support\Str::after($l, ': ') }}</span></div>
        @endforeach
    </div>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Nom</th><th class="py-3">Identifiant (e-mail)</th><th class="py-3">Rôle</th><th class="py-3">Dernière connexion</th><th class="py-3">État</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $u)
                    <tr class="{{ $u->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-2.5 font-medium">{{ $u->name }}@if ($u->is(auth()->user())) <span class="text-xs text-slate-400">(vous)</span>@endif
                            @if ($u->employee)<span class="block text-xs text-slate-400">{{ $u->employee->position }}</span>@endif</td>
                        <td class="py-2.5">{{ $u->email }}<span class="block text-xs text-slate-400">{{ $u->phone }}</span></td>
                        <td class="py-2.5"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold">{{ ucfirst($u->getRoleNames()->first() ?? '—') }}</span></td>
                        <td class="py-2.5 text-slate-500">{{ $u->last_login_at?->diffForHumans() ?? 'jamais' }}</td>
                        <td class="py-2.5">{{ $u->is_active ? 'Actif' : 'Désactivé' }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="{{ route('users.edit', $u) }}" class="text-xs font-medium text-slate-600 hover:text-slate-900">Modifier</a>
                            @unless ($u->is(auth()->user()))
                                <form method="POST" action="{{ route('users.toggle', $u) }}" class="inline">@csrf @method('PATCH')
                                    <button class="ml-3 text-xs font-medium {{ $u->is_active ? 'text-rose-600' : 'text-emerald-700' }} hover:underline">{{ $u->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
