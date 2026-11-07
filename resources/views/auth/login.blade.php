@php $storeName = \App\Models\Setting::get('store_name', config('app.name')); $logo = \App\Models\Setting::get('store_logo'); @endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · {{ $storeName }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="grid h-full place-items-center bg-slate-900 p-4 antialiased">
    <form method="POST" action="{{ route('login') }}" class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-2xl">
        @csrf
        <div class="mb-6 flex flex-col items-center text-center">
            @if ($logo)
                <img src="{{ asset('storage/'.$logo) }}" alt="" class="mb-3 h-16">
            @else
                <span class="mb-3 grid h-14 w-14 place-items-center rounded-2xl bg-emerald-500 text-2xl font-bold text-white">{{ mb_substr($storeName, 0, 1) }}</span>
            @endif
            <h1 class="text-xl font-bold text-slate-900">{{ $storeName }}</h1>
            <p class="text-sm text-slate-500">Gestion de stock</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>
        @endif

        <label class="block text-sm font-medium text-slate-700" for="email">Identifiant</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
               class="mt-1 w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">

        <label class="mt-4 block text-sm font-medium text-slate-700" for="password">Mot de passe</label>
        <input id="password" type="password" name="password" required autocomplete="current-password"
               class="mt-1 w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">

        <label class="mt-4 flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300"> Rester connecté
        </label>

        <button class="mt-6 w-full rounded-xl bg-emerald-600 py-3 font-semibold text-white hover:bg-emerald-700">Se connecter</button>
        <p class="mt-4 text-center text-xs text-slate-400">Mot de passe oublié ? Demandez à l'administrateur.</p>
    </form>
</body>
</html>
