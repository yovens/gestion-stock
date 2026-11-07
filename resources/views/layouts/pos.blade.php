<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Caisse') · {{ \App\Models\Setting::get('store_name', config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
<div class="flex h-full flex-col">
    <header class="flex h-14 shrink-0 items-center gap-4 bg-slate-900 px-4 text-white">
        <div class="flex items-center gap-2">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-500 font-bold">{{ mb_substr(\App\Models\Setting::get('store_name', 'D'), 0, 1) }}</span>
            <span class="font-semibold">{{ \App\Models\Setting::get('store_name', config('app.name')) }}</span>
        </div>
        @yield('header')
        <div class="ml-auto flex items-center gap-3 text-sm">
            <span class="hidden text-slate-300 sm:inline">{{ auth()->user()->name }}</span>
            @if (Route::has('logout'))
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-md px-3 py-1.5 text-slate-300 hover:bg-slate-800 hover:text-white">Déconnexion</button>
                </form>
            @endif
        </div>
    </header>

    @foreach (['success' => 'bg-emerald-600', 'error' => 'bg-rose-600', 'info' => 'bg-sky-600'] as $key => $color)
        @if (session($key))
            <div class="{{ $color }} px-4 py-2 text-sm text-white">{{ session($key) }}</div>
        @endif
    @endforeach

    <main class="min-h-0 flex-1">
        @yield('content')
    </main>
</div>
</body>
</html>
