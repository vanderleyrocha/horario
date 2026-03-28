import Chart from "chart.js/auto"

if (window.__solverDashboardRegistered) {
    // Avoid duplicate listeners when the bundle is evaluated again.
} else {
    window.__solverDashboardRegistered = true

const chartState = {
    root: null,
    executionId: null,
    seenGenerations: new Set(),
    operatorUsage: new Map(),
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
    chartState.seenGenerations = new Set()
    chartState.operatorUsage = new Map()
    chartState.executionId = null
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
        type: "line",
        data: {
            labels: [],
            datasets: [
                {
                    label,
                    data: [],
                    stepped: true,
                    borderColor: "#7c3aed",
                    backgroundColor: "rgba(124, 58, 237, 0.18)",
                    pointRadius: 3,
                },
            ],
        },
        options: {
            responsive: true,
            animation: false,
            maintainAspectRatio: false,
            resizeDelay: 100,
            scales: {
                y: {
                    min: 0,
                    ticks: {
                        callback(value) {
                            return landscapeLabel(Number(value))
                        },
                    },
                },
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label(context) {
                            return landscapeLabel(Number(context.parsed.y))
                        },
                    },
                },
            },
        },
    })
}

const LANDSCAPE_STATE_SCALE = {
    unknown: 0,
    exploracao: 1,
    exploration: 1,
    "exploracao_intensiva": 2,
    "exploracao intensiva": 2,
    "exploracao_controlada": 3,
    "exploracao controlada": 3,
    equilibrado: 4,
    balanced: 4,
    "estagnacao_leve": 5,
    "estagnacao leve": 5,
    "estagnacao_moderada": 6,
    "estagnacao moderada": 6,
    "estagnacao_severa": 7,
    "estagnacao severa": 7,
    stagnation: 7,
    convergence: 8,
    convergencia: 8,
}

const LANDSCAPE_LABELS = {
    0: "Unknown",
    1: "Exploration",
    2: "Exploration+",
    3: "Controlled",
    4: "Balanced",
    5: "Stagnation I",
    6: "Stagnation II",
    7: "Stagnation III",
    8: "Convergence",
}

function normalizeLandscapeState(state) {
    if (typeof state !== "string" || state.trim() === "") {
        return 0
    }

    const key = state.trim().toLowerCase()

    return LANDSCAPE_STATE_SCALE[key] ?? 0
}

function landscapeLabel(value) {
    return LANDSCAPE_LABELS[value] ?? `State ${value}`
}

function normalizeMetricEvent(detail) {
    if (!detail) {
        return null
    }

    if (Array.isArray(detail)) {
        return detail.at(-1) ?? null
    }

    if (Array.isArray(detail.metric)) {
        return detail.metric.at(-1) ?? null
    }

    if (detail.metric && typeof detail.metric === "object") {
        return detail.metric
    }

    return typeof detail === "object" ? detail : null
}

function syncOperatorChart() {
    if (!chartState.operatorChart) {
        return
    }

    const entries = [...chartState.operatorUsage.entries()]

    chartState.operatorChart.data.labels = entries.map(([label]) => label)
    chartState.operatorChart.data.datasets[0].data = entries.map(([, stats]) => {
        if ((stats.count ?? 0) === 0) {
            return 0
        }

        return Number(stats.rewardTotal ?? 0) / stats.count
    })
}

function appendLandscapeMetric(generation, landscapeState) {
    if (!chartState.landscapeChart) {
        return
    }

    chartState.landscapeChart.data.labels.push(generation)
    chartState.landscapeChart.data.datasets[0].data.push({
        x: generation,
        y: normalizeLandscapeState(landscapeState),
    })
}

function appendMetric(metric) {
    if (!metric || !chartState.fitnessChart) {
        return
    }

    const generation = metric.generation ?? chartState.fitnessChart.data.labels.length + 1

    if (chartState.seenGenerations.has(generation)) {
        return
    }

    chartState.seenGenerations.add(generation)

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

    if (operatorUsed) {
        const currentStats = chartState.operatorUsage.get(String(operatorUsed)) ?? {
            count: 0,
            rewardTotal: 0,
        }

        currentStats.count += 1
        currentStats.rewardTotal += Number(operatorReward ?? 0)
        chartState.operatorUsage.set(String(operatorUsed), currentStats)
        syncOperatorChart()
    }

    appendLandscapeMetric(generation, metric.landscape_state ?? metric.landscapeState ?? "unknown")
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
    chartState.executionId = window.executionId ?? null
    chartState.fitnessChart = createMultiLineChart(fitnessCanvas, ["Best Fitness", "Average Fitness"])
    chartState.diversityChart = createLineChart(diversityCanvas, "Diversity")
    chartState.entropyChart = createLineChart(entropyCanvas, "Entropy")
    chartState.mutationChart = createLineChart(mutationCanvas, "Mutation Rate")
    chartState.operatorChart = createBarChart(operatorCanvas, "Average Operator Reward")
    chartState.landscapeChart = createBubbleChart(landscapeCanvas, "Landscape State")

    loadInitialMetrics()
}

function handleMetricEvent(event) {
    const metric = normalizeMetricEvent(event?.detail)

    if (!metric) {
        return
    }

    const metricExecutionId = metric.execution_id ?? metric.executionId ?? null

    if (
        chartState.executionId !== null &&
        metricExecutionId !== null &&
        Number(metricExecutionId) !== Number(chartState.executionId)
    ) {
        return
    }

    initializeDashboard()
    appendMetric(metric)
    updateAllCharts()
}

document.addEventListener("DOMContentLoaded", initializeDashboard)
document.addEventListener("livewire:navigated", initializeDashboard)
window.addEventListener("metrics-update", handleMetricEvent)
document.addEventListener("metrics-update", handleMetricEvent)
}
