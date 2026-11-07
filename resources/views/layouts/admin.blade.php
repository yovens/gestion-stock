@php
    // Les modules sans route encore créée apparaissent grisés (« bientôt »)
    // [route, libellé, permission, icône] ; une chaîne seule = titre de section
    $nav = [
        ['dashboard', 'Tableau de bord', 'dashboard.view', 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
        ['pos.index', 'Caisse', 'pos.access', 'M3 7h18M5 7l1 12h12l1-12M9 7V5a3 3 0 016 0v2'],
        'Ventes',
        ['sales.index', 'Ventes', 'sales.view_all', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['customers.index', 'Clients & crédit', 'customers.view', 'M17 20h5v-2a4 4 0 00-5-3.9M9 20H2v-2a4 4 0 015-3.9m5-6.1a4 4 0 11-8 0 4 4 0 018 0zm6 2a3 3 0 11-6 0 3 3 0 016 0z'],
        'Stock',
        ['products.index', 'Produits', 'products.view', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
        ['stock.expiry', 'Expirations', 'stock.view', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['stock-adjustments.index', 'Pertes & ajustements', 'stock.view', 'M19 14l-7 7m0 0l-7-7m7 7V3'],
        ['inventories.index', 'Inventaires', 'inventory.manage', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        ['stock.index', 'Mouvements', 'stock.view', 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
        ['categories.index', 'Catégories', 'categories.manage', 'M7 7h.01M7 3h5a2 2 0 011.4.6l7 7a2 2 0 010 2.8l-5 5a2 2 0 01-2.8 0l-7-7A2 2 0 013 10V5a2 2 0 012-2z'],
        ['units.index', 'Unités', 'units.manage', 'M4 6h16M4 12h16M4 18h10'],
        'Achats',
        ['goods-receipts.index', 'Réceptions', 'purchases.manage', 'M3 3h2l2.4 12h11.2L21 7H6'],
        ['purchase-orders.index', 'Bons de commande', 'purchases.manage', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z'],
        ['suppliers.index', 'Fournisseurs', 'suppliers.manage', 'M9 17a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0zM3 5h11v10H3zm11 4h4l3 3v3h-7'],
        'Gestion',
        ['expenses.index', 'Dépenses', 'expenses.view', 'M12 8c-2 0-3 .9-3 2s1 2 3 2 3 .9 3 2-1 2-3 2m0-8V6m0 10v2M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['employees.index', 'Employés', 'employees.manage', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ['reports.index', 'Rapports', 'reports.view', 'M9 17v-6m3 6V7m3 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z'],
        'Administration',
        ['users.index', 'Utilisateurs', 'users.manage', 'M12 4.4a4 4 0 110 5.3M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.2'],
        ['settings.index', 'Paramètres', 'settings.manage', 'M10.3 4.3a1.7 1.7 0 013.4 0 1.7 1.7 0 002.6 1.1 1.7 1.7 0 012.4 2.4 1.7 1.7 0 001 2.6 1.7 1.7 0 010 3.4 1.7 1.7 0 00-1 2.6 1.7 1.7 0 01-2.4 2.4 1.7 1.7 0 00-2.6 1 1.7 1.7 0 01-3.4 0 1.7 1.7 0 00-2.6-1 1.7 1.7 0 01-2.4-2.4 1.7 1.7 0 00-1-2.6 1.7 1.7 0 010-3.4 1.7 1.7 0 001-2.6 1.7 1.7 0 012.4-2.4 1.7 1.7 0 002.6-1.1zM15 12a3 3 0 11-6 0 3 3 0 016 0z'],
        ['activity.index', 'Journal d\'activité', 'activity_log.view', 'M4 6h16M4 10h16M4 14h16M4 18h16'],
        ['backups.index', 'Sauvegardes', 'backup.manage', 'M4 7v10c0 2 3.6 4 8 4s8-2 8-4V7M4 7c0 2 3.6 4 8 4s8-2 8-4M4 7c0-2 3.6-4 8-4s8 2 8 4m0 5c0 2-3.6 4-8 4s-8-2-8-4'],
    ];
    // Sections vides masquées
    $user = auth()->user();
    $visible = [];
    foreach ($nav as $i => $item) {
        if (is_string($item)) {
            $hasChild = false;
            for ($j = $i + 1; $j < count($nav) && ! is_string($nav[$j]); $j++) {
                if ($user->can($nav[$j][2])) { $hasChild = true; break; }
            }
            if ($hasChild) $visible[] = $item;
        } elseif ($user->can($item[2])) {
            $visible[] = $item;
        }
    }
    $navRoutes = collect($nav)->filter(fn ($i) => is_array($i))->pluck(0)->all();
    $pendingAdjustments = $user->can('stock.approve_adjustment') ? \App\Models\StockAdjustment::where('status', 'pending')->count() : 0;
    $storeName = \App\Models\Setting::get('store_name', config('app.name'));
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Administration') · {{ $storeName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full bg-slate-50 print:bg-white text-slate-800 antialiased" x-data="{ nav: false }">
<div class="flex min-h-full">
    {{-- Barre latérale --}}
    <aside :class="nav ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-slate-900 text-slate-300 transition-transform lg:translate-x-0 print:hidden">
        <div class="flex h-16 items-center gap-3 px-5">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-500 text-lg font-bold text-white">{{ mb_substr($storeName, 0, 1) }}</span>
            <div class="min-w-0">
                <div class="truncate font-semibold text-white">{{ $storeName }}</div>
                <div class="text-xs text-slate-400">Gestion de stock</div>
            </div>
        </div>
        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-2">
            @foreach ($visible as $item)
                @if (is_string($item))
                    <div class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $item }}</div>
                    @continue
                @endif
                @php
                    [$route, $label, $permission, $icon] = $item;
                    $exists = Route::has($route);
                    $active = $exists && (request()->routeIs($route) || (str_ends_with($route, '.index')
                        && request()->routeIs(\Illuminate\Support\Str::beforeLast($route, '.').'.*')
                        && ! request()->routeIs(...$navRoutes)));
                @endphp
                <a @if ($exists) href="{{ route($route) }}" @endif
                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium
                          {{ $active ? 'bg-slate-800 text-white' : ($exists ? 'hover:bg-slate-800/60 hover:text-white' : 'cursor-default opacity-40') }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    {{ $label }}
                    @if ($route === 'stock-adjustments.index' && $pendingAdjustments)<span class="ml-auto rounded-full bg-amber-500 px-1.5 text-[10px] font-bold text-white">{{ $pendingAdjustments }}</span>@endif
                </a>
            @endforeach
        </nav>
        <div class="border-t border-slate-800 p-4 text-sm">
            <div class="font-medium text-white">{{ auth()->user()->name }}</div>
            <div class="text-xs text-slate-400">{{ auth()->user()->getRoleNames()->map(fn ($r) => ucfirst($r))->join(', ') }}</div>
            @if (Route::has('logout'))
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button class="text-xs text-slate-400 hover:text-white">Déconnexion</button>
                </form>
            @endif
        </div>
    </aside>
    <div x-show="nav" x-cloak @click="nav = false" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    {{-- Contenu --}}
    <div class="flex min-w-0 flex-1 flex-col lg:pl-64 print:pl-0">
        <header class="sticky top-0 z-20 flex h-16 print:hidden items-center gap-4 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button @click="nav = true" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Menu">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="text-lg font-semibold">@yield('title')</h1>
            <div class="ml-auto flex items-center gap-2">@yield('actions')</div>
        </header>

        @foreach (['success' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'error' => 'bg-rose-50 text-rose-800 ring-rose-200', 'info' => 'bg-sky-50 text-sky-800 ring-sky-200'] as $key => $cls)
            @if (session($key))
                <div class="mx-4 mt-4 rounded-lg px-4 py-3 text-sm ring-1 sm:mx-6 {{ $cls }}">{{ session($key) }}</div>
            @endif
        @endforeach

        <main class="flex-1 p-4 sm:p-6">
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
