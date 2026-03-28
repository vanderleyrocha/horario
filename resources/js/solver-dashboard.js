import Chart from "chart.js/auto"

if (window.__solverDashboardRegistered) {
    // Avoid duplicate listeners when the bundle is evaluated again.
} else {
    window.__solverDashboardRegistered = true

const chartState = {
    root: null,
    executionId: null,
    heartbeatIntervalId: null,
    lastHeartbeatAt: null,
    lastHeartbeatPhase: "",
    lastHeartbeatStage: "",
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
    if (chartState.heartbeatIntervalId) {
        window.clearInterval(chartState.heartbeatIntervalId)
    }

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
    chartState.heartbeatIntervalId = null
    chartState.lastHeartbeatAt = null
    chartState.lastHeartbeatPhase = ""
    chartState.lastHeartbeatStage = ""
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
    0: "Desconhecido",
    1: "Exploração",
    2: "Exploração+",
    3: "Controlado",
    4: "Equilibrado",
    5: "Estagnação I",
    6: "Estagnação II",
    7: "Estagnação III",
    8: "Convergência",
}

const LANDSCAPE_PHENOMENON_LABELS = {
    neutral: "Neutro",
    plateau: "Plateau",
    local_minimum: "Mínimo local",
    deep_valley: "Vale profundo",
}

Object.assign(LANDSCAPE_STATE_SCALE, {
    exploitation: 2,
    "exploracao_controlada": 2,
    "exploracao controlada": 2,
    plateau: 3,
    premature_convergence: 4,
    convergencia_prematura: 4,
    chaotic: 5,
    caotico: 5,
})

Object.assign(LANDSCAPE_LABELS, {
    1: "Exploracao",
    2: "Exploitation",
    3: "Plateau",
    4: "Convergencia prematura",
    5: "Caotico",
})

function normalizeLandscapeState(state) {
    if (typeof state !== "string" || state.trim() === "") {
        return 0
    }

    const key = state.trim().toLowerCase()

    return LANDSCAPE_STATE_SCALE[key] ?? 0
}

function landscapeLabel(value) {
    return LANDSCAPE_LABELS[value] ?? `Estado ${value}`
}

function landscapePhenomenonLabel(value) {
    if (typeof value !== "string" || value.trim() === "") {
        return "Neutro"
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

function parseHeartbeatTimestamp(value) {
    if (typeof value === "number" && Number.isFinite(value)) {
        const milliseconds = value > 1_000_000_000_000 ? value : value * 1000
        return new Date(milliseconds)
    }

    if (typeof value === "string" && value.trim() !== "") {
        const parsed = new Date(value)

        if (!Number.isNaN(parsed.getTime())) {
            return parsed
        }
    }

    return null
}

function formatElapsedSeconds(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) {
        return "--"
    }

    if (seconds < 60) {
        return `${Math.floor(seconds)}s`
    }

    const minutes = Math.floor(seconds / 60)
    const remainingSeconds = Math.floor(seconds % 60)

    if (minutes < 60) {
        return `${minutes}min ${remainingSeconds}s`
    }

    const hours = Math.floor(minutes / 60)
    const remainingMinutes = minutes % 60

    return `${hours}h ${remainingMinutes}min`
}

function resolveHeartbeatSeverity(phase, stage, ageSeconds) {
    let thresholds = { monitoring: 20, warning: 60, critical: 180 }

    if (phase === "initial_population" && stage.includes("quality_gate_repair")) {
        thresholds = { monitoring: 30, warning: 120, critical: 360 }
    } else if (phase === "initial_population") {
        thresholds = { monitoring: 20, warning: 90, critical: 240 }
    } else if (phase === "evolving" || phase === "evolution") {
        thresholds = { monitoring: 15, warning: 45, critical: 120 }
    }

    if (ageSeconds <= thresholds.monitoring) {
        return {
            status: "saudavel",
            note: "Heartbeat recente. O solver segue publicando progresso normalmente.",
        }
    }

    if (ageSeconds <= thresholds.warning) {
        return {
            status: "monitorando",
            note: "Heartbeat mais espacoso, mas ainda dentro da janela esperada para esta fase.",
        }
    }

    if (ageSeconds <= thresholds.critical) {
        return {
            status: "atencao",
            note: phase === "initial_population"
                ? "A populacao inicial esta demorando para publicar um novo heartbeat."
                : "O heartbeat da execucao esta atrasado e merece acompanhamento.",
        }
    }

    return {
        status: "possivel estagnacao operacional",
        note: phase === "initial_population"
            ? "Sem novo heartbeat ha tempo demais durante a populacao inicial ou reparo."
            : "Sem novo heartbeat ha tempo demais. Vale conferir worker, fila e logs.",
    }
}

function renderDashboardHeartbeatMonitor() {
    const root = chartState.root

    if (!root) {
        return
    }

    const lastHeartbeatElement = root.querySelector("[data-dashboard-last-heartbeat]")
    const delayElement = root.querySelector("[data-dashboard-heartbeat-delay]")
    const statusElement = root.querySelector("[data-dashboard-heartbeat-status]")
    const noteElement = root.querySelector("[data-dashboard-heartbeat-note]")
    const lastHeartbeatCard = root.querySelector("[data-dashboard-last-heartbeat-card]")
    const delayCard = root.querySelector("[data-dashboard-heartbeat-delay-card]")
    const logLinkWrapper = root.querySelector("[data-dashboard-log-link-wrapper]")

    if (!lastHeartbeatElement || !delayElement || !statusElement || !noteElement || !lastHeartbeatCard || !delayCard || !logLinkWrapper) {
        return
    }

    if (!(chartState.lastHeartbeatAt instanceof Date) || Number.isNaN(chartState.lastHeartbeatAt.getTime())) {
        lastHeartbeatElement.textContent = "Sem heartbeat ainda"
        delayElement.textContent = "--"
        statusElement.textContent = "Aguardando primeiro sinal"
        noteElement.textContent = "O contador atualiza sozinho entre os heartbeats."
        applyHeartbeatCardSeverity(lastHeartbeatCard, delayCard, logLinkWrapper, "idle")
        return
    }

    const ageSeconds = Math.max(0, (Date.now() - chartState.lastHeartbeatAt.getTime()) / 1000)
    const severity = resolveHeartbeatSeverity(
        chartState.lastHeartbeatPhase,
        chartState.lastHeartbeatStage,
        ageSeconds,
    )

    lastHeartbeatElement.textContent = chartState.lastHeartbeatAt.toLocaleString("pt-BR")
    delayElement.textContent = formatElapsedSeconds(ageSeconds)
    statusElement.textContent = severity.status
    noteElement.textContent = severity.note
    applyHeartbeatCardSeverity(lastHeartbeatCard, delayCard, logLinkWrapper, severity.status)
}

function applyHeartbeatCardSeverity(lastHeartbeatCard, delayCard, logLinkWrapper, status) {
    const paletteByStatus = {
        idle: {
            card: ["border-slate-200", "bg-white", "text-slate-900"],
            note: ["text-slate-500"],
            showLogs: false,
        },
        saudavel: {
            card: ["border-emerald-200", "bg-emerald-50", "text-emerald-900"],
            note: ["text-emerald-700"],
            showLogs: false,
        },
        monitorando: {
            card: ["border-sky-200", "bg-sky-50", "text-sky-900"],
            note: ["text-sky-700"],
            showLogs: false,
        },
        atencao: {
            card: ["border-amber-200", "bg-amber-50", "text-amber-900"],
            note: ["text-amber-700"],
            showLogs: true,
        },
        "possivel estagnacao operacional": {
            card: ["border-rose-200", "bg-rose-50", "text-rose-900"],
            note: ["text-rose-700"],
            showLogs: true,
        },
    }

    const palette = paletteByStatus[status] ?? paletteByStatus.idle
    const resetCardClasses = [
        "border-slate-200", "bg-white", "text-slate-900",
        "border-emerald-200", "bg-emerald-50", "text-emerald-900",
        "border-sky-200", "bg-sky-50", "text-sky-900",
        "border-amber-200", "bg-amber-50", "text-amber-900",
        "border-rose-200", "bg-rose-50", "text-rose-900",
    ]
    const resetNoteClasses = [
        "text-slate-500",
        "text-emerald-700",
        "text-sky-700",
        "text-amber-700",
        "text-rose-700",
    ]

    for (const element of [lastHeartbeatCard, delayCard]) {
        element.classList.remove(...resetCardClasses)
        element.classList.add(...palette.card)
    }

    const noteTarget = delayCard.querySelector("[data-dashboard-heartbeat-note]")

    if (noteTarget) {
        noteTarget.classList.remove(...resetNoteClasses)
        noteTarget.classList.add(...palette.note)
    }

    logLinkWrapper.classList.toggle("hidden", !palette.showLogs)
}

function updateDashboardHeartbeatMonitor(metric) {
    if (!metric || typeof metric !== "object") {
        return
    }

    const heartbeatAt = parseHeartbeatTimestamp(metric.timestamp ?? metric.created_at ?? metric.updated_at)

    if (!heartbeatAt) {
        return
    }

    chartState.lastHeartbeatAt = heartbeatAt
    chartState.lastHeartbeatPhase = String(metric.phase ?? "")
    chartState.lastHeartbeatStage = String(metric.stage ?? "")

    renderDashboardHeartbeatMonitor()
}

function ensureHeartbeatTicker() {
    if (chartState.heartbeatIntervalId) {
        return
    }

    chartState.heartbeatIntervalId = window.setInterval(() => {
        renderDashboardHeartbeatMonitor()
    }, 1000)
}

function translateRepairEvent(value) {
    const normalized = String(value ?? "").trim().toLowerCase()

    const map = {
        pass_started: "inicio do passe",
        pass_progress: "progresso do passe",
        pass_finished: "fim do passe",
        relocation_applied: "realocacao aplicada",
        swap_applied: "troca aplicada",
        local_rebuild_applied: "reconstrucao local",
    }

    return map[normalized] ?? normalized.replaceAll("_", " ")
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

    updateDashboardHeartbeatMonitor(metric)

    if ((metric.phase ?? null) === "initial_population") {
        updateInitialPopulationObservationReadable(metric)
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
    updateSearchResponseReadiness(landscapeObservation)
}

function updateInitialPopulationObservationReadable(progress) {
    const root = chartState.root

    if (!root) {
        return
    }

    const stageElement = root.querySelector("[data-initial-stage]")
    const attemptElement = root.querySelector("[data-initial-attempt]")
    const fillRatioElement = root.querySelector("[data-initial-fill-ratio]")
    const summaryElement = root.querySelector("[data-initial-summary]")

    if (!stageElement || !attemptElement || !fillRatioElement || !summaryElement) {
        return
    }

    const stage = String(progress.stage ?? "aguardando")
    const attempt = Number(progress.attempt ?? 0)
    const fillRatio = Number(progress.fill_ratio ?? progress.population_fill_ratio ?? 0)
    const queueSize = Number(progress.queue_size ?? 0)
    const allocations = Number(progress.allocations ?? 0)
    const forcedAllocations = Number(progress.forced_allocations ?? 0)
    const hardConflictAllocations = Number(progress.hard_conflict_allocations ?? 0)
    const repairPass = Number(progress.repair_pass ?? 0)
    const repairEvent = String(progress.repair_event ?? "")
    const repairProcessed = Number(progress.repair_processed_invalid_genes ?? 0)
    const repairTotal = Number(progress.repair_total_invalid_genes ?? 0)
    const repairInvalidAfter = Number(progress.repair_invalid_genes_after ?? 0)
    const hardPenalty = progress.hard_penalty ?? progress.repair_hard_penalty_after ?? null
    const message = String(progress.message ?? "")

    stageElement.textContent = stage.replaceAll("_", " ")
    attemptElement.textContent = attempt > 0 ? String(attempt) : "-"
    fillRatioElement.textContent = `${(fillRatio * 100).toFixed(0)}%`
    summaryElement.textContent = `aloc ${allocations}/${queueSize || "-"} | forçadas ${forcedAllocations} | hard ${hardConflictAllocations}${repairEvent ? ` | reparo p${repairPass} ${repairEvent}` : ""}${repairTotal > 0 ? ` ${repairProcessed}/${repairTotal}` : ""}${repairInvalidAfter > 0 ? ` | inválidos ${repairInvalidAfter}` : ""}${hardPenalty !== null ? ` | hp ${Number(hardPenalty).toFixed(2)}` : ""}${message ? ` | ${message}` : ""}`
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
    const alnsTrigger = normalizedObservation.alns_trigger ?? {}
    const alnsTriggered = Boolean(alnsTrigger.triggered ?? false)
    const alnsTriggerReason = String(alnsTrigger.reason ?? "")
    const alnsEffectiveFrequency = Number(alnsTrigger.effective_frequency ?? 0)
    const alnsResponse = alnsTrigger.response ?? {}
    const alnsAggressionLabel = String(alnsResponse.aggression_label ?? "")
    const alnsDestroyRatio = Number(alnsResponse.destroy_ratio ?? 0)
    const alnsRecentSuccessRate = Number(alnsResponse.recent_success_rate ?? 0)
    const alnsRealActivation = alnsTrigger.real_activation ?? {}
    const alnsRealActivationApplied = Boolean(alnsRealActivation.applied ?? false)
    const alnsRealActivationPolicy = String(alnsRealActivation.policy ?? "")

    phenomenonElement.textContent = landscapePhenomenonLabel(phenomenon)
    confidenceElement.textContent = confidence.toFixed(2)
    depthScoreElement.textContent = depthScore.toFixed(2)
    summaryElement.textContent = `ep ${episodeDuration} | dBestWin ${bestDeltaWindow.toFixed(3)}${searchResponseWouldEscalate ? ` -> ${auditTargetBestDeltaWindow.toFixed(3)}` : ""} | turnover ${(populationTurnover * 100).toFixed(0)}%${searchResponseWouldEscalate ? ` -> ${(auditTargetPopulationTurnover * 100).toFixed(0)}%` : ""} | elite ${eliteSimilarity.toFixed(2)}${basinLockDetected ? ` | basin ${basinLockConfidence.toFixed(2)}` : ""}${alnsEffectiveFrequency > 0 ? ` | ALNS q${alnsEffectiveFrequency}` : ""}${alnsTriggered ? `:${alnsTriggerReason || "trigger"}` : ""}${alnsRealActivationApplied ? ` live:${alnsRealActivationPolicy || "gate"}` : ""}${alnsAggressionLabel ? ` ${alnsAggressionLabel}` : ""}${alnsDestroyRatio > 0 ? ` d${alnsDestroyRatio.toFixed(2)}` : ""}${alnsAggressionLabel ? ` s${(alnsRecentSuccessRate * 100).toFixed(0)}%` : ""}${searchResponseWouldEscalate ? ` | plan ${searchResponsePolicy}` : ""}${normalizedObservation.search_response_outcome ? ` | outcome ${searchResponseOutcomeSatisfied ? "hit" : "miss"} ${searchResponseOutcomeProgress.toFixed(2)}` : ""}${effectivenessTotalResolved > 0 ? ` | bestS ${effectivenessBestBySuccess || "-"} | bestP ${effectivenessBestByProgress || "-"} (${effectivenessTotalResolved})` : ""}${activationEligible ? ` | gate ${activationCandidate}` : ""}${searchResponsePendingAudits > 0 ? ` | pending ${searchResponsePendingAudits}` : ""}${bestSignatureChanged ? " | sig changed" : ""}`
}

function updateSearchResponseReadiness(observation) {
    const root = chartState.root

    if (!root) {
        return
    }

    const readiness = observation && typeof observation === "object"
        ? observation.search_response_readiness_dashboard ?? {}
        : {}

    const headlineElement = root.querySelector("[data-sr-readiness-headline]")
    const statusElement = root.querySelector("[data-sr-readiness-status]")
    const evidenceCountElement = root.querySelector("[data-sr-evidence-count]")
    const pendingAuditsElement = root.querySelector("[data-sr-pending-audits]")
    const gateStatusElement = root.querySelector("[data-sr-gate-status]")
    const gateCandidateElement = root.querySelector("[data-sr-gate-candidate]")
    const bestOutcomeElement = root.querySelector("[data-sr-best-outcome]")
    const bestProgressElement = root.querySelector("[data-sr-best-progress]")
    const latestOutcomeElement = root.querySelector("[data-sr-latest-outcome]")
    const latestOutcomeDetailElement = root.querySelector("[data-sr-latest-outcome-detail]")
    const policyRowsElement = root.querySelector("[data-sr-policy-rows]")
    const blockingReasonsElement = root.querySelector("[data-sr-blocking-reasons]")

    if (
        !headlineElement ||
        !statusElement ||
        !evidenceCountElement ||
        !pendingAuditsElement ||
        !gateStatusElement ||
        !gateCandidateElement ||
        !bestOutcomeElement ||
        !bestProgressElement ||
        !latestOutcomeElement ||
        !latestOutcomeDetailElement ||
        !policyRowsElement ||
        !blockingReasonsElement
    ) {
        return
    }

    const status = String(readiness.status ?? "ocioso")
    const headline = String(readiness.headline ?? "Nenhuma evidência de prontidão coletada ainda")
    const resolvedEvidenceCount = Number(readiness.resolved_evidence_count ?? 0)
    const pendingAudits = Number(readiness.pending_audits ?? 0)
    const bestBySuccess = readiness.best_policy_by_success ?? null
    const bestByProgress = readiness.best_policy_by_progress ?? null
    const latestOutcome = readiness.latest_outcome ?? null
    const activationGate = readiness.activation_gate ?? null
    const blockingReasons = Array.isArray(readiness.blocking_reasons) ? readiness.blocking_reasons : []
    const policyRows = Array.isArray(readiness.policy_rows) ? readiness.policy_rows : []

    headlineElement.textContent = headline
    statusElement.textContent = status.replaceAll("_", " ")
    evidenceCountElement.textContent = String(resolvedEvidenceCount)
    pendingAuditsElement.textContent = String(pendingAudits)
    gateStatusElement.textContent = Boolean(activationGate?.eligible_as_candidate)
        ? "Candidata pronta"
        : "Somente diagnóstico"
    gateCandidateElement.textContent = activationGate?.candidate_policy
        ? `Candidata: ${activationGate.candidate_policy}`
        : String(activationGate?.reason ?? "Nenhuma candidata ainda")

    bestOutcomeElement.textContent = bestBySuccess?.policy
        ? `${bestBySuccess.policy} | sucesso ${(Number(bestBySuccess.success_rate ?? 0) * 100).toFixed(0)}%`
        : "Evidência insuficiente"
    bestProgressElement.textContent = bestByProgress?.policy
        ? `${bestByProgress.policy} | progresso ${Number(bestByProgress.avg_progress_score ?? 0).toFixed(2)}`
        : "Progresso indisponível"

    latestOutcomeElement.textContent = latestOutcome?.policy
        ? `${latestOutcome.policy} | ${Boolean(latestOutcome.targets_satisfied) ? "alvos atingidos" : "alvos não atingidos"}`
        : "Nenhum resultado resolvido ainda"
    latestOutcomeDetailElement.textContent = latestOutcome?.policy
        ? `Progresso ${Number(latestOutcome.progress_score ?? 0).toFixed(2)} | geração resolvida ${Number(latestOutcome.resolved_generation ?? 0)}`
        : "Aguardando o primeiro horizonte expirar"

    if (policyRows.length === 0) {
        policyRowsElement.innerHTML = `
            <tr>
                <td colspan="5" class="py-4 text-sm text-slate-500">Nenhuma política avaliada ainda.</td>
            </tr>
        `
    } else {
        policyRowsElement.innerHTML = policyRows.map((policy) => `
            <tr>
                <td class="py-3 pr-4 font-medium text-slate-900">${String(policy.policy ?? "-")}</td>
                <td class="py-3 pr-4 text-slate-600">${Number(policy.resolved_outcomes ?? 0)}</td>
                <td class="py-3 pr-4 text-slate-600">${(Number(policy.success_rate ?? 0) * 100).toFixed(0)}%</td>
                <td class="py-3 pr-4 text-slate-600">${(Number(policy.approach_rate ?? 0) * 100).toFixed(0)}%</td>
                <td class="py-3 text-slate-600">${Number(policy.avg_progress_score ?? 0).toFixed(2)}</td>
            </tr>
        `).join("")
    }

    if (blockingReasons.length === 0) {
        blockingReasonsElement.innerHTML = `
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                Nenhum motivo de bloqueio no momento.
            </p>
        `
    } else {
        blockingReasonsElement.innerHTML = blockingReasons.map((reason) => `
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                ${String(reason)}
            </p>
        `).join("")
    }
}

function updateInitialPopulationObservation(progress) {
    const root = chartState.root

    if (!root) {
        return
    }

    const stageElement = root.querySelector("[data-initial-stage]")
    const attemptElement = root.querySelector("[data-initial-attempt]")
    const fillRatioElement = root.querySelector("[data-initial-fill-ratio]")
    const summaryElement = root.querySelector("[data-initial-summary]")

    if (!stageElement || !attemptElement || !fillRatioElement || !summaryElement) {
        return
    }

    const stage = String(progress.stage ?? "aguardando")
    const attempt = Number(progress.attempt ?? 0)
    const fillRatio = Number(progress.fill_ratio ?? progress.population_fill_ratio ?? 0)
    const queueSize = Number(progress.queue_size ?? 0)
    const allocations = Number(progress.allocations ?? 0)
    const forcedAllocations = Number(progress.forced_allocations ?? 0)
    const hardConflictAllocations = Number(progress.hard_conflict_allocations ?? 0)
    const repairPass = Number(progress.repair_pass ?? 0)
    const repairEvent = String(progress.repair_event ?? "")
    const repairProcessed = Number(progress.repair_processed_invalid_genes ?? 0)
    const repairTotal = Number(progress.repair_total_invalid_genes ?? 0)
    const repairInvalidAfter = Number(progress.repair_invalid_genes_after ?? 0)
    const hardPenalty = progress.hard_penalty ?? progress.repair_hard_penalty_after ?? null
    const message = String(progress.message ?? "")
    const summaryParts = [
        `Alocacoes ${allocations}/${queueSize || "-"}`,
        `Forcadas ${forcedAllocations}`,
        `Conflitos hard ${hardConflictAllocations}`,
    ]

    if (repairEvent) {
        summaryParts.push(`Reparo passe ${repairPass || "-"}: ${translateRepairEvent(repairEvent)}`)
    }

    if (repairTotal > 0) {
        summaryParts.push(`Genes verificados ${repairProcessed}/${repairTotal}`)
    }

    if (repairInvalidAfter > 0) {
        summaryParts.push(`Invalidos ${repairInvalidAfter}`)
    }

    if (hardPenalty !== null) {
        summaryParts.push(`Penalidade hard ${Number(hardPenalty).toFixed(2)}`)
    }

    if (message) {
        summaryParts.push(message)
    }

    stageElement.textContent = stage.replaceAll("_", " ")
    attemptElement.textContent = attempt > 0 ? String(attempt) : "-"
    fillRatioElement.textContent = `${(fillRatio * 100).toFixed(0)}%`
    summaryElement.textContent = summaryParts.join(" · ")
    summaryElement.title = summaryParts.join(" · ")
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
    ensureHeartbeatTicker()
    chartState.fitnessChart = createMultiLineChart(fitnessCanvas, ["Melhor fitness", "Fitness médio"])
    chartState.diversityChart = createLineChart(diversityCanvas, "Diversidade")
    chartState.entropyChart = createLineChart(entropyCanvas, "Entropia")
    chartState.mutationChart = createLineChart(mutationCanvas, "Taxa de mutação")
    chartState.operatorChart = createBarChart(operatorCanvas, "Recompensa média por operador")
    chartState.landscapeChart = createBubbleChart(landscapeCanvas, "Estado do landscape")

    loadInitialMetrics()
    renderDashboardHeartbeatMonitor()
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
