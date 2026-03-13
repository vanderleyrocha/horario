import { loadChartJs } from './lib/load-chart';

const dashboardState = {
    root: null,
    fitnessChart: null,
    diversityChart: null,
    mutationObserver: null,
    chartConstructor: null,
};

function resetChart(chart) {
    if (!chart) {
        return;
    }

    chart.data.labels = [];

    chart.data.datasets.forEach((dataset) => {
        dataset.data = [];
    });

    chart.update();
}

function destroyCharts() {
    dashboardState.fitnessChart?.destroy();
    dashboardState.diversityChart?.destroy();
    dashboardState.fitnessChart = null;
    dashboardState.diversityChart = null;
}

function createLineChart(Chart, element, datasets) {
    return new Chart(element, {
        type: 'line',
        data: {
            labels: [],
            datasets,
        },
        options: {
            responsive: true,
            animation: false,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                },
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Geracao',
                    },
                },
            },
        },
    });
}

async function initializeCharts(root) {
    const fitnessCanvas = root.querySelector('[data-chart="fitness"]');
    const diversityCanvas = root.querySelector('[data-chart="diversity"]');

    if (!fitnessCanvas || !diversityCanvas) {
        return;
    }

    const Chart = dashboardState.chartConstructor ?? await loadChartJs();
    dashboardState.chartConstructor = Chart;

    destroyCharts();

    dashboardState.root = root;
    dashboardState.fitnessChart = createLineChart(Chart, fitnessCanvas, [
        {
            label: 'Best Fitness',
            data: [],
            borderColor: '#16a34a',
            tension: 0.1,
            borderWidth: 2,
            pointRadius: 0,
        },
        {
            label: 'Avg Fitness',
            data: [],
            borderColor: '#9ca3af',
            borderDash: [5, 5],
            tension: 0.1,
            borderWidth: 1,
            pointRadius: 0,
        },
    ]);

    dashboardState.diversityChart = createLineChart(Chart, diversityCanvas, [
        {
            label: 'Entropia',
            data: [],
            borderColor: '#9333ea',
            tension: 0.3,
            borderWidth: 2,
            pointRadius: 0,
        },
        {
            label: 'Diversidade (Hamming)',
            data: [],
            borderColor: '#f97316',
            tension: 0.3,
            borderWidth: 2,
            pointRadius: 0,
        },
    ]);
}

async function ensureDashboard() {
    const root = document.querySelector('[data-ga-dashboard]');

    if (!root) {
        destroyCharts();
        dashboardState.root = null;
        return;
    }

    if (dashboardState.root === root && dashboardState.fitnessChart && dashboardState.diversityChart) {
        return;
    }

    await initializeCharts(root);
}

async function appendMetrics(detail) {
    await ensureDashboard();

    if (!dashboardState.fitnessChart || !dashboardState.diversityChart) {
        return;
    }

    dashboardState.fitnessChart.data.labels.push(detail.generation);
    dashboardState.fitnessChart.data.datasets[0].data.push(detail.bestFitness);
    dashboardState.fitnessChart.data.datasets[1].data.push(detail.avgFitness);
    dashboardState.fitnessChart.update();

    dashboardState.diversityChart.data.labels.push(detail.generation);
    dashboardState.diversityChart.data.datasets[0].data.push(detail.entropy);
    dashboardState.diversityChart.data.datasets[1].data.push(detail.diversity);
    dashboardState.diversityChart.update();
}

function registerListeners() {
    document.addEventListener('DOMContentLoaded', () => {
        void ensureDashboard();
    });

    document.addEventListener('livewire:navigated', () => {
        void ensureDashboard();
    });

    window.addEventListener('ga-metrics-updated', (event) => {
        void appendMetrics(event.detail ?? {});
    });

    window.addEventListener('ga-started', () => {
        void ensureDashboard().then(() => {
            resetChart(dashboardState.fitnessChart);
            resetChart(dashboardState.diversityChart);
        });
    });

    window.addEventListener('ga-finished', () => {
        void ensureDashboard();
    });

    if (!dashboardState.mutationObserver) {
        dashboardState.mutationObserver = new MutationObserver(() => {
            void ensureDashboard();
        });

        dashboardState.mutationObserver.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }
}

registerListeners();
