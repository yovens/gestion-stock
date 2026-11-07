@extends('layouts.admin')

@section('title', $employee->exists ? 'Modifier · '.$employee->fullName() : 'Nouvel employé')

@php $in = 'mt-1 w-full rounded-lg border-slate-300 text-sm'; @endphp

@section('content')
<form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" class="mx-auto max-w-4xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6">
    @csrf
    @if ($employee->exists) @method('PUT') @endif
    @if ($errors->any())<div class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

    <div class="grid gap-4 md:grid-cols-3">
        <div><label class="text-sm font-medium">Prénom *</label><input name="first_name" required value="{{ old('first_name', $employee->first_name) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">Nom *</label><input name="last_name" required value="{{ old('last_name', $employee->last_name) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">Sexe</label><select name="gender" class="{{ $in }}"><option value=""></option><option value="M" @selected(old('gender', $employee->gender) === 'M')>M</option><option value="F" @selected(old('gender', $employee->gender) === 'F')>F</option></select></div>
        <div><label class="text-sm font-medium">Date de naissance</label><input type="date" name="birth_date" value="{{ old('birth_date', $employee->birth_date?->toDateString()) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">Téléphone</label><input name="phone" value="{{ old('phone', $employee->phone) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">NIF / CIN</label><input name="nif_cin" value="{{ old('nif_cin', $employee->nif_cin) }}" class="{{ $in }}"></div>
        <div class="md:col-span-3"><label class="text-sm font-medium">Adresse</label><input name="address" value="{{ old('address', $employee->address) }}" class="{{ $in }}"></div>

        <div><label class="text-sm font-medium">Poste *</label><input name="position" required list="positions" value="{{ old('position', $employee->position) }}" class="{{ $in }}">
            <datalist id="positions"><option value="Caissier(ère)"><option value="Secrétaire"><option value="Magasinier"><option value="Manutentionnaire"><option value="Chauffeur"><option value="Gardien"><option value="Gérant"></datalist></div>
        <div><label class="text-sm font-medium">Date d'embauche *</label><input type="date" name="hire_date" required value="{{ old('hire_date', $employee->hire_date?->toDateString()) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">Date de départ</label><input type="date" name="end_date" value="{{ old('end_date', $employee->end_date?->toDateString()) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">Salaire (HTG) *</label><input type="number" step="0.01" min="0" name="salary" required value="{{ old('salary', (float) $employee->salary) }}" class="{{ $in }} text-right"></div>
        <div><label class="text-sm font-medium">Fréquence</label><select name="salary_frequency" class="{{ $in }}">@foreach (['monthly' => 'Mensuel', 'biweekly' => 'Quinzaine', 'weekly' => 'Hebdomadaire'] as $k => $l)<option value="{{ $k }}" @selected(old('salary_frequency', $employee->salary_frequency) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label class="text-sm font-medium">Statut</label><select name="status" class="{{ $in }}">@foreach (['active' => 'Actif', 'suspended' => 'Suspendu', 'terminated' => 'Parti'] as $k => $l)<option value="{{ $k }}" @selected(old('status', $employee->status) === $k)>{{ $l }}</option>@endforeach</select></div>

        <div><label class="text-sm font-medium">Contact d'urgence</label><input name="emergency_contact_name" value="{{ old('emergency_contact_name', $employee->emergency_contact_name) }}" class="{{ $in }}"></div>
        <div><label class="text-sm font-medium">Téléphone d'urgence</label><input name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone) }}" class="{{ $in }}"></div>
        <div>
            <label class="text-sm font-medium">Compte utilisateur</label>
            <select name="user_id" class="{{ $in }}">
                <option value="">Aucun (n'utilise pas le logiciel)</option>
                @foreach ($users as $u)<option value="{{ $u->id }}" @selected(old('user_id', $employee->user_id) == $u->id)>{{ $u->name }} · {{ $u->email }}</option>@endforeach
            </select>
        </div>
        <div class="md:col-span-3"><label class="text-sm font-medium">Notes</label><textarea name="notes" rows="2" class="{{ $in }}">{{ old('notes', $employee->notes) }}</textarea></div>
    </div>

    <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
        <a href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Annuler</a>
        <button class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Enregistrer</button>
    </div>
</form>
@endsection
