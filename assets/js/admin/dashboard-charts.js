/**
 * Admin dashboard charts (Chart.js). Data comes from <script type="application/json" id="dashboard-chart-data">;
 * charts are redrawn when the colour scheme changes.
 */
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

function css(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

function palette() {
    const primary = css('--color-primary-600', '#2563eb');
    return {
        text: css('--color-text-secondary', '#52525b'),
        grid: css('--ui-line', '#e4e4e7'),
        primary,
        primarySoft: `color-mix(in srgb, ${primary} 15%, transparent)`,
        series: [primary, '#06b6d4', '#16a34a', '#f59e0b', '#ef4444', '#8b5cf6', '#a1a1aa'],
    };
}

export function init() {
    const source = document.getElementById('dashboard-chart-data');
    if (!source) {
        return;
    }

    const data = JSON.parse(source.textContent || '{}');
    const labels = JSON.parse(source.dataset.labels || '{}');
    const locale = document.documentElement.lang || 'en';
    const money = new Intl.NumberFormat(locale, { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
    let charts = [];

    const build = () => {
        charts.forEach((chart) => chart.destroy());
        charts = [];

        const colors = palette();
        Chart.defaults.color = colors.text;
        Chart.defaults.borderColor = colors.grid;
        Chart.defaults.font.family = 'Inter, system-ui, sans-serif';

        const revenue = document.getElementById('revenue-chart');
        if (revenue && data.revenue) {
            charts.push(new Chart(revenue, {
                type: 'line',
                data: {
                    labels: data.revenue.labels,
                    datasets: [{
                        label: labels.revenue,
                        data: data.revenue.revenue,
                        borderColor: colors.primary,
                        backgroundColor: colors.primarySoft,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        borderWidth: 2,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false } },
                    scales: { y: { ticks: { callback: (value) => money.format(value) } }, x: { grid: { display: false } } },
                },
            }));
        }

        const status = document.getElementById('status-chart');
        if (status && data.status) {
            charts.push(new Chart(status, {
                type: 'doughnut',
                data: { labels: data.status.labels, datasets: [{ data: data.status.values, backgroundColor: colors.series, borderWidth: 0 }] },
                options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom' } } },
            }));
        }

        const top = document.getElementById('top-products-chart');
        if (top && Array.isArray(data.topProducts)) {
            charts.push(new Chart(top, {
                type: 'bar',
                data: {
                    labels: data.topProducts.map((p) => p.name),
                    datasets: [{
                        label: labels.topProducts,
                        data: data.topProducts.map((p) => p.revenue / 100),
                        backgroundColor: colors.primary,
                        borderRadius: 6,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, ticks: { callback: (value) => money.format(value) } }, y: { grid: { display: false } } },
                },
            }));
        }
    };

    build();
    new MutationObserver(build).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
}
