@extends('layouts.admin')

@section('title', $user->exists ? 'Modifier · '.$user->name : 'Nouveau compte')

@php $in = 'mt-1 w-full rounded-lg border-slate-300 text-sm'; @endphp

@section('content')
<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="mx-auto max-w-2xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
    @csrf
    @if ($user->exists) @method('PUT') @endif
    @if ($errors->any())<div class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="grid gap-4 md:grid-cols-2">
        <div><label class="text-sm font-medium">Nom complet *</label><input name="name" required value="{{ old('name', $user->name) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">Téléphone</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="{{ $in }}"></div>
        <div class="md:col-span-2"><label class="text-sm font-medium">Identifiant de connexion (e-mail) *</label><input type="email" name="email" required value="{{ old('email', $user->email) }}" class="{{ $in }}" placeholder="rose@depot.local">
            <p class="mt-1 text-xs text-slate-500">Pas besoin d'une vraie adresse : « prenom@depot.local » suffit.</p></div>
        <div><label class="text-sm font-medium">Mot de passe {{ $user->exists ? '(laisser vide pour ne pas changer)' : '*' }}</label><input type="password" name="password" autocomplete="new-password" @required(! $user->exists) class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">Confirmer le mot de passe</label><input type="password" name="password_confirmation" autocomplete="new-password" class="{{ $in }}"></div>
        <p class="text-xs text-slate-500 md:col-span-2">8 caractères minimum, avec des lettres et des chiffres.</p>
    </div>

    <fieldset>
        <legend class="text-sm font-medium">Rôle *</legend>
        <div class="mt-2 space-y-2">
            @foreach ($roles as $k => $l)
                <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                    <input type="radio" name="role" value="{{ $k }}" @checked(old('role', $role) === $k) class="mt-0.5">
                    <span><strong>{{ ucfirst($k) }}</strong><span class="block text-slate-500">{{ \Illuminate\Support\Str::after($l, ': ') }}</span></span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) class="rounded border-slate-300"> Compte actif</label>

    <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
        <a href="{{ route('users.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Annuler</a>
        <button class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Enregistrer</button>
    </div>
</form>
@endsection
