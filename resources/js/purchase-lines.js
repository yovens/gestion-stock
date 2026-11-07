/**
 * Lignes d'un bon de commande ou d'une réception.
 * mode 'order'   : produit, conditionnement, quantité, prix d'achat
 * mode 'receipt' : + n° de lot, fabrication, expiration (obligatoire si le produit gère les lots), transport réparti
 */
const round2 = (v) => Math.round((Number(v) + Number.EPSILON) * 100) / 100;
const fmt = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtQ = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 });
let seq = 0;

export default (init) => ({
    mode: init.mode,
    url: init.productsUrl,
    lines: (init.lines || []).map((l) => ({
        _key: ++seq,
        product: l.product,
        product_unit_id: l.product_unit_id ?? null,
        quantity: l.quantity,
        unit_cost: l.unit_cost,
        batch_number: l.batch_number ?? '',
        manufacture_date: l.manufacture_date ?? '',
        expiry_date: l.expiry_date ?? '',
    })),
    transport: init.transport ?? '',
    paid: init.paid ?? '',
    query: '',
    results: [],
    open: false,
    active: 0,
    timer: null,

    money(v) { return `${fmt.format(Number(v) || 0)} HTG`; },
    qty(v) { return fmtQ.format(Number(v) || 0); },

    // ----------------------------------------------------------- recherche
    onInput() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.search(), 200);
    },
    async search() {
        const q = this.query.trim();
        if (!q) { this.results = []; this.open = false; return; }
        const res = await fetch(`${this.url}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
        this.results = res.ok ? await res.json() : [];
        this.active = 0;
        this.open = true;
    },
    async onEnter() {
        clearTimeout(this.timer);
        await this.search();
        if (this.results[this.active]) this.add(this.results[this.active]);
    },
    move(step) {
        if (!this.results.length) return;
        this.active = (this.active + step + this.results.length) % this.results.length;
    },

    add(p) {
        const unit = p.units.find((u) => u.purchase_default) ?? [...p.units].sort((a, b) => b.factor - a.factor)[0] ?? null;
        const factor = unit?.factor ?? 1;
        const baseCost = p.last_cost ?? p.cost ?? 0;
        this.lines.push({
            _key: ++seq, product: p, product_unit_id: unit?.id ?? null, quantity: 1,
            unit_cost: round2(baseCost * factor), batch_number: '', manufacture_date: '', expiry_date: '',
        });
        this.query = '';
        this.results = [];
        this.open = false;
        this.$nextTick(() => {
            const inputs = this.$root.querySelectorAll('[data-qty]');
            inputs[inputs.length - 1]?.select();
        });
    },
    remove(i) { this.lines.splice(i, 1); },

    // -------------------------------------------------------------- calculs
    unitOf(l) { return l.product_unit_id ? l.product.units.find((u) => u.id === Number(l.product_unit_id)) : null; },
    factor(l) { return this.unitOf(l)?.factor ?? 1; },
    unitLabel(l) { return this.unitOf(l)?.name ?? l.product.base.name; },

    changeUnit(l, value) {
        const oldF = this.factor(l);
        l.product_unit_id = value === '' ? null : Number(value);
        const newF = this.factor(l);
        if (Number(l.unit_cost)) l.unit_cost = round2((Number(l.unit_cost) / oldF) * newF);
    },

    lineTotal(l) { return round2((Number(l.quantity) || 0) * (Number(l.unit_cost) || 0)); },
    baseQty(l) { return (Number(l.quantity) || 0) * this.factor(l); },
    get subtotal() { return round2(this.lines.reduce((s, l) => s + this.lineTotal(l), 0)); },
    get total() { return round2(this.subtotal + (Number(this.transport) || 0)); },
    get due() { return Math.max(0, round2(this.total - (Number(this.paid) || 0))); },

    /** Coût de revient par unité de base, transport inclus (même calcul que le serveur). */
    landedCost(l) {
        const base = this.baseQty(l);
        if (!base) return null;
        const share = this.subtotal > 0 ? ((Number(this.transport) || 0) * this.lineTotal(l)) / this.subtotal : 0;
        return round2((this.lineTotal(l) + share) / base);
    },
    costChange(l) {
        const c = this.landedCost(l);
        const ref = l.product.cost;
        if (c === null || !ref) return null;
        return round2(((c - ref) / ref) * 100);
    },

    expiryDays(l) {
        if (!l.expiry_date) return null;
        return Math.round((new Date(l.expiry_date) - new Date(new Date().toDateString())) / 86400000);
    },
    get missingExpiry() {
        return this.mode === 'receipt' && this.lines.some((l) => l.product.track_expiry && !l.expiry_date);
    },
});
