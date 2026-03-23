import Chart from "chart.js/auto"

if (window.__solverDashboardRegistered) {
    // Avoid duplicate listeners when the bundle is evaluated again.
} else {
    window.__solverDashboardRegistered = true

const chartState = {
    root: null,
    fitnessChart: null,
    diversityChart: null,
    entropyChart: null,
    mutationChart: null,
    operatorChart: null,
    landscapeChart: null,
}

function destroyCharts() {
    chartState.fitnessChart?.destroy()
    chartState.diversityChart?.destroy()
    chartState.entropyChart?.destroy()
    chartState.mutationChart?.destroy()
    chartState.operatorChart?.destroy()
    chartState.landscapeChart?.destroy()

    chartState.fitnessChart = null
    chartState.diversityChart = null
    chartState.entropyChart = null
    chartState.mutationChart = null
    chartState.operatorChart = null
    chartState.landscapeChart = null
}

function createLineChart(element, label) {
    return new Chart(element, {
        type: "line",
        data: {
            labels: [],
            datasets: [
                {
                    label,
                    data: [],
                },
            ],
        },
        options: {
            responsive: true,
            animation: false,
            maintainAspectRatio: false,
            resizeDelay: 100,
        },
    })
}

function createMultiLineChart(element, labels) {
    return new Chart(element, {
        type: "line",
        data: {
            labels: [],
            datasets: labels.map((label) => ({
                label,
                data: [],
            })),
        },
        options: {
            responsive: true,
            animation: false,
            maintainAspectRatio: false,
            resizeDelay: 100,
        },
    })
}

function createBarChart(element, label) {
    return new Chart(element, {
        type: "bar",
        data: {
            labels: [],
            datasets: [
                {
                    label,
                    data: [],
                },
            ],
        },
        options: {
            responsive: true,
            animation: false,
            maintainAspectRatio: false,
            resizeDelay: 100,
        },
    })
}

function createBubbleChart(element, label) {
    return new Chart(element, {
        type: "bubble",
        data: {
            datasets: [
                {
                    label,
                    data: [],
                },
            ],
        },
        options: {
            responsive: true,
            animation: false,
            maintainAspectRatio: false,
            resizeDelay: 100,
        },
    })
}

function normalizeHeatmap(heatmap) {
    if (!Array.isArray(heatmap)) {
        return []
    }

    const dataset = []

    for (let x = 0; x < heatmap.length; x += 1) {
        if (!Array.isArray(heatmap[x])) {
            continue
        }

        for (let y = 0; y < heatmap[x].length; y += 1) {
            dataset.push({
                x,
                y,
                r: Number(heatmap[x][y] ?? 0) * 2,
            })
        }
    }

    return dataset
}

function appendMetric(metric) {
    if (!metric || !chartState.fitnessChart) {
        return
    }

    const generation = metric.generation ?? chartState.fitnessChart.data.labels.length + 1

    chartState.fitnessChart.data.labels.push(generation)
    chartState.fitnessChart.data.datasets[0].data.push(Number(metric.best_fitness ?? metric.bestFitness ?? 0))
    chartState.fitnessChart.data.datasets[1].data.push(Number(metric.avg_fitness ?? metric.avgFitness ?? 0))

    chartState.diversityChart.data.labels.push(generation)
    chartState.diversityChart.data.datasets[0].data.push(Number(metric.diversity ?? 0))

    chartState.entropyChart.data.labels.push(generation)
    chartState.entropyChart.data.datasets[0].data.push(Number(metric.entropy ?? 0))

    chartState.mutationChart.data.labels.push(generation)
    chartState.mutationChart.data.datasets[0].data.push(Number(metric.mutation_rate ?? metric.mutationRate ?? 0))

    const operatorUsed = metric.operator_used ?? metric.operatorUsed
    const operatorReward = metric.operator_reward ?? metric.operatorReward

    if (operatorUsed !== undefined && operatorReward !== undefined) {
        chartState.operatorChart.data.labels = [String(operatorUsed)]
        chartState.operatorChart.data.datasets[0].data = [Number(operatorReward)]
    }

    const heatmap = metric.landscape_heatmap ?? metric.landscapeHeatmap

    if (heatmap !== undefined) {
        chartState.landscapeChart.data.datasets[0].data = normalizeHeatmap(heatmap)
    }
}

function updateAllCharts() {
    chartState.fitnessChart?.update()
    chartState.diversityChart?.update()
    chartState.entropyChart?.update()
    chartState.mutationChart?.update()
    chartState.operatorChart?.update()
    chartState.landscapeChart?.update()
}

function loadInitialMetrics() {
    const metrics = Array.isArray(window.solverMetrics) ? window.solverMetrics : []

    metrics.forEach((metric) => {
        appendMetric(metric)
    })

    updateAllCharts()
}

function initializeDashboard() {
    const root = document.querySelector("[data-solver-dashboard]")

    if (!root) {
        destroyCharts()
        chartState.root = null
        return
    }

    if (chartState.root === root && chartState.fitnessChart) {
        return
    }

    const fitnessCanvas = root.querySelector("#fitnessChart")
    const diversityCanvas = root.querySelector("#diversityChart")
    const entropyCanvas = root.querySelector("#entropyChart")
    const mutationCanvas = root.querySelector("#mutationChart")
    const operatorCanvas = root.querySelector("#operatorChart")
    const landscapeCanvas = root.querySelector("#landscapeChart")

    if (!fitnessCanvas || !diversityCanvas || !entropyCanvas || !mutationCanvas || !operatorCanvas || !landscapeCanvas) {
        return
    }

    destroyCharts()

    chartState.root = root
    chartState.fitnessChart = createMultiLineChart(fitnessCanvas, ["Best Fitness", "Average Fitness"])
    chartState.diversityChart = createLineChart(diversityCanvas, "Diversity")
    chartState.entropyChart = createLineChart(entropyCanvas, "Entropy")
    chartState.mutationChart = createLineChart(mutationCanvas, "Mutation Rate")
    chartState.operatorChart = createBarChart(operatorCanvas, "Operator Reward")
    chartState.landscapeChart = createBubbleChart(landscapeCanvas, "Search Landscape")

    loadInitialMetrics()
}

document.addEventListener("DOMContentLoaded", initializeDashboard)
document.addEventListener("livewire:navigated", initializeDashboard)
}
