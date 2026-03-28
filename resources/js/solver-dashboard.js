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

const LANDSCAPE_PHENOMENON_LABELS = {
    neutral: "Neutral",
    plateau: "Plateau",
    local_minimum: "Local Minimum",
    deep_valley: "Deep Valley",
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

function landscapePhenomenonLabel(value) {
    if (typeof value !== "string" || value.trim() === "") {
        return "Neutral"
    }

    return LANDSCAPE_PHENOMENON_LABELS[value.trim().toLowerCase()] ?? value
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
    const alnsDestroyOperator = metric.alns_destroy_operator ?? metric.alnsDestroyOperator
    const alnsRepairOperator = metric.alns_repair_operator ?? metric.alnsRepairOperator
    const alnsImprovement = metric.alns_improvement ?? metric.alnsImprovement
    const landscapePhenomenon = metric.landscape_phenomenon ?? metric.landscapePhenomenon
    const landscapeObservation = metric.landscape_observation ?? metric.landscapeObservation

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

    if (alnsDestroyOperator || alnsRepairOperator) {
        const label = `ALNS: ${alnsDestroyOperator ?? "?"} + ${alnsRepairOperator ?? "?"}`
        const currentStats = chartState.operatorUsage.get(label) ?? {
            count: 0,
            rewardTotal: 0,
        }

        currentStats.count += 1
        currentStats.rewardTotal += Number(alnsImprovement ?? 0)
        chartState.operatorUsage.set(label, currentStats)
        syncOperatorChart()
    }

    appendLandscapeMetric(generation, metric.landscape_state ?? metric.landscapeState ?? "unknown")
    updateLandscapeObservation(landscapePhenomenon, landscapeObservation)
}

function updateLandscapeObservation(phenomenon, observation) {
    const root = chartState.root

    if (!root) {
        return
    }

    const phenomenonElement = root.querySelector("[data-landscape-phenomenon]")
    const confidenceElement = root.querySelector("[data-landscape-confidence]")
    const depthScoreElement = root.querySelector("[data-landscape-depth-score]")
    const summaryElement = root.querySelector("[data-landscape-summary]")

    if (!phenomenonElement || !confidenceElement || !depthScoreElement || !summaryElement) {
        return
    }

    const normalizedObservation = observation && typeof observation === "object" ? observation : {}
    const confidence = Number(normalizedObservation.confidence ?? 0)
    const depthScore = Number(normalizedObservation.depth_score ?? 0)
    const bestDeltaWindow = Number(normalizedObservation.best_delta_window ?? 0)
    const populationTurnover = Number(normalizedObservation.population_turnover ?? 0)
    const eliteSimilarity = Number(normalizedObservation.elite_similarity ?? 0)
    const bestSignatureChanged = Boolean(normalizedObservation.best_signature_changed ?? false)
    const basinLockConfidence = Number(normalizedObservation.basin_of_attraction_lock_confidence ?? 0)
    const basinLockDetected = Boolean(normalizedObservation.basin_of_attraction_lock_detected ?? false)
    const episodeDuration = Number(normalizedObservation.current_episode?.duration ?? 0)
    const searchResponsePolicy = String(normalizedObservation.search_response_simulation?.policy ?? "")
    const searchResponseWouldEscalate = Boolean(normalizedObservation.search_response_simulation?.would_escalate ?? false)
    const auditTargetBestDeltaWindow = Number(normalizedObservation.search_response_audit?.target_best_delta_window ?? 0)
    const auditTargetPopulationTurnover = Number(normalizedObservation.search_response_audit?.target_population_turnover ?? 0)
    const searchResponseOutcomeProgress = Number(normalizedObservation.search_response_outcome?.progress_score ?? 0)
    const searchResponseOutcomeSatisfied = Boolean(normalizedObservation.search_response_outcome?.targets_satisfied ?? false)
    const searchResponsePendingAudits = Number(normalizedObservation.search_response_pending_audits ?? 0)
    const effectivenessBestBySuccess = String(normalizedObservation.search_response_effectiveness_report?.best_policy_by_success ?? "")
    const effectivenessBestByProgress = String(normalizedObservation.search_response_effectiveness_report?.best_policy_by_progress ?? "")
    const effectivenessTotalResolved = Number(normalizedObservation.search_response_effectiveness_report?.total_resolved_outcomes ?? 0)
    const activationCandidate = String(normalizedObservation.search_response_activation_gate?.candidate_policy ?? "")
    const activationEligible = Boolean(normalizedObservation.search_response_activation_gate?.eligible_as_candidate ?? false)

    phenomenonElement.textContent = landscapePhenomenonLabel(phenomenon)
    confidenceElement.textContent = confidence.toFixed(2)
    depthScoreElement.textContent = depthScore.toFixed(2)
    summaryElement.textContent = `ep ${episodeDuration} | dBestWin ${bestDeltaWindow.toFixed(3)}${searchResponseWouldEscalate ? ` -> ${auditTargetBestDeltaWindow.toFixed(3)}` : ""} | turnover ${(populationTurnover * 100).toFixed(0)}%${searchResponseWouldEscalate ? ` -> ${(auditTargetPopulationTurnover * 100).toFixed(0)}%` : ""} | elite ${eliteSimilarity.toFixed(2)}${basinLockDetected ? ` | basin ${basinLockConfidence.toFixed(2)}` : ""}${searchResponseWouldEscalate ? ` | plan ${searchResponsePolicy}` : ""}${normalizedObservation.search_response_outcome ? ` | outcome ${searchResponseOutcomeSatisfied ? "hit" : "miss"} ${searchResponseOutcomeProgress.toFixed(2)}` : ""}${effectivenessTotalResolved > 0 ? ` | bestS ${effectivenessBestBySuccess || "-"} | bestP ${effectivenessBestByProgress || "-"} (${effectivenessTotalResolved})` : ""}${activationEligible ? ` | gate ${activationCandidate}` : ""}${searchResponsePendingAudits > 0 ? ` | pending ${searchResponsePendingAudits}` : ""}${bestSignatureChanged ? " | sig changed" : ""}`
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
