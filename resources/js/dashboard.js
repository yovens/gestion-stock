/**
 * Graphiques du tableau de bord (Chart.js, compilé localement : marche hors ligne).
 * Palette validée (daltonisme + contraste) : ventes = bleu, bénéfice = orange.
 * Un seul axe Y par graphique ; une légende dès qu'il y a 2 séries ; tableau de données sous chaque graphique.
 */
import Chart from 'chart.js/auto';

const C = {
    series1: '#2a78d6', // ventes
    series2: '#eb6834', // bénéfice brut
    text: '#52514e',
    muted: '#8a8984',
    grid: '#ecebe8',
    surface: '#ffffff',
};

const nf = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
const compact = (v) => {
    const a = Math.abs(v);
    if (a >= 1e6) return `${nf.format(v / 1e6)} M`;
    if (a >= 1e3) return `${nf.format(v / 1e3)} k`;
    return nf.format(v);
};

function base(horizontal = false, legend = false) {
    const valueAxis = {
        beginAtZero: true,
        grid: { color: C.grid, drawTicks: false },
        border: { display: false },
        ticks: { color: C.muted, padding: 8, maxTicksLimit: 6, callback: (v) => compact(v) },
    };
    const catAxis = {
        grid: { display: false },
        border: { color: C.grid },
        ticks: { color: C.text, autoSkipPadding: 12, maxRotation: 0 },
    };
    return {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: horizontal ? 'y' : 'x',
        animation: { duration: 300 },
        interaction: { mode: 'index', intersect: false, axis: horizontal ? 'y' : 'x' },
        layout: { padding: { right: horizontal ? 8 : 0 } },
        scales: horizontal ? { x: valueAxis, y: catAxis } : { x: catAxis, y: valueAxis },
        plugins: {
            legend: {
                display: legend,
                position: 'top',
                align: 'start',
                labels: { color: C.text, boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 2, padding: 16 },
            },
            tooltip: {
                backgroundColor: '#0f172a',
                padding: 10,
                cornerRadius: 8,
                boxPadding: 4,
                callbacks: {
                    label: (ctx) => {
                        const v = horizontal ? ctx.parsed.x : ctx.parsed.y;
                        return ` ${ctx.dataset.label} : ${nf.format(v)} HTG`;
                    },
                },
            },
        },
    };
}

const bar = (label, data, color) => ({
    label,
    data,
    backgroundColor: color,
    hoverBackgroundColor: color,
    borderRadius: 4,
    borderSkipped: 'start', // coins arrondis côté valeur, base droite
    borderWidth: 0,
    maxBarThickness: 18,
    categoryPercentage: 0.72,
    barPercentage: 0.88, // espace entre barres voisines
});

export default function initDashboard() {
    const el = document.getElementById('dashboard-data');
    if (!el) return;
    const d = JSON.parse(el.textContent);

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;

    const daily = document.getElementById('chart-daily');
    if (daily) {
        const datasets = [bar('Ventes', d.daily.revenue, C.series1)];
        if (Array.isArray(d.daily.profit)) datasets.push(bar('Bénéfice brut', d.daily.profit, C.series2));
        new Chart(daily, {
            type: 'bar',
            data: { labels: d.daily.labels, datasets },
            options: base(false, datasets.length > 1),
        });
    }

    for (const [id, key, label] of [['chart-category', 'byCategory', 'Ventes'], ['chart-method', 'byMethod', 'Encaissé']]) {
        const canvas = document.getElementById(id);
        if (!canvas || !d[key].values.length) continue;
        new Chart(canvas, {
            type: 'bar',
            data: { labels: d[key].labels, datasets: [bar(label, d[key].values, C.series1)] },
            options: base(true, false),
        });
    }
}
