/**
 * Formulaire produit : conditionnements (caisse, sac...), calcul de marge en direct,
 * génération de code-barres interne EAN-13.
 */
const round2 = (v) => Math.round((Number(v) + Number.EPSILON) * 100) / 100;
const fmt = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** EAN-13 avec préfixe 20-29 (réservé à l'usage interne d'un magasin) et clé de contrôle. */
export function generateEan13(prefix = '20') {
    let digits = prefix;
    while (digits.length < 12) digits += Math.floor(Math.random() * 10);
    const sum = [...digits].reduce((s, d, i) => s + Number(d) * (i % 2 === 0 ? 1 : 3), 0);
    return digits + ((10 - (sum % 10)) % 10);
}

export default (init) => ({
    cost: init.cost || '',
    retail: init.retail || '',
    wholesale: init.wholesale ?? '',
    baseUnitId: init.baseUnitId ? String(init.baseUnitId) : '',
    trackExpiry: init.trackExpiry,
    unitsList: init.unitsList,
    units: init.units.map((u, i) => ({ ...u, _key: `u${i}-${Date.now()}` })),
    openingQty: init.openingQty ?? '',
    openingCost: init.openingCost ?? '',

    money(v) { return `${fmt.format(Number(v) || 0)} HTG`; },

    unitName(id) { return this.unitsList.find((u) => String(u.id) === String(id))?.name ?? ''; },
    get baseName() { return this.unitName(this.baseUnitId) || 'unité de base'; },
    get baseAllowsDecimal() { return !!this.unitsList.find((u) => String(u.id) === String(this.baseUnitId))?.allows_decimal; },

    /** Coût de référence : celui du stock initial s'il est saisi. */
    get refCost() { return Number(this.openingCost) || Number(this.cost) || 0; },

    margin(price, factor = 1) {
        const c = this.refCost * (Number(factor) || 1);
        const p = Number(price);
        if (!c || !p) return null;
        return round2(((p - c) / c) * 100);
    },
    marginClass(m) {
        if (m === null) return 'text-slate-400';
        if (m < 0) return 'text-rose-600 font-semibold';
        if (m < 5) return 'text-amber-600';
        return 'text-emerald-700';
    },
    marginLabel(m) { return m === null ? '—' : `${m > 0 ? '+' : ''}${fmt.format(m)} %`; },

    addUnit() {
        this.units.push({
            _key: `u${Date.now()}`, id: null, unit_id: '', conversion_factor: '', barcode: '',
            retail_price: '', wholesale_price: null, wholesale_min_qty: null,
            is_default_sale_unit: false,
        });
    },
    removeUnit(i) { this.units.splice(i, 1); },
    setDefault(i) { this.units.forEach((u, j) => (u.is_default_sale_unit = i === j ? !u.is_default_sale_unit : false)); },

    /** Prix « détail × contenance » comme point de départ. */
    suggest(u) {
        if (Number(this.retail) && Number(u.conversion_factor)) {
            u.retail_price = round2(Number(this.retail) * Number(u.conversion_factor));
        }
    },
    perBase(u) {
        const f = Number(u.conversion_factor);
        return f > 0 && Number(u.retail_price) ? round2(Number(u.retail_price) / f) : null;
    },
    saving(u) {
        const pb = this.perBase(u);
        return pb !== null && Number(this.retail) ? round2(Number(this.retail) - pb) : null;
    },

    fillBarcode(field) { this.$refs[field].value = generateEan13(); },
    fillUnitBarcode(u) { u.barcode = generateEan13(); },
});
