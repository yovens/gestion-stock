@extends('layouts.pos')

@section('title', 'Ouvrir la caisse')

@section('content')
<div class="grid h-full place-items-center p-4">
    <form method="POST" action="{{ route('cash.store') }}" class="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
        @csrf
        <h1 class="text-2xl font-bold">Ouvrir la caisse</h1>
        <p class="mt-1 text-sm text-slate-500">Comptez le fond de caisse avant de commencer.</p>

        @if ($registers->isEmpty())
            <p class="mt-6 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                Toutes les caisses sont déjà ouvertes ou désactivées. Demandez à l'administrateur.
            </p>
        @else
            <label class="mt-6 block text-sm font-medium">Caisse</label>
            <select name="cash_register_id" class="mt-1 w-full rounded-lg border-slate-300" required>
                @foreach ($registers as $r)
                    <option value="{{ $r->id }}" @selected(old('cash_register_id') == $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>

            <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium">Fond en HTG</label>
                    <input name="opening_amount_htg" type="number" step="0.01" min="0" required autofocus
                           value="{{ old('opening_amount_htg', 0) }}" class="mt-1 w-full rounded-lg border-slate-300 text-right text-lg font-semibold">
                </div>
                <div>
                    <label class="block text-sm font-medium">Fond en USD</label>
                    <input name="opening_amount_usd" type="number" step="0.01" min="0"
                           value="{{ old('opening_amount_usd', 0) }}" class="mt-1 w-full rounded-lg border-slate-300 text-right text-lg font-semibold">
                </div>
            </div>

            @if ($errors->any())
                <div class="mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>
            @endif

            <button class="mt-6 w-full rounded-xl bg-emerald-600 py-3 text-lg font-bold text-white hover:bg-emerald-700">Ouvrir et commencer</button>
        @endif
    </form>
</div>
@endsection
