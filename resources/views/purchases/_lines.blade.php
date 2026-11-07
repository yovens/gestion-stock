{{-- Éditeur de lignes partagé (bon de commande / réception). Variable : $mode = 'order' | 'receipt' --}}
<section class="rounded-2xl border border-slate-200 bg-white">
    <div class="border-b border-slate-200 p-5">
        <h2 class="font-semibold">Produits</h2>
        <div class="relative mt-3" @click.outside="open = false">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
            <input x-model="query" @input="onInput" @keydown.enter.prevent="onEnter" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                   @focus="results.length && (open = true)" autocomplete="off"
                   placeholder="Chercher ou scanner un produit à ajouter…" class="w-full rounded-lg border-slate-300 pl-9 text-sm">
            <div x-show="open" x-cloak class="absolute z-30 mt-1 max-h-80 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                <template x-for="(p, i) in results" :key="p.id">
                    <button type="button" @click="add(p)" @mouseenter="active = i"
                            :class="active === i ? 'bg-emerald-50' : ''" class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm">
                        <span><span class="font-medium" x-text="p.name"></span> <span class="text-xs text-slate-500" x-text="p.sku"></span></span>
                        <span class="text-xs" :class="p.stock <= p.min_stock ? 'font-semibold text-amber-700' : 'text-slate-500'"
                              x-text="'stock ' + qty(p.stock) + ' ' + p.base.abbr"></span>
                    </button>
                </template>
                <p x-show="!results.length" class="px-4 py-3 text-sm text-slate-500">Aucun produit. <a href="{{ route('products.create') }}" target="_blank" class="font-semibold text-emerald-700 hover:underline">Créer le produit</a></p>
            </div>
        </div>
    </div>

    <div class="divide-y divide-slate-100">
        <template x-for="(l, i) in lines" :key="l._key">
            <div class="p-4" :class="mode === 'receipt' && l.product.track_expiry && !l.expiry_date ? 'bg-amber-50/50' : ''">
                <input type="hidden" :name="`items[${i}][product_id]`" :value="l.product.id">
                <div class="grid items-start gap-3 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <div class="font-medium" x-text="l.product.name"></div>
                        <div class="text-xs text-slate-500">
                            Stock actuel <span x-text="qty(l.product.stock) + ' ' + l.product.base.abbr"></span>
                            <template x-if="l.product.last_cost !== null"><span> · dernier coût <span x-text="money(l.product.last_cost)"></span>/<span x-text="l.product.base.abbr"></span></span></template>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Conditionnement</label>
                        <select :name="`items[${i}][product_unit_id]`" @change="changeUnit(l, $event.target.value)" class="mt-0.5 w-full rounded-lg border-slate-300 text-sm">
                            <option value="" :selected="!l.product_unit_id" x-text="l.product.base.name"></option>
                            <template x-for="u in l.product.units" :key="u.id">
                                <option :value="u.id" :selected="Number(l.product_unit_id) === u.id" x-text="`${u.name} (${qty(u.factor)} ${l.product.base.abbr})`"></option>
                            </template>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Quantité</label>
                        <input data-qty :name="`items[${i}][quantity]`" x-model="l.quantity" type="number" step="0.001" min="0.001" required class="mt-0.5 w-full rounded-lg border-slate-300 text-right text-sm font-semibold">
                        <div class="mt-0.5 text-right text-[11px] text-slate-500" x-show="factor(l) !== 1" x-text="'= ' + qty(baseQty(l)) + ' ' + l.product.base.abbr"></div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Prix d'achat / <span x-text="unitLabel(l).toLowerCase()"></span></label>
                        <input :name="`items[${i}][unit_cost]`" x-model="l.unit_cost" type="number" step="0.01" min="0" required class="mt-0.5 w-full rounded-lg border-slate-300 text-right text-sm">
                    </div>
                    <div class="text-right md:col-span-2">
                        <div class="text-xs text-slate-500">Total</div>
                        <div class="font-semibold tabular-nums" x-text="money(lineTotal(l))"></div>
                        <button type="button" @click="remove(i)" class="text-xs text-slate-400 hover:text-rose-600">Retirer</button>
                    </div>

                    @if ($mode === 'receipt')
                        <div class="md:col-span-4 md:col-start-5 grid grid-cols-3 gap-2">
                            <div>
                                <label class="text-xs text-slate-500">N° de lot</label>
                                <input :name="`items[${i}][batch_number]`" x-model="l.batch_number" maxlength="50" class="mt-0.5 w-full rounded-lg border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Fabrication</label>
                                <input :name="`items[${i}][manufacture_date]`" x-model="l.manufacture_date" type="date" class="mt-0.5 w-full rounded-lg border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="text-xs" :class="l.product.track_expiry ? 'font-semibold text-slate-700' : 'text-slate-500'">Expiration<span x-show="l.product.track_expiry"> *</span></label>
                                <input :name="`items[${i}][expiry_date]`" x-model="l.expiry_date" type="date" :required="l.product.track_expiry" class="mt-0.5 w-full rounded-lg border-slate-300 text-sm">
                            </div>
                        </div>
                        <div class="text-xs md:col-span-4">
                            <template x-if="expiryDays(l) !== null && expiryDays(l) <= 60">
                                <p class="mt-5 font-semibold text-amber-700">⚠ Expire dans <span x-text="expiryDays(l)"></span> jour(s) : vérifiez avant d'accepter.</p>
                            </template>
                            <template x-if="landedCost(l) !== null">
                                <p class="mt-1 text-slate-500">Coût de revient <strong x-text="money(landedCost(l))"></strong>/<span x-text="l.product.base.abbr"></span>
                                    <template x-if="costChange(l) !== null && Math.abs(costChange(l)) >= 1">
                                        <span :class="costChange(l) > 0 ? 'text-rose-600' : 'text-emerald-700'" x-text="(costChange(l) > 0 ? ' ▲ +' : ' ▼ ') + costChange(l) + ' % vs coût moyen'"></span>
                                    </template>
                                </p>
                            </template>
                        </div>
                    @endif
                </div>
            </div>
        </template>
        <p x-show="!lines.length" class="px-5 py-10 text-center text-sm text-slate-500">Cherchez un produit ci-dessus pour l'ajouter.</p>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-x-8 gap-y-2 border-t border-slate-200 bg-slate-50 px-5 py-4 text-sm">
        <span class="text-slate-500"><span x-text="lines.length"></span> ligne(s)</span>
        <span>Sous-total <strong class="tabular-nums" x-text="money(subtotal)"></strong></span>
        @if ($mode === 'receipt')
            <span>Total avec transport <strong class="text-lg tabular-nums" x-text="money(total)"></strong></span>
        @else
            <span>Total <strong class="text-lg tabular-nums" x-text="money(subtotal)"></strong></span>
        @endif
    </div>
</section>
