import { loadChartJs } from './lib/load-chart';

const metrics = window.solverMetrics ?? [];

const generations = metrics.map((metric) => metric.generation);
const bestFitness = metrics.map((metric) => metric.best_fitness);
const diversity = metrics.map((metric) => metric.diversity);
const entropy = metrics.map((metric) => metric.entropy);
const mutationRate = metrics.map((metric) => metric.mutation_rate);

const operatorRewards = {};

metrics.forEach((metric) => {
    if (!metric.operator_used) {
        return;
    }

    if (!operatorRewards[metric.operator_used]) {
        operatorRewards[metric.operator_used] = 0;
    }

    operatorRewards[metric.operator_used] += metric.operator_reward;
});

async function bootSolverDashboard() {
    const Chart = await loadChartJs();

    const fitnessChart = new Chart(document.getElementById('fitnessChart'), {
        type: 'line',
        data: {
            labels: generations,
            datasets: [{
                label: 'Best Fitness',
                data: bestFitness,
                borderColor: '#2563eb',
                tension: 0.3,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true },
            },
            scales: {
                x: { title: { display: true, text: 'Generation' } },
                y: { title: { display: true, text: 'Best Fitness' } },
            },
        },
    });

    const diversityChart = new Chart(document.getElementById('diversityChart'), {
        type: 'line',
        data: {
            labels: generations,
            datasets: [{
                label: 'Diversity',
                data: diversity,
                borderColor: '#059669',
                tension: 0.3,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true },
            },
        },
    });

    const entropyChart = new Chart(document.getElementById('entropyChart'), {
        type: 'line',
        data: {
            labels: generations,
            datasets: [{
                label: 'Entropy',
                data: entropy,
                borderColor: '#7c3aed',
                tension: 0.3,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true },
            },
        },
    });

    const mutationChart = new Chart(document.getElementById('mutationChart'), {
        type: 'line',
        data: {
            labels: generations,
            datasets: [{
                label: 'Mutation Rate',
                data: mutationRate,
                borderColor: '#ea580c',
                tension: 0.3,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true },
            },
        },
    });

    new Chart(document.getElementById('operatorChart'), {
        type: 'bar',
        data: {
            labels: Object.keys(operatorRewards),
            datasets: [{
                label: 'Operator Reward',
                data: Object.values(operatorRewards),
                backgroundColor: '#2563eb',
            }],
        },
    });

    const landscapeStates = metrics.map((metric) => metric.landscape_state);

    new Chart(document.getElementById('landscapeChart'), {
        type: 'line',
        data: {
            labels: generations,
            datasets: [{
                label: 'Landscape State',
                data: landscapeStates,
                borderColor: '#ef4444',
            }],
        },
    });

    window.addEventListener('metrics-update', (event) => {
        const data = event.detail;

        fitnessChart.data.labels.push(data.generation);
        fitnessChart.data.datasets[0].data.push(data.best_fitness);

        diversityChart.data.labels.push(data.generation);
        diversityChart.data.datasets[0].data.push(data.diversity);

        entropyChart.data.labels.push(data.generation);
        entropyChart.data.datasets[0].data.push(data.entropy);

        mutationChart.data.labels.push(data.generation);
        mutationChart.data.datasets[0].data.push(data.mutation_rate);

        fitnessChart.update();
        diversityChart.update();
        entropyChart.update();
        mutationChart.update();
    });
}

void bootSolverDashboard();
