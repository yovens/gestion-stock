@extends('layouts.pos')

@section('title', 'Caisse')

@section('header')
    <div class="hidden items-center gap-2 text-sm text-slate-300 md:flex">
        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
        {{ $session->register->name }} · ouverte à {{ $session->opened_at->format('H:i') }}
    </div>
    @can('customers.view')
        <a href="{{ route('customers.index', ['filtre' => 'dette']) }}" target="_blank" class="ml-4 rounded-md px-3 py-1.5 text-sm text-slate-300 hover:bg-slate-800 hover:text-white">Paiement de crédit</a>
    @endcan
    @can('dashboard.view')
        <a href="{{ route('dashboard') }}" class="rounded-md px-3 py-1.5 text-sm text-slate-300 hover:bg-slate-800 hover:text-white">Administration</a>
    @endcan
    <a href="{{ route('cash.close') }}" class="ml-2 rounded-md bg-slate-800 px-3 py-1.5 text-sm hover:bg-slate-700">Fermer la caisse</a>
@endsection

@section('content')
<div x-data="pos(@js($config + ['urls' => [
        'products' => route('pos.products'),
        'customers' => route('pos.customers'),
        'store' => route('pos.store'),
        'held' => route('pos.held'),
        'resume' => route('pos.resume', '__ID__'),
        'heldDestroy' => route('pos.held.destroy', '__ID__'),
    ]]))" x-cloak class="flex h-full">

    {{-- =========================== PRODUITS =========================== --}}
    <section class="flex min-w-0 flex-1 flex-col">
        <div class="space-y-3 border-b border-slate-200 bg-white p-4">
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input x-ref="search" x-model="query" @input="onSearchInput" @keydown.enter.prevent="onSearchEnter"
                       type="text" autocomplete="off" placeholder="Scanner un code-barres ou chercher un produit…  (F2)"
                       class="w-full rounded-xl border border-slate-300 py-3 pl-11 pr-24 text-lg focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                <span x-show="loading" class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-slate-400">Recherche…</span>
            </div>

            <div class="flex gap-2 overflow-x-auto pb-1">
                <button @click="setCategory(null)"
                        :class="category === null ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium">Tout</button>
                @foreach ($categories as $cat)
                    <button @click="setCategory({{ $cat->id }})"
                            :class="category === {{ $cat->id }} ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                            class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium">
                        @if ($cat->color)<span class="mr-1 inline-block h-2 w-2 rounded-full" style="background: {{ $cat->color }}"></span>@endif
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-4">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 2xl:grid-cols-4">
                <template x-for="p in results" :key="p.id">
                    <div class="flex flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-emerald-400 hover:shadow"
                         :class="p.stock <= 0 && 'opacity-50'">
                        <button @click="add(p)" :disabled="p.stock <= 0" class="text-left">
                            <div class="mb-2 grid h-20 place-items-center overflow-hidden rounded-lg bg-slate-50">
                                <template x-if="p.image"><img :src="p.image" alt="" class="h-full w-full object-cover"></template>
                                <template x-if="!p.image"><span class="text-2xl font-bold text-slate-300" x-text="p.name.slice(0, 2).toUpperCase()"></span></template>
                            </div>
                            <div class="line-clamp-2 min-h-10 text-sm font-semibold leading-5" x-text="p.name"></div>
                            <div class="mt-1 flex items-baseline justify-between gap-2">
                                <span class="font-bold text-emerald-700" x-text="money(p.base.retail)"></span>
                                <span class="text-xs text-slate-500" x-text="'/ ' + p.base.abbr"></span>
                            </div>
                            <div class="mt-1 text-xs"
                                 :class="p.stock <= 0 ? 'font-semibold text-rose-600' : (p.low ? 'font-semibold text-amber-600' : 'text-slate-500')"
                                 x-text="p.stock <= 0 ? 'Rupture de stock' : 'Stock : ' + qty(p.stock) + ' ' + p.base.abbr"></div>
                        </button>
                        <div class="mt-2 flex flex-wrap gap-1" x-show="p.units.length">
                            <template x-for="u in p.units" :key="u.id">
                                <button @click="add(p, u.id)" :disabled="p.stock < u.factor"
                                        class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-100 disabled:opacity-40"
                                        x-text="'+ ' + u.name + ' · ' + money(u.retail)"></button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
            <p x-show="!loading && results.length === 0" class="py-16 text-center text-slate-500">Aucun produit trouvé.</p>
        </div>
    </section>

    {{-- ============================ PANIER ============================ --}}
    <aside class="flex w-[440px] shrink-0 flex-col border-l border-slate-200 bg-white">
        <div class="flex items-center gap-2 border-b border-slate-200 p-3">
            <button @click="openCustomer()" class="flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left hover:bg-slate-50">
                <svg class="h-5 w-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span class="truncate text-sm" x-text="customer ? customer.name : 'Client comptoir'"></span>
                <span x-show="customer && customer.balance > 0" class="ml-auto shrink-0 text-xs text-rose-600" x-text="'Doit ' + money(customer?.balance)"></span>
            </button>
            <button x-show="customer" @click="selectCustomer(null)" class="rounded-lg px-2 py-2 text-slate-400 hover:bg-slate-100" title="Retirer le client">✕</button>
            <div class="flex overflow-hidden rounded-lg border border-slate-200 text-xs font-semibold">
                <button @click="saleType = 'retail'" :class="saleType === 'retail' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="px-3 py-2">Détail</button>
                <button @click="saleType = 'wholesale'" :class="saleType === 'wholesale' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="px-3 py-2">Gros</button>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <template x-for="line in cart" :key="line.key">
                <div class="border-b border-slate-100 px-3 py-2.5" :class="overStock(line) && 'bg-rose-50'">
                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold" x-text="line.product.name"></div>
                            <div class="mt-0.5 flex items-center gap-2 text-xs text-slate-500">
                                <select @change="changeUnit(line, $event.target.value)" class="rounded border-slate-200 py-0 pl-1 pr-6 text-xs">
                                    <option value="" :selected="!line.unitId" x-text="line.product.base.name"></option>
                                    <template x-for="u in line.product.units" :key="u.id">
                                        <option :value="u.id" :selected="line.unitId === u.id" x-text="u.name + ' (' + qty(u.factor) + ')'"></option>
                                    </template>
                                </select>
                                <span x-text="money(price(line))"></span>
                                <span x-show="isWholesale(line)" class="rounded bg-amber-100 px-1.5 font-semibold text-amber-800">GROS</span>
                            </div>
                            <div x-show="overStock(line)" class="mt-1 text-xs font-semibold text-rose-600"
                                 x-text="'Stock insuffisant (' + qty(line.product.stock) + ' ' + line.product.base.abbr + ' dispo)'"></div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold" x-text="money(lineTotal(line))"></div>
                            <button @click="remove(line)" class="text-xs text-slate-400 hover:text-rose-600">Retirer</button>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <button @click="inc(line, -1)" class="h-8 w-8 rounded-lg bg-slate-100 text-lg font-bold hover:bg-slate-200">−</button>
                        <input :value="line.quantity" @change="setQty(line, $event.target.value); $event.target.value = line.quantity"
                               @focus="$event.target.select()" inputmode="decimal"
                               class="h-8 w-20 rounded-lg border-slate-200 text-center text-sm font-semibold">
                        <button @click="inc(line, 1)" class="h-8 w-8 rounded-lg bg-slate-100 text-lg font-bold hover:bg-slate-200">+</button>
                        <label class="ml-auto flex items-center gap-1 text-xs text-slate-500">
                            Remise
                            <input :value="line.discount || ''" @change="setLineDiscount(line, $event.target.value); $event.target.value = line.discount || ''"
                                   inputmode="decimal" placeholder="0" class="h-8 w-20 rounded-lg border-slate-200 text-right text-sm">
                        </label>
                    </div>
                </div>
            </template>
            <div x-show="!cart.length" class="grid h-full place-items-center p-8 text-center text-slate-400">
                <div>
                    <div class="mb-2 text-4xl">🛒</div>
                    <p>Scannez ou touchez un produit pour l'ajouter.</p>
                    <p class="mt-4 text-xs">F2 recherche · F4 encaisser · F8 attente</p>
                </div>
            </div>
        </div>

        <div class="space-y-1.5 border-t border-slate-200 bg-slate-50 p-4 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Sous-total (<span x-text="itemsCount"></span> art.)</span><span x-text="money(gross)"></span></div>
            <div class="flex justify-between" x-show="lineDiscounts > 0"><span class="text-slate-500">Remises lignes</span><span class="text-rose-600" x-text="'− ' + money(lineDiscounts)"></span></div>
            <div class="flex items-center justify-between">
                <span class="text-slate-500">Remise globale</span>
                <input :value="discount || ''" @change="setGlobalDiscount($event.target.value); $event.target.value = discount || ''"
                       inputmode="decimal" placeholder="0" class="h-8 w-28 rounded-lg border-slate-200 text-right text-sm">
            </div>
            <div class="flex items-baseline justify-between border-t border-slate-200 pt-2">
                <span class="text-base font-semibold">TOTAL</span>
                <span class="text-3xl font-extrabold text-slate-900" x-text="money(total)"></span>
            </div>
            <div class="text-right text-xs text-slate-500" x-text="'≈ ' + usd(total / config.rate) + ' (taux ' + config.rate + ')'"></div>

            <div class="grid grid-cols-4 gap-2 pt-2">
                <button @click="clearCart()" class="rounded-xl bg-white px-2 py-3 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100">Vider</button>
                <button @click="openHeld()" class="relative rounded-xl bg-white px-2 py-3 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100">
                    Attente
                    <span x-show="heldCount > 0" x-text="heldCount" class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-amber-500 px-1 text-[10px] text-white"></span>
                </button>
                <button @click="hold()" class="rounded-xl bg-amber-100 px-2 py-3 text-xs font-semibold text-amber-900 hover:bg-amber-200">Mettre en attente <span class="opacity-60">F8</span></button>
                <button @click="openPay()" :disabled="!cart.length"
                        class="rounded-xl bg-emerald-600 px-2 py-3 text-sm font-bold text-white shadow hover:bg-emerald-700 disabled:opacity-40">Encaisser <span class="block text-[10px] font-normal opacity-80">F4</span></button>
            </div>
            <button x-show="lastSale" @click="printReceipt(lastSale)" class="w-full pt-1 text-center text-xs text-slate-500 hover:text-slate-900"
                    x-text="'Réimprimer le reçu ' + lastSale?.reference"></button>
        </div>
    </aside>

    {{-- ========================= MODALE PAIEMENT ========================= --}}
    <div x-show="showPay" x-transition.opacity class="fixed inset-0 z-40 grid place-items-center bg-slate-900/60 p-4" @click.self="showPay = false">
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl" @keydown.enter.prevent="submit()">
            <div class="flex items-center justify-between border-b border-slate-200 p-5">
                <h2 class="text-lg font-bold">Encaissement</h2>
                <div class="text-right">
                    <div class="text-xs uppercase text-slate-500">À payer</div>
                    <div class="text-3xl font-extrabold" x-text="money(total)"></div>
                </div>
            </div>

            <div class="space-y-3 p-5">
                <template x-for="(p, i) in payments" :key="i">
                    <div class="flex flex-wrap items-center gap-2">
                        <select x-model="p.method" class="rounded-lg border-slate-300 text-sm">
                            <template x-for="(label, key) in config.methods" :key="key">
                                <option :value="key" x-text="label" :selected="p.method === key"></option>
                            </template>
                        </select>
                        <select x-model="p.currency" class="w-24 rounded-lg border-slate-300 text-sm">
                            <option value="HTG">HTG</option>
                            <option value="USD">USD</option>
                        </select>
                        <input x-model.number="p.amount" data-pay-amount inputmode="decimal" type="number" step="0.01" min="0"
                               class="w-40 rounded-lg border-slate-300 text-right text-lg font-semibold">
                        <span x-show="p.currency === 'USD'" class="text-xs text-slate-500" x-text="'= ' + money(toHtg(p))"></span>
                        <input x-show="p.method !== 'cash'" x-model="p.reference" placeholder="N° transaction"
                               class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm">
                        <button x-show="payments.length > 1" @click="removePayment(i)" class="ml-auto text-slate-400 hover:text-rose-600">✕</button>
                    </div>
                </template>

                <div class="flex flex-wrap gap-2">
                    <template x-for="v in quickAmounts()" :key="v">
                        <button @click="setCash(v)" class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold hover:bg-slate-200" x-text="money(v)"></button>
                    </template>
                    <button @click="addPayment()" class="rounded-lg px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">+ Autre moyen de paiement</button>
                </div>

                <div class="grid grid-cols-3 gap-3 rounded-xl bg-slate-50 p-4 text-center">
                    <div><div class="text-xs uppercase text-slate-500">Reçu</div><div class="text-xl font-bold" x-text="money(paid)"></div></div>
                    <div><div class="text-xs uppercase text-slate-500">Monnaie</div><div class="text-xl font-bold text-emerald-700" x-text="money(change)"></div></div>
                    <div><div class="text-xs uppercase text-slate-500">Reste (crédit)</div><div class="text-xl font-bold" :class="due > 0 ? 'text-rose-600' : ''" x-text="money(due)"></div></div>
                </div>

                <p x-show="creditProblem" x-text="creditProblem" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700"></p>
                <p x-show="changeProblem" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">La monnaie ne peut être rendue que sur un paiement en espèces.</p>
                <p x-show="due > 0 && !creditProblem" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800"
                   x-text="'Le reste (' + money(due) + ') sera ajouté au compte de ' + customer?.name + '.'"></p>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-200 p-5">
                <button @click="showPay = false" class="rounded-xl px-5 py-3 font-semibold text-slate-600 hover:bg-slate-100">Annuler</button>
                <button @click="submit()" :disabled="!canSubmit"
                        class="rounded-xl bg-emerald-600 px-8 py-3 text-lg font-bold text-white shadow hover:bg-emerald-700 disabled:opacity-40">
                    <span x-show="!submitting">Valider la vente</span>
                    <span x-show="submitting">Enregistrement…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ========================== MODALE CLIENT ========================== --}}
    <div x-show="showCustomer" x-transition.opacity class="fixed inset-0 z-40 grid place-items-center bg-slate-900/60 p-4" @click.self="showCustomer = false">
        <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
            <h2 class="mb-3 text-lg font-bold">Choisir un client</h2>
            <input x-ref="customerSearch" x-model="customerQuery" @input="onCustomerInput" placeholder="Nom, boutique ou téléphone"
                   class="mb-3 w-full rounded-lg border-slate-300">
            <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                <template x-for="c in customerResults" :key="c.id">
                    <button @click="selectCustomer(c)" class="flex w-full items-center justify-between px-2 py-2.5 text-left hover:bg-slate-50">
                        <div>
                            <div class="font-semibold" x-text="c.name"></div>
                            <div class="text-xs text-slate-500" x-text="[c.business_name, c.phone].filter(Boolean).join(' · ')"></div>
                        </div>
                        <div class="text-right text-xs">
                            <span x-show="c.type === 'wholesale'" class="rounded bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-800">GROS</span>
                            <div x-show="c.balance > 0" class="mt-1 text-rose-600" x-text="'Doit ' + money(c.balance)"></div>
                        </div>
                    </button>
                </template>
                <p x-show="!customerResults.length" class="py-6 text-center text-sm text-slate-500">Aucun client.</p>
            </div>
        </div>
    </div>

    {{-- ======================= MODALE VENTES EN ATTENTE ======================= --}}
    <div x-show="showHeld" x-transition.opacity class="fixed inset-0 z-40 grid place-items-center bg-slate-900/60 p-4" @click.self="showHeld = false">
        <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
            <h2 class="mb-3 text-lg font-bold">Ventes en attente</h2>
            <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                <template x-for="h in heldList" :key="h.id">
                    <div class="flex items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold" x-text="h.reference + ' · ' + h.time"></div>
                            <div class="truncate text-xs text-slate-500" x-text="(h.customer || 'Client comptoir') + ' · ' + h.items_count + ' article(s)' + (h.notes ? ' · ' + h.notes : '')"></div>
                        </div>
                        <span class="font-bold" x-text="money(h.total)"></span>
                        <button @click="resume(h)" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700">Reprendre</button>
                        <button @click="deleteHeld(h)" class="text-slate-400 hover:text-rose-600">✕</button>
                    </div>
                </template>
                <p x-show="!heldList.length" class="py-6 text-center text-sm text-slate-500">Aucune vente en attente.</p>
            </div>
        </div>
    </div>

    {{-- ============================== TOAST ============================== --}}
    <div x-show="toast" x-transition class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-lg"
         :class="toast?.type === 'success' ? 'bg-emerald-600' : 'bg-rose-600'" x-text="toast?.msg"></div>
</div>
@endsection
