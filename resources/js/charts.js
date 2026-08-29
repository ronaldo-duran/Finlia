/*
|----------------------------------------------------------------------
| Finlia — Gráficos (Chart.js)
|----------------------------------------------------------------------
| Entrada Vite independiente (cargada solo en pantallas con gráficos).
| Lee los datos desde un <script type="application/json"
| id="finlia-chart-data"> inyectado por el servidor y dibuja:
|
|  Panel (/dashboard):
|   - #trendChart    : barras ingresos vs gastos (6 meses)
|   - #categoryChart : doughnut de gastos por categoría (mes)
|
|  Reportes (/reportes, Épica 8):
|   - #reportTrendChart   : barras ingresos vs gastos por mes del período
|   - #reportCategoryChart: doughnut de gastos por categoría del período
|   - #reportBalanceChart : línea del balance mensual
|   - #reportDebtChart    : línea del saldo de deuda a cierre de mes
|   - #reportGoalsChart   : barras horizontales del progreso de metas
|
| Solo se ejecutan los gráficos cuyos <canvas> existen en la página.
*/

import Chart from 'chart.js/auto';

const source = document.getElementById('finlia-chart-data');
const data = source ? JSON.parse(source.textContent) : null;

// Paleta consistente con la marca (CSS --finlia-primary) y derivados.
const INCOME_COLOR = '#0b3f44';
const EXPENSE_COLOR = '#e35d6a';
const DEBT_COLOR = '#b4544e';       // --finlia-danger
const SAVED_COLOR = '#2f7d5f';      // --finlia-success
const REMAINING_COLOR = 'rgba(11, 63, 68, 0.12)';

const currency = (value) =>
    '$ ' + Number(value).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

const legendBottom = { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10 } };

if (data) {
    // ============ Panel ============

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
                    legend: legendBottom,
                    tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${currency(ctx.parsed)}` } },
                },
            },
        });
    }

    // ============ Reportes (Épica 8) ============

    const reportTrendEl = document.getElementById('reportTrendChart');
    if (reportTrendEl && data.reportTrend) {
        new Chart(reportTrendEl, {
            type: 'bar',
            data: {
                labels: data.reportTrend.labels,
                datasets: [
                    { label: 'Ingresos', data: data.reportTrend.incomes, backgroundColor: INCOME_COLOR, borderRadius: 6 },
                    { label: 'Gastos', data: data.reportTrend.expenses, backgroundColor: EXPENSE_COLOR, borderRadius: 6 },
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

    const reportCategoryEl = document.getElementById('reportCategoryChart');
    if (reportCategoryEl && data.reportCategory && data.reportCategory.amounts.length) {
        new Chart(reportCategoryEl, {
            type: 'doughnut',
            data: {
                labels: data.reportCategory.labels,
                datasets: [{
                    data: data.reportCategory.amounts,
                    backgroundColor: data.reportCategory.colors,
                    borderWidth: 2,
                    borderColor: 'rgba(255,255,255,0.6)',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: legendBottom,
                    tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${currency(ctx.parsed)}` } },
                },
            },
        });
    }

    const reportBalanceEl = document.getElementById('reportBalanceChart');
    if (reportBalanceEl && data.reportBalance) {
        new Chart(reportBalanceEl, {
            type: 'line',
            data: {
                labels: data.reportBalance.labels,
                datasets: [{
                    label: 'Balance',
                    data: data.reportBalance.balances,
                    borderColor: INCOME_COLOR,
                    backgroundColor: 'rgba(11, 63, 68, 0.12)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (ctx) => `Balance: ${currency(ctx.parsed.y)}` } },
                },
                scales: {
                    y: { ticks: { callback: (v) => currency(v) } },
                },
            },
        });
    }

    const reportDebtEl = document.getElementById('reportDebtChart');
    if (reportDebtEl && data.reportDebt) {
        new Chart(reportDebtEl, {
            type: 'line',
            data: {
                labels: data.reportDebt.labels,
                datasets: [{
                    label: 'Deuda',
                    data: data.reportDebt.balances,
                    borderColor: DEBT_COLOR,
                    backgroundColor: 'rgba(180, 84, 78, 0.12)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (ctx) => `Deuda: ${currency(ctx.parsed.y)}` } },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: (v) => currency(v) } },
                },
            },
        });
    }

    const reportGoalsEl = document.getElementById('reportGoalsChart');
    if (reportGoalsEl && data.reportGoals && data.reportGoals.labels.length) {
        new Chart(reportGoalsEl, {
            type: 'bar',
            data: {
                labels: data.reportGoals.labels,
                datasets: [
                    { label: 'Ahorrado', data: data.reportGoals.saved, backgroundColor: SAVED_COLOR, borderRadius: 6 },
                    { label: 'Falta', data: data.reportGoals.remaining, backgroundColor: REMAINING_COLOR, borderRadius: 6 },
                ],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, beginAtZero: true, ticks: { callback: (v) => currency(v) } },
                    y: { stacked: true },
                },
                plugins: {
                    legend: legendBottom,
                    tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${currency(ctx.parsed.x)}` } },
                },
            },
        });
    }
}
