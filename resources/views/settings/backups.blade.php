@extends('layouts.admin')

@section('title', 'Sauvegardes')

@section('actions')
    <form method="POST" action="{{ route('backups.run') }}">@csrf
        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Sauvegarder maintenant</button>
    </form>
@endsection

@section('content')
<div class="mx-auto max-w-4xl space-y-4">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
        Une sauvegarde automatique est faite chaque jour (base de données + photos + factures).
        @if ($external)
            Une copie est aussi envoyée dans <strong>{{ $external }}</strong>.
        @else
            <span class="font-semibold text-amber-700">Aucun dossier de copie externe : réglez-le dans les paramètres (clé USB, Google Drive).</span>
        @endif
        <p class="mt-2 text-xs">Pour restaurer : décompresser le zip, importer le fichier <code>base_….sql</code> dans MySQL (HeidiSQL dans Laragon) et copier le dossier <code>fichiers</code> dans <code>storage/app/public</code>.</p>
    </div>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Fichier</th><th class="py-3">Date</th><th class="py-3 text-right">Taille</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($files as $f)
                    <tr>
                        <td class="px-4 py-2.5 font-mono text-xs">{{ $f->getFilename() }}</td>
                        <td class="py-2.5">{{ \Illuminate\Support\Carbon::createFromTimestamp($f->getMTime())->format('d/m/Y H:i') }}</td>
                        <td class="py-2.5 text-right">{{ number_format($f->getSize() / 1048576, 2, ',', ' ') }} Mo</td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="{{ route('backups.download', $f->getFilename()) }}" class="text-xs font-semibold text-emerald-700 hover:underline">Télécharger</a>
                            <form method="POST" action="{{ route('backups.destroy', $f->getFilename()) }}" class="inline" onsubmit="return confirm('Supprimer cette sauvegarde ?')">@csrf @method('DELETE')
                                <button class="ml-3 text-xs text-slate-400 hover:text-rose-600">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-12 text-center font-semibold text-amber-700">Aucune sauvegarde ! Cliquez sur « Sauvegarder maintenant ».</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
