@extends('layouts.admin')

@section('title', 'Catégories')

@section('content')
<div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-3" x-data="{ editing: null }">
    {{-- Ajout --}}
    <form method="POST" action="{{ route('categories.store') }}" class="h-fit rounded-2xl border border-slate-200 bg-white p-5">
        @csrf
        <h2 class="font-semibold">Nouvelle catégorie</h2>
        <label class="mt-4 block text-sm font-medium">Nom</label>
        <input name="name" required maxlength="100" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
        <label class="mt-3 block text-sm font-medium">Sous-catégorie de</label>
        <select name="parent_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
            <option value="">— Catégorie principale —</option>
            @foreach ($parents as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
        </select>
        <label class="mt-3 block text-sm font-medium">Couleur (bouton en caisse)</label>
        <input type="color" name="color" value="#2a78d6" class="mt-1 h-9 w-16 rounded border-slate-300">
        @if ($errors->any())<p class="mt-3 text-sm text-rose-600">{{ $errors->first() }}</p>@endif
        <button class="mt-4 w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Ajouter</button>
    </form>

    {{-- Liste --}}
    <div class="rounded-2xl border border-slate-200 bg-white lg:col-span-2">
        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($categories as $cat)
                @foreach ([$cat, ...$cat->children] as $c)
                    <li class="px-5 py-3 {{ $c->parent_id ? 'pl-12 bg-slate-50/50' : '' }}">
                        <div class="flex items-center gap-3" x-show="editing !== {{ $c->id }}">
                            @if ($c->color)<span class="h-3 w-3 rounded-full" style="background: {{ $c->color }}" aria-hidden="true"></span>@endif
                            <span class="{{ $c->parent_id ? '' : 'font-semibold' }} {{ $c->is_active ? '' : 'text-slate-400 line-through' }}">{{ $c->name }}</span>
                            <a href="{{ route('products.index', ['category' => $c->id]) }}" class="text-xs text-slate-500 hover:underline">{{ $c->products_count }} produit(s)</a>
                            <button type="button" @click="editing = {{ $c->id }}" class="ml-auto text-xs font-medium text-slate-600 hover:text-slate-900">Modifier</button>
                            <form method="POST" action="{{ route('categories.destroy', $c) }}" onsubmit="return confirm('Supprimer cette catégorie ?')">
                                @csrf @method('DELETE')
                                <button class="text-xs font-medium text-rose-600 hover:underline">Supprimer</button>
                            </form>
                        </div>
                        <form method="POST" action="{{ route('categories.update', $c) }}" x-show="editing === {{ $c->id }}" x-cloak class="flex flex-wrap items-center gap-2">
                            @csrf @method('PUT')
                            <input type="color" name="color" value="{{ $c->color ?: '#52514e' }}" class="h-8 w-10 rounded border-slate-300">
                            <input name="name" value="{{ $c->name }}" required class="min-w-40 flex-1 rounded-lg border-slate-300 text-sm">
                            <select name="parent_id" class="rounded-lg border-slate-300 text-sm">
                                <option value="">— Principale —</option>
                                @foreach ($parents as $p)
                                    @continue($p->id === $c->id)
                                    <option value="{{ $p->id }}" @selected($c->parent_id === $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                            <label class="flex items-center gap-1 text-xs"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($c->is_active) class="rounded border-slate-300"> Active</label>
                            <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">OK</button>
                            <button type="button" @click="editing = null" class="text-xs text-slate-500">Annuler</button>
                        </form>
                    </li>
                @endforeach
            @empty
                <li class="px-5 py-10 text-center text-slate-500">Aucune catégorie. Lancez <code>php artisan db:seed --class=CatalogSeeder</code> pour une liste de départ.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
