/** Lignes d'un ajustement de stock (perte, casse, expiré, correction). */
let seq = 0;

export default (init) => ({
    url: init.url,
    lines: (init.lines || []).map((l) => ({ _key: ++seq, ...l, batch_id: l.batch_id ?? '' })),
    query: '',
    results: [],
    open: false,
    timer: null,

    onInput() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.search(), 200);
    },
    async search() {
        const q = this.query.trim();
        if (!q) { this.results = []; this.open = false; return; }
        const res = await fetch(`${this.url}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
        this.results = res.ok ? await res.json() : [];
        this.open = true;
    },
    async onEnter() {
        clearTimeout(this.timer);
        await this.search();
        if (this.results.length) this.add(this.results[0]);
    },
    add(p) {
        // Par défaut : le lot expiré s'il y en a un, sinon le premier à sortir
        const expired = p.batches.find((b) => b.expired);
        this.lines.push({ _key: ++seq, product: p, batch_id: expired?.id ?? '', direction: 'out', quantity: '', reason: '' });
        this.query = '';
        this.results = [];
        this.open = false;
    },
    remove(i) { this.lines.splice(i, 1); },
    batchOf(l) { return l.product.batches.find((b) => b.id === Number(l.batch_id)); },
    maxOut(l) { return l.batch_id ? this.batchOf(l)?.remaining ?? 0 : l.product.stock; },
    tooMuch(l) { return l.direction === 'out' && Number(l.quantity) > this.maxOut(l) + 0.0005; },
    value(l) { return (Number(l.quantity) || 0) * l.product.cost * (l.direction === 'out' ? -1 : 1); },
    get total() { return this.lines.reduce((s, l) => s + this.value(l), 0); },
    money(v) { return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v) + ' HTG'; },
});
