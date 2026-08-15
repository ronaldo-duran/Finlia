/*
|----------------------------------------------------------------------
| Finlia — Gráficos del dashboard (Chart.js)
|----------------------------------------------------------------------
| Entrada Vite independiente (cargada solo en el dashboard). Lee los
| datos desde un <script type="application/json" id="finlia-chart-data">
| inyectado por el servidor y dibuja:
|   - #trendChart  : barras ingresos vs gastos (6 meses)
|   - #categoryChart : doughnut de gastos por categoría (mes)
|
| Solo se ejecutan los gráficos cuyos <canvas> existen en la página.
*/

import Chart from 'chart.js/auto';

const source = document.getElementById('finlia-chart-data');
const data = source ? JSON.parse(source.textContent) : null;

// Paleta consistente con la marca (CSS --finlia-primary) y derivados.
const INCOME_COLOR = '#0f766e';
const EXPENSE_COLOR = '#e35d6a';

const currency = (value) =>
    '$ ' + Number(value).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

if (data) {
    const trendEl = document.getElementById('trendChart');
    if (trendEl) {
        new Chart(trendEl, {
            type: 'bar',
            data: {
                labels: data.trend.labels,
                datasets: [
                    { label: 'Ingresos', data: data.trend.incomes, backgroundColor: INCOME_COLOR, borderRadius: 6 },
                    { label: 'Gastos', data: data.trend.expenses, backgroundColor: EXPENSE_COLOR, borderRadius: 6 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true } },
                    tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${currency(ctx.parsed.y)}` } },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: (v) => currency(v) } },
                },
            },
        });
    }

    const categoryEl = document.getElementById('categoryChart');
    if (categoryEl && data.expensesByCategory.amounts.length) {
        new Chart(categoryEl, {
            type: 'doughnut',
            data: {
                labels: data.expensesByCategory.labels,
                datasets: [{
                    data: data.expensesByCategory.amounts,
                    backgroundColor: data.expensesByCategory.colors,
                    borderWidth: 2,
                    borderColor: 'rgba(255,255,255,0.6)',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10 } },
                    tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${currency(ctx.parsed)}` } },
                },
            },
        });
    }
}
