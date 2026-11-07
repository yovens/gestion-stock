/**
 * Composant Alpine de la caisse.
 * Le serveur (SaleService) reste l'autorité pour les prix, le stock et les totaux ;
 * ce composant ne fait que les calculs d'affichage.
 *
 * Raccourcis : F2 recherche · F4 encaisser · F8 mettre en attente · Échap fermer
 */
const round2 = (v) => Math.round((Number(v) + Number.EPSILON) * 100) / 100;
const round3 = (v) => Math.round((Number(v) + Number.EPSILON) * 1000) / 1000;
const fmt = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtQty = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 });

export default (config) => ({
    config,
    query: '',
    category: null,
    results: [],
    loading: false,
    searchTimer: null,

    cart: [],
    customer: null,
    saleType: 'retail',
    discount: 0,
    notes: '',

    customerQuery: '',
    customerResults: [],
    showCustomer: false,
    customerTimer: null,

    showPay: false,
    payments: [],
    submitting: false,

    showHeld: false,
    heldList: [],
    heldCount: config.heldCount,

    toast: null,
    toastTimer: null,
    lastSale: null,

    init() {
        this.fetchProducts();
        window.addEventListener('keydown', (e) => this.hotkey(e));
        this.$nextTick(() => this.focusSearch());
    },

    // ---------------------------------------------------------------- utils
    money(v) { return fmt.format(Number(v) || 0) + ' HTG'; },
    usd(v) { return '$' + fmt.format(Number(v) || 0); },
    qty(v) { return fmtQty.format(Number(v) || 0); },

    notify(msg, type = 'error') {
        this.toast = { msg, type };
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => (this.toast = null), 4000);
    },

    focusSearch() {
        this.$refs.search?.focus();
        this.$refs.search?.select();
    },

    hotkey(e) {
        if (e.key === 'F2') { e.preventDefault(); this.focusSearch(); }
        else if (e.key === 'F4') { e.preventDefault(); this.showPay ? this.submit() : this.openPay(); }
        else if (e.key === 'F8') { e.preventDefault(); this.hold(); }
        else if (e.key === 'Escape') {
            this.showPay = this.showHeld = this.showCustomer = false;
            this.focusSearch();
        }
    },

    async api(url, options = {}) {
        const res = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                ...(options.headers || {}),
            },
        });
        const data = await res.json().catch(() => ({}));
        if (res.status === 419 || res.status === 401) {
            throw new Error('Session expirée. Rechargez la page et reconnectez-vous.');
        }
        if (!res.ok) {
            const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
            throw new Error(first || data.message || `Erreur ${res.status}`);
        }
        return data;
    },

    // --------------------------------------------------------- produits
    onSearchInput() {
        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => this.fetchProducts(), 250);
    },

    /** Entrée (ou fin de scan) : code-barres exact => ajout direct au panier. */
    async onSearchEnter() {
        clearTimeout(this.searchTimer);
        const data = await this.fetchProducts();
        if (!data) return;
        if (data.exact) {
            this.add(data.exact, data.unit_id);
            this.query = '';
            this.fetchProducts();
        } else if (this.results.length === 1) {
            this.add(this.results[0]);
            this.query = '';
            this.fetchProducts();
        } else if (this.results.length === 0) {
            this.notify(`Aucun produit pour « ${this.query} »`);
            this.focusSearch();
        }
    },

    setCategory(id) {
        this.category = this.category === id ? null : id;
        this.fetchProducts();
    },

    async fetchProducts() {
        this.loading = true;
        try {
            const params = new URLSearchParams();
            if (this.query.trim()) params.set('q', this.query.trim());
            if (this.category) params.set('category', this.category);
            const data = await this.api(`${this.config.urls.products}?${params}`);
            this.results = data.exact ? [data.exact] : data.results;
            return data;
        } catch (e) {
            this.notify(e.message);
            return null;
        } finally {
            this.loading = false;
        }
    },

    // ------------------------------------------------------------ panier
    unitOf(line) {
        return line.unitId ? line.product.units.find((u) => u.id === line.unitId) : null;
    },
    pricing(line) { return this.unitOf(line) ?? line.product.base; },
    factor(line) { return this.unitOf(line)?.factor ?? 1; },
    unitName(line) { return this.unitOf(line)?.name ?? line.product.base.name; },
    allowsDecimal(line) { return this.pricing(line).decimal; },

    price(line) {
        const p = this.pricing(line);
        const wholesale = p.wholesale !== null && p.wholesale > 0
            && (this.saleType === 'wholesale' || (p.min !== null && line.quantity >= p.min));
        return wholesale ? p.wholesale : p.retail;
    },
    isWholesale(line) { return this.price(line) !== this.pricing(line).retail; },
    lineGross(line) { return round2(this.price(line) * line.quantity); },
    lineTotal(line) { return round2(this.lineGross(line) - (Number(line.discount) || 0)); },

    baseInCart(productId) {
        return round3(this.cart
            .filter((l) => l.product.id === productId)
            .reduce((s, l) => s + l.quantity * this.factor(l), 0));
    },
    overStock(line) { return this.baseInCart(line.product.id) > line.product.stock + 0.0005; },

    add(product, unitId = null) {
        const unit = unitId
            ? product.units.find((u) => u.id === unitId)
            : product.units.find((u) => u.default) ?? null;
        const key = `${product.id}-${unit?.id ?? 'b'}`;
        const existing = this.cart.find((l) => l.key === key);

        if (existing) {
            existing.product = product; // stock à jour
            existing.quantity = round3(existing.quantity + 1);
        } else {
            this.cart.unshift({ key, product, unitId: unit?.id ?? null, quantity: 1, discount: 0 });
        }

        const line = existing ?? this.cart[0];
        if (this.overStock(line)) {
            this.notify(`Stock insuffisant : ${this.qty(product.stock)} ${product.base.abbr} disponible(s) pour ${product.name}`);
        }
        this.focusSearch();
    },

    setQty(line, value) {
        let q = Number(String(value).replace(',', '.'));
        if (!Number.isFinite(q) || q <= 0) return this.remove(line);
        if (!this.allowsDecimal(line)) q = Math.floor(q) || 1;
        line.quantity = round3(q);
    },
    inc(line, step) { this.setQty(line, round3(line.quantity + step)); },

    changeUnit(line, unitId) {
        const id = unitId === '' || unitId === null ? null : Number(unitId);
        const key = `${line.product.id}-${id ?? 'b'}`;
        if (this.cart.some((l) => l !== line && l.key === key)) {
            this.notify('Ce conditionnement est déjà dans le panier.');
            return;
        }
        line.unitId = id;
        line.key = key;
        if (!this.allowsDecimal(line)) line.quantity = Math.max(1, Math.floor(line.quantity));
    },

    setLineDiscount(line, value) {
        const d = round2(Math.max(0, Number(value) || 0));
        const gross = this.lineGross(line);
        if (!this.config.freeDiscount && gross > 0 && (d / gross) * 100 > this.config.maxDiscount) {
            this.notify(`Remise maximale : ${this.config.maxDiscount} %`);
            line.discount = round2((gross * this.config.maxDiscount) / 100);
            return;
        }
        line.discount = Math.min(d, gross);
    },

    setGlobalDiscount(value) {
        const d = round2(Math.max(0, Number(value) || 0));
        const base = round2(this.gross - this.lineDiscounts);
        if (!this.config.freeDiscount && base > 0 && (d / base) * 100 > this.config.maxDiscount) {
            this.notify(`Remise maximale : ${this.config.maxDiscount} %`);
            this.discount = round2((base * this.config.maxDiscount) / 100);
            return;
        }
        this.discount = Math.min(d, base);
    },

    remove(line) {
        this.cart = this.cart.filter((l) => l !== line);
        this.focusSearch();
    },

    clearCart() {
        if (this.cart.length && !confirm('Vider le panier ?')) return;
        this.reset();
    },

    get gross() { return round2(this.cart.reduce((s, l) => s + this.lineGross(l), 0)); },
    get lineDiscounts() { return round2(this.cart.reduce((s, l) => s + (Number(l.discount) || 0), 0)); },
    get total() { return Math.max(0, round2(this.gross - this.lineDiscounts - (Number(this.discount) || 0))); },
    get itemsCount() { return this.cart.length; },
    get hasStockProblem() { return this.cart.some((l) => this.overStock(l)); },

    // ------------------------------------------------------------ clients
    onCustomerInput() {
        clearTimeout(this.customerTimer);
        this.customerTimer = setTimeout(() => this.fetchCustomers(), 250);
    },
    async fetchCustomers() {
        try {
            this.customerResults = await this.api(`${this.config.urls.customers}?q=${encodeURIComponent(this.customerQuery)}`);
        } catch (e) { this.notify(e.message); }
    },
    openCustomer() {
        this.showCustomer = true;
        this.customerQuery = '';
        this.fetchCustomers();
        this.$nextTick(() => this.$refs.customerSearch?.focus());
    },
    selectCustomer(c) {
        this.customer = c;
        this.saleType = c?.type === 'wholesale' ? 'wholesale' : 'retail';
        this.showCustomer = false;
        this.focusSearch();
    },

    // ----------------------------------------------------------- paiement
    openPay() {
        if (!this.cart.length) return this.notify('Le panier est vide.');
        if (this.hasStockProblem) return this.notify('Corrigez les quantités en rouge (stock insuffisant).');
        this.payments = [{ method: 'cash', currency: 'HTG', amount: this.total, reference: '' }];
        this.showPay = true;
        this.$nextTick(() => this.$root.querySelector('[data-pay-amount]')?.select());
    },
    addPayment() {
        const rest = Math.max(0, this.due);
        this.payments.push({ method: 'moncash', currency: 'HTG', amount: rest, reference: '' });
    },
    removePayment(i) { this.payments.splice(i, 1); },

    toHtg(p) { return round2((Number(p.amount) || 0) * (p.currency === 'USD' ? this.config.rate : 1)); },
    get paid() { return round2(this.payments.reduce((s, p) => s + this.toHtg(p), 0)); },
    get cashPaid() { return round2(this.payments.filter((p) => p.method === 'cash').reduce((s, p) => s + this.toHtg(p), 0)); },
    get change() { return Math.max(0, round2(this.paid - this.total)); },
    get due() { return Math.max(0, round2(this.total - this.paid)); },
    get changeProblem() { return this.change > this.cashPaid + 0.001; },
    get creditProblem() {
        if (this.due <= 0) return null;
        if (!this.customer) return 'Paiement insuffisant — choisissez un client pour vendre à crédit.';
        if (!this.config.canCredit) return "Vous n'êtes pas autorisé à vendre à crédit.";
        if (this.due > this.customer.available_credit + 0.005) {
            return `Crédit disponible pour ce client : ${this.money(this.customer.available_credit)}`;
        }
        return null;
    },
    get canSubmit() { return !this.submitting && !this.changeProblem && !this.creditProblem; },

    quickAmounts() {
        const t = this.total;
        const set = new Set([t]);
        [50, 100, 500, 1000].forEach((step) => set.add(Math.ceil(t / step) * step));
        [1000, 2000, 5000].forEach((v) => v > t && set.add(v));
        return [...set].filter((v) => v >= t).sort((a, b) => a - b).slice(0, 6);
    },
    setCash(v) {
        const row = this.payments.find((p) => p.method === 'cash' && p.currency === 'HTG');
        if (row) row.amount = v; else this.payments.unshift({ method: 'cash', currency: 'HTG', amount: v, reference: '' });
    },

    payload(hold) {
        return {
            customer_id: this.customer?.id ?? null,
            sale_type: this.saleType,
            hold,
            discount_amount: Number(this.discount) || 0,
            notes: this.notes || null,
            items: this.cart.map((l) => ({
                product_id: l.product.id,
                product_unit_id: l.unitId,
                quantity: l.quantity,
                discount: Number(l.discount) || 0,
            })),
            payments: hold ? [] : this.payments
                .filter((p) => Number(p.amount) > 0)
                .map((p) => ({ method: p.method, currency: p.currency, amount: Number(p.amount), reference: p.reference || null })),
        };
    },

    async submit(hold = false) {
        if (this.submitting) return;
        if (!hold && !this.canSubmit) return this.notify(this.creditProblem || 'La monnaie ne peut être rendue que sur un paiement en espèces.');
        this.submitting = true;
        try {
            const sale = await this.api(this.config.urls.store, { method: 'POST', body: JSON.stringify(this.payload(hold)) });
            if (hold) {
                this.heldCount++;
                this.notify(`Vente ${sale.reference} mise en attente`, 'success');
            } else {
                this.lastSale = sale;
                this.notify(`Vente ${sale.reference} enregistrée${sale.change > 0 ? ' — Monnaie : ' + this.money(sale.change) : ''}`, 'success');
                this.printReceipt(sale);
            }
            this.reset();
            this.fetchProducts();
        } catch (e) {
            this.notify(e.message);
        } finally {
            this.submitting = false;
        }
    },

    hold() {
        if (!this.cart.length) return this.notify('Le panier est vide.');
        this.submit(true);
    },

    printReceipt(sale) {
        if (!sale?.receipt_url) return;
        window.open(`${sale.receipt_url}?print=1`, 'recu', 'width=420,height=700');
    },

    reset() {
        this.cart = [];
        this.customer = null;
        this.saleType = 'retail';
        this.discount = 0;
        this.notes = '';
        this.payments = [];
        this.showPay = false;
        this.$nextTick(() => this.focusSearch());
    },

    // ------------------------------------------------------- en attente
    async openHeld() {
        try {
            this.heldList = await this.api(this.config.urls.held);
            this.heldCount = this.heldList.length;
            this.showHeld = true;
        } catch (e) { this.notify(e.message); }
    },
    async resume(h) {
        if (this.cart.length && !confirm('Le panier actuel sera remplacé. Continuer ?')) return;
        try {
            const data = await this.api(this.config.urls.resume.replace('__ID__', h.id), { method: 'POST' });
            this.cart = data.lines.map((l) => ({
                key: `${l.product.id}-${l.unit_id ?? 'b'}`,
                product: l.product,
                unitId: l.unit_id,
                quantity: l.quantity,
                discount: l.discount,
            }));
            this.customer = data.customer;
            this.saleType = data.sale_type;
            this.discount = data.discount;
            this.notes = data.notes ?? '';
            this.heldCount = Math.max(0, this.heldCount - 1);
            this.showHeld = false;
            this.focusSearch();
        } catch (e) { this.notify(e.message); }
    },
    async deleteHeld(h) {
        if (!confirm(`Supprimer la vente en attente ${h.reference} ?`)) return;
        try {
            await this.api(this.config.urls.heldDestroy.replace('__ID__', h.id), { method: 'DELETE' });
            this.heldList = this.heldList.filter((x) => x.id !== h.id);
            this.heldCount = this.heldList.length;
        } catch (e) { this.notify(e.message); }
    },
});
