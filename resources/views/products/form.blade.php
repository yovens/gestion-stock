@extends('layouts.admin')

@section('title', $product->exists ? 'Modifier · '.$product->name : 'Nouveau produit')

@php
    $input = 'mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500';
    $label = 'block text-sm font-medium text-slate-700';
    $priceLock = $canEditPrices ? '' : 'readonly';
    $priceLockCls = $canEditPrices ? '' : ' bg-slate-50 text-slate-500';
@endphp

@section('content')
<form method="POST" enctype="multipart/form-data"
      action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}"
      x-data="productForm(@js($initial + ['unitsList' => $units]))"
      class="mx-auto max-w-5xl space-y-6">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    @if ($copiedFrom)
        <div class="rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-800 ring-1 ring-sky-200">Copie de « {{ $copiedFrom->name }} » : changez le nom, le code et les prix.</div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200">
            <strong>Le produit n'a pas été enregistré :</strong>
            <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- ===================== 1. Informations ===================== --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="text-base font-semibold">Informations</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <label class="{{ $label }}" for="name">Nom du produit *</label>
                <input id="name" name="name" value="{{ old('name', $product->name) }}" required maxlength="150" class="{{ $input }}" placeholder="Ex. : Diri Tchako 50 lb">
            </div>
            <div>
                <label class="{{ $label }}" for="sku">Code interne *</label>
                <input id="sku" name="sku" value="{{ old('sku', $product->sku) }}" required maxlength="50" class="{{ $input }} uppercase" placeholder="DIRI-TCH">
            </div>
            <div>
                <label class="{{ $label }}" for="category_id">Catégorie *</label>
                <select id="category_id" name="category_id" required class="{{ $input }}">
                    <option value="">Choisir…</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id) == $cat->id)>{{ $cat->name }}</option>
                        @foreach ($cat->children as $child)
                            <option value="{{ $child->id }}" @selected(old('category_id', $product->category_id) == $child->id)>&nbsp;&nbsp;↳ {{ $child->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $label }}" for="brand_name">Marque</label>
                <input id="brand_name" name="brand_name" list="brands" value="{{ old('brand_name', $product->brand?->name) }}" class="{{ $input }}" placeholder="Ex. : Tchako">
                <datalist id="brands">@foreach ($brands as $b)<option value="{{ $b }}">@endforeach</datalist>
            </div>
            <div>
                <label class="{{ $label }}" for="barcode">Code-barres (unité de base)</label>
                <div class="mt-1 flex gap-2">
                    <input id="barcode" name="barcode" x-ref="barcode" value="{{ old('barcode', $product->barcode) }}" maxlength="64" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Scanner ou saisir">
                    <button type="button" @click="fillBarcode('barcode')" class="shrink-0 rounded-lg bg-slate-100 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-200" title="Générer un code interne EAN-13">Générer</button>
                </div>
            </div>
            <div class="md:col-span-2">
                <label class="{{ $label }}" for="description">Description</label>
                <textarea id="description" name="description" rows="2" class="{{ $input }}">{{ old('description', $product->description) }}</textarea>
            </div>
            <div>
                <label class="{{ $label }}">Photo</label>
                <div class="mt-1 flex items-center gap-3">
                    @if ($product->image)
                        <img src="{{ asset('storage/'.$product->image) }}" alt="" class="h-14 w-14 rounded-lg object-cover">
                    @endif
                    <input type="file" name="image" accept="image/*" class="block w-full text-xs file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold">
                </div>
                @if ($product->image)
                    <label class="mt-1 flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="remove_image" value="1" class="rounded border-slate-300"> Retirer la photo</label>
                @endif
            </div>
        </div>
    </section>

    {{-- ===================== 2. Unité & prix ===================== --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-base font-semibold">Unité de base et prix</h2>
            @unless ($canEditPrices)
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800 ring-1 ring-amber-200">Prix verrouillés : seul l'administrateur peut les modifier</span>
            @endunless
        </div>
        <p class="mt-1 text-sm text-slate-500">L'unité de base est la plus petite unité vendue (bouteille, livre, marmite…). Le stock est toujours compté dans cette unité.</p>

        <div class="mt-4 grid gap-4 md:grid-cols-4">
            <div>
                <label class="{{ $label }}" for="base_unit_id">Unité de base *</label>
                <select id="base_unit_id" name="base_unit_id" x-model="baseUnitId" required class="{{ $input }}" @disabled($hasMovements)>
                    <option value="">Choisir…</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}" @selected(old('base_unit_id', $product->base_unit_id) == $u->id)>{{ $u->name }} ({{ $u->abbreviation }})</option>
                    @endforeach
                </select>
                @if ($hasMovements)
                    <input type="hidden" name="base_unit_id" value="{{ $product->base_unit_id }}">
                    <p class="mt-1 text-xs text-slate-500">Verrouillée : le produit a déjà du stock.</p>
                @endif
            </div>
            @unless ($product->exists)
                <div>
                    <label class="{{ $label }}" for="cost_price">Prix d'achat <span class="font-normal text-slate-500">/ <span x-text="baseName"></span></span></label>
                    <input id="cost_price" name="cost_price" type="number" step="0.01" min="0" x-model="cost" class="{{ $input }} text-right">
                </div>
            @else
                <div>
                    <span class="{{ $label }}">Coût moyen actuel</span>
                    <div class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-right text-sm tabular-nums text-slate-600">{{ \App\Support\Format::money($product->cost_price) }}</div>
                    <p class="mt-1 text-xs text-slate-500">Calculé à chaque réception.</p>
                </div>
            @endunless
            <div>
                <label class="{{ $label }}" for="retail_price">Prix détail * <span class="font-normal text-slate-500">/ <span x-text="baseName"></span></span></label>
                <input id="retail_price" name="retail_price" type="number" step="0.01" min="0" required x-model="retail" {{ $priceLock }} class="{{ $input }}{{ $priceLockCls }} text-right font-semibold">
                <p class="mt-1 text-xs" :class="marginClass(margin(retail))">Marge : <span x-text="marginLabel(margin(retail))"></span></p>
            </div>
            <div>
                <label class="{{ $label }}" for="wholesale_price">Prix gros <span class="font-normal text-slate-500">/ <span x-text="baseName"></span></span></label>
                <input id="wholesale_price" name="wholesale_price" type="number" step="0.01" min="0" x-model="wholesale" {{ $priceLock }} class="{{ $input }}{{ $priceLockCls }} text-right">
                <p class="mt-1 text-xs" :class="marginClass(margin(wholesale))" x-show="wholesale">Marge : <span x-text="marginLabel(margin(wholesale))"></span></p>
            </div>
            <div class="md:col-start-3">
                <label class="{{ $label }}" for="wholesale_min_qty">Prix gros à partir de</label>
                <div class="mt-1 flex items-center gap-2">
                    <input id="wholesale_min_qty" name="wholesale_min_qty" type="number" step="0.001" min="0" value="{{ old('wholesale_min_qty', $product->wholesale_min_qty !== null ? (float) $product->wholesale_min_qty : '') }}" {{ $priceLock }} class="w-full rounded-lg border-slate-300 text-right text-sm{{ $priceLockCls }}">
                    <span class="text-sm text-slate-500" x-text="baseName"></span>
                </div>
                <p class="mt-1 text-xs text-slate-500">Vide = prix gros seulement pour les clients « gros ».</p>
            </div>
            <div>
                <label class="{{ $label }}" for="tax_rate">TCA (%)</label>
                <input id="tax_rate" name="tax_rate" type="number" step="0.01" min="0" max="100" value="{{ old('tax_rate', (float) ($product->tax_rate ?? 0)) }}" class="{{ $input }} text-right">
            </div>
        </div>
    </section>

    {{-- ===================== 3. Conditionnements ===================== --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold">Conditionnements</h2>
                <p class="mt-1 text-sm text-slate-500">Ex. : 1 <em>caisse</em> = 24 <span x-text="baseName"></span>, 1 <em>sac</em> = 50 livres. Chacun a son prix et son code-barres.</p>
            </div>
            <button type="button" @click="addUnit()" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">+ Ajouter un conditionnement</button>
        </div>

        <div class="mt-4 space-y-3">
            <template x-for="(u, i) in units" :key="u._key">
                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                    <input type="hidden" :name="`units[${i}][id]`" :value="u.id ?? ''">
                    <div class="grid gap-3 md:grid-cols-12">
                        <div class="md:col-span-3">
                            <label class="text-xs font-medium text-slate-600">Unité</label>
                            <select :name="`units[${i}][unit_id]`" x-model="u.unit_id" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <option value="">Choisir…</option>
                                <template x-for="opt in unitsList" :key="opt.id">
                                    <option :value="opt.id" x-text="opt.name" :selected="String(opt.id) === String(u.unit_id)" :disabled="String(opt.id) === String(baseUnitId)"></option>
                                </template>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs font-medium text-slate-600">Contient</label>
                            <div class="mt-1 flex items-center gap-1">
                                <input :name="`units[${i}][conversion_factor]`" x-model="u.conversion_factor" type="number" step="0.001" min="0" required class="w-full rounded-lg border-slate-300 text-right text-sm">
                                <span class="shrink-0 text-xs text-slate-500" x-text="baseName"></span>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs font-medium text-slate-600">Prix détail</label>
                            <input :name="`units[${i}][retail_price]`" x-model="u.retail_price" type="number" step="0.01" min="0" required
                                   @if (! $canEditPrices) :readonly="!!u.id" @endif
                                   class="mt-1 w-full rounded-lg border-slate-300 text-right text-sm font-semibold">
                            <button type="button" @click="suggest(u)" x-show="!u.retail_price && u.conversion_factor" class="mt-1 text-xs text-emerald-700 hover:underline">= détail × contenance</button>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs font-medium text-slate-600">Prix gros</label>
                            <input :name="`units[${i}][wholesale_price]`" x-model="u.wholesale_price" type="number" step="0.01" min="0"
                                   @if (! $canEditPrices) :readonly="!!u.id" @endif
                                   class="mt-1 w-full rounded-lg border-slate-300 text-right text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs font-medium text-slate-600">Gros dès (qté)</label>
                            <input :name="`units[${i}][wholesale_min_qty]`" x-model="u.wholesale_min_qty" type="number" step="0.001" min="0"
                                   @if (! $canEditPrices) :readonly="!!u.id" @endif
                                   class="mt-1 w-full rounded-lg border-slate-300 text-right text-sm">
                        </div>
                        <div class="flex items-end justify-end md:col-span-1">
                            <button type="button" @click="removeUnit(i)" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Retirer">✕</button>
                        </div>

                        <div class="md:col-span-5">
                            <label class="text-xs font-medium text-slate-600">Code-barres du conditionnement</label>
                            <div class="mt-1 flex gap-2">
                                <input :name="`units[${i}][barcode]`" x-model="u.barcode" maxlength="64" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Scanner le code sur la caisse / le sac">
                                <button type="button" @click="fillUnitBarcode(u)" class="shrink-0 rounded-lg bg-white px-3 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100">Générer</button>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-end gap-x-4 gap-y-1 text-xs md:col-span-7">
                            <label class="flex items-center gap-2 py-2 text-slate-700">
                                <input type="checkbox" :name="`units[${i}][is_default_sale_unit]`" value="1" :checked="u.is_default_sale_unit" @change="setDefault(i)" class="rounded border-slate-300">
                                Unité proposée par défaut en caisse
                            </label>
                            <template x-if="perBase(u) !== null">
                                <span class="py-2 text-slate-500">
                                    Soit <strong x-text="money(perBase(u))"></strong> / <span x-text="baseName"></span>
                                    <template x-if="saving(u) > 0"><span class="text-emerald-700"> · le client économise <span x-text="money(saving(u))"></span> par <span x-text="baseName"></span></span></template>
                                    <template x-if="saving(u) < 0"><span class="text-amber-700"> · plus cher qu'au détail !</span></template>
                                    · marge <span :class="marginClass(margin(u.retail_price, u.conversion_factor))" x-text="marginLabel(margin(u.retail_price, u.conversion_factor))"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
            <p x-show="!units.length" class="rounded-xl border border-dashed border-slate-300 py-6 text-center text-sm text-slate-500">
                Aucun conditionnement : le produit se vend uniquement par <span x-text="baseName"></span>.
            </p>
        </div>
    </section>

    {{-- ===================== 4. Stock ===================== --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="text-base font-semibold">Stock et expiration</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-4">
            <div>
                <label class="{{ $label }}" for="min_stock">Stock minimum (alerte) *</label>
                <div class="mt-1 flex items-center gap-2">
                    <input id="min_stock" name="min_stock" type="number" step="0.001" min="0" required value="{{ old('min_stock', (float) $product->min_stock) }}" class="w-full rounded-lg border-slate-300 text-right text-sm">
                    <span class="text-sm text-slate-500" x-text="baseName"></span>
                </div>
            </div>
            <div>
                <label class="{{ $label }}" for="max_stock">Stock maximum</label>
                <input id="max_stock" name="max_stock" type="number" step="0.001" min="0" value="{{ old('max_stock', $product->max_stock !== null ? (float) $product->max_stock : '') }}" class="{{ $input }} text-right">
            </div>
            <div>
                <label class="{{ $label }}" for="location">Emplacement</label>
                <input id="location" name="location" value="{{ old('location', $product->location) }}" maxlength="100" class="{{ $input }}" placeholder="Rangée B · Étagère 3">
            </div>
            <div>
                <label class="{{ $label }}" for="expiry_alert_days">Alerter avant expiration</label>
                <div class="mt-1 flex items-center gap-2">
                    <input id="expiry_alert_days" name="expiry_alert_days" type="number" min="1" max="365" required value="{{ old('expiry_alert_days', $product->expiry_alert_days ?? 30) }}" class="w-full rounded-lg border-slate-300 text-right text-sm">
                    <span class="text-sm text-slate-500">jours</span>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm md:col-span-2">
                <input type="checkbox" name="track_expiry" value="1" x-model="trackExpiry" @checked(old('track_expiry', $product->track_expiry)) class="rounded border-slate-300">
                Ce produit a une date d'expiration (gestion par lots)
            </label>
            <label class="flex items-center gap-2 text-sm md:col-span-2">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true)) class="rounded border-slate-300">
                Actif (visible en caisse)
            </label>
        </div>

        @unless ($product->exists)
            <div class="mt-6 rounded-xl bg-emerald-50/60 p-4 ring-1 ring-emerald-100">
                <h3 class="text-sm font-semibold text-emerald-900">Stock initial <span class="font-normal text-emerald-800">(facultatif : marchandise déjà au dépôt)</span></h3>
                <div class="mt-3 grid gap-4 md:grid-cols-4">
                    <div>
                        <label class="{{ $label }}" for="opening_quantity">Quantité</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input id="opening_quantity" name="opening_quantity" type="number" step="0.001" min="0" x-model="openingQty" value="{{ old('opening_quantity') }}" class="w-full rounded-lg border-slate-300 text-right text-sm">
                            <span class="text-sm text-slate-500" x-text="baseName"></span>
                        </div>
                    </div>
                    <div>
                        <label class="{{ $label }}" for="opening_unit_cost">Coût unitaire</label>
                        <input id="opening_unit_cost" name="opening_unit_cost" type="number" step="0.01" min="0" x-model="openingCost" value="{{ old('opening_unit_cost') }}" class="{{ $input }} text-right" :placeholder="cost || '0'">
                    </div>
                    <div x-show="trackExpiry">
                        <label class="{{ $label }}" for="opening_expiry">Date d'expiration</label>
                        <input id="opening_expiry" name="opening_expiry" type="date" value="{{ old('opening_expiry') }}" min="{{ today()->addDay()->toDateString() }}" class="{{ $input }}" :required="trackExpiry && Number(openingQty) > 0">
                    </div>
                    <div>
                        <label class="{{ $label }}" for="opening_batch">N° de lot</label>
                        <input id="opening_batch" name="opening_batch" value="{{ old('opening_batch') }}" maxlength="50" class="{{ $input }}">
                    </div>
                </div>
                <p class="mt-2 text-xs text-emerald-900/70">Plusieurs lots avec des dates différentes ? Saisissez le premier ici, les autres via une réception.</p>
            </div>
        @endunless
    </section>

    {{-- ===================== Actions ===================== --}}
    <div class="sticky bottom-0 -mx-4 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6">
        <a href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Annuler</a>
        @unless ($product->exists)
            <button name="add_another" value="1" class="rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50">Enregistrer et ajouter un autre</button>
        @endunless
        <button class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Enregistrer</button>
    </div>
</form>
@endsection
