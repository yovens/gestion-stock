@extends('layouts.admin')

@section('title', 'Paramètres')

@php $in = 'mt-1 w-full rounded-lg border-slate-300 text-sm'; $F = \App\Support\Format::class; @endphp

@section('actions')
    @can('backup.manage')<a href="{{ route('backups.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Sauvegardes</a>@endcan
    @can('activity_log.view')<a href="{{ route('activity.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Journal d'activité</a>@endcan
@endsection

@section('content')
<div class="grid gap-6 xl:grid-cols-3">
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-6 xl:col-span-2">
        @csrf @method('PUT')
        @if ($errors->any())<div class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

        <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="font-semibold">Le dépôt (affiché sur les reçus et documents)</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div><label class="text-sm font-medium">Nom *</label><input name="store_name" required value="{{ old('store_name', $values['store_name']) }}" class="{{ $in }}"></div>
                <div><label class="text-sm font-medium">Téléphone(s)</label><input name="store_phone" value="{{ old('store_phone', $values['store_phone']) }}" class="{{ $in }}"></div>
                <div><label class="text-sm font-medium">Adresse</label><input name="store_address" value="{{ old('store_address', $values['store_address']) }}" class="{{ $in }}"></div>
                <div><label class="text-sm font-medium">NIF</label><input name="store_nif" value="{{ old('store_nif', $values['store_nif']) }}" class="{{ $in }}"></div>
                <div class="md:col-span-2"><label class="text-sm font-medium">Message en bas du reçu</label><input name="receipt_footer" value="{{ old('receipt_footer', $values['receipt_footer'] ?? 'Merci pour votre achat !') }}" class="{{ $in }}"></div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium">Logo</label>
                    <div class="mt-1 flex items-center gap-4">
                        @if ($logo)<img src="{{ asset('storage/'.$logo) }}" alt="" class="h-14 rounded">@endif
                        <input type="file" name="logo" accept="image/*" class="text-xs file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs">
                        @if ($logo)<label class="flex items-center gap-1 text-xs"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300"> Retirer</label>@endif
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="font-semibold">Règles</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div><label class="text-sm font-medium">Remise max. caissier (%)</label><input type="number" step="0.5" min="0" max="100" name="max_discount_percent" value="{{ old('max_discount_percent', $values['max_discount_percent'] ?? 10) }}" class="{{ $in }}"></div>
                <div><label class="text-sm font-medium">Alerte expiration (jours)</label><input type="number" min="1" max="365" name="expiry_alert_days" value="{{ old('expiry_alert_days', $values['expiry_alert_days'] ?? 30) }}" class="{{ $in }}"></div>
                <div><label class="text-sm font-medium">Taux USD par défaut</label><input type="number" step="0.01" min="0.01" name="default_usd_rate" value="{{ old('default_usd_rate', $values['default_usd_rate'] ?? 132) }}" class="{{ $in }}"></div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="font-semibold">Sauvegarde automatique</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2"><label class="text-sm font-medium">Copier aussi les sauvegardes dans ce dossier</label><input name="backup_path" value="{{ old('backup_path', $values['backup_path']) }}" class="{{ $in }}" placeholder="E:\Sauvegardes  ou  C:\Users\…\Google Drive\Depot">
                    <p class="mt-1 text-xs text-slate-500">Une clé USB ou un dossier Google Drive / OneDrive : si l'ordinateur est perdu, les données sont ailleurs.</p></div>
                <div><label class="text-sm font-medium">Nombre de sauvegardes gardées</label><input type="number" min="3" max="365" name="backup_keep" value="{{ old('backup_keep', $values['backup_keep'] ?? 30) }}" class="{{ $in }}"></div>
            </div>
        </section>

        <div class="flex justify-end"><button class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Enregistrer les paramètres</button></div>
    </form>

    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Taux du dollar</h2>
            <p class="mt-1 text-sm">Actuel : <strong>1 USD = {{ $currentRate }} HTG</strong></p>
            <form method="POST" action="{{ route('settings.rate') }}" class="mt-3 flex gap-2">
                @csrf
                <input type="number" step="0.01" min="0.01" name="rate" required placeholder="Nouveau taux" class="w-full rounded-lg border-slate-300 text-sm">
                <input type="date" name="effective_date" value="{{ today()->toDateString() }}" class="rounded-lg border-slate-300 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 text-sm font-semibold text-white">OK</button>
            </form>
            <ul class="mt-3 divide-y divide-slate-100 text-xs">
                @foreach ($rates as $r)<li class="flex justify-between py-1.5"><span>{{ $r->effective_date->format('d/m/Y') }} · {{ $r->user?->name }}</span><span class="font-semibold">{{ (float) $r->rate }}</span></li>@endforeach
            </ul>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Caisses</h2>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @foreach ($registers as $r)
                    <li class="flex items-center justify-between py-2">
                        <span class="{{ $r->is_active ? '' : 'text-slate-400 line-through' }}">{{ $r->name }} @if ($r->open_count)<span class="ml-1 rounded bg-emerald-100 px-1.5 text-[10px] font-bold text-emerald-700">OUVERTE</span>@endif</span>
                        <form method="POST" action="{{ route('settings.registers.toggle', $r) }}">@csrf @method('PATCH')<button class="text-xs text-slate-500 hover:underline">{{ $r->is_active ? 'Désactiver' : 'Activer' }}</button></form>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('settings.registers.store') }}" class="mt-3 flex gap-2">
                @csrf
                <input name="name" required placeholder="Ex. : Caisse Gros" class="w-full rounded-lg border-slate-300 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 text-sm font-semibold text-white">Ajouter</button>
            </form>
        </div>
    </div>
</div>
@endsection
