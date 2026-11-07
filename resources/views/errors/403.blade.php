<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accès refusé</title>
    @vite(['resources/css/app.css'])
</head>
<body class="grid h-full place-items-center bg-slate-50 p-4">
    <div class="max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-rose-100 text-xl font-bold text-rose-600">!</div>
        <h1 class="mt-4 text-lg font-bold">Accès refusé</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $message ?? ($exception?->getMessage() ?: "Vous n'avez pas accès à cette page.") }}</p>
        <p class="mt-1 text-sm text-slate-500">Si vous pensez que c'est une erreur, demandez à l'administrateur.</p>
        <a href="{{ url('/') }}" class="mt-6 inline-block rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white">Retour à l'accueil</a>
    </div>
</body>
</html>
