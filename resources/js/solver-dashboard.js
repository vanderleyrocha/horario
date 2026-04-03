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
        lastHeartbeatOperationLabel: "",
        lastHeartbeatOperationElapsedSeconds: null,
        lastHeartbeatIslandId: null,
        lastHeartbeatGeneration: null,
        lastHeartbeatLocalGeneration: null,
        lastHeartbeatPopulationTarget: null,
        lastHeartbeatOffspringBuilt: null,
        incompleteGenerationSnapshotCount: 0,
        ignoredMetricWarnings: new Set(),
        ignoredMetricWarningCount: 0,
        latestIgnoredMetricDiagnostic: null,
        seenGenerations: new Set(),
        operatorUsage: new Map(),
        fitnessChart: null,
        diversityChart: null,
        entropyChart: null,
        mutationChart: null,
        operatorChart: null,
        landscapeChart: null,
        intraGenerationChart: null,
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
        chartState.intraGenerationChart?.destroy()

        chartState.fitnessChart = null
        chartState.diversityChart = null
        chartState.entropyChart = null
        chartState.mutationChart = null
        chartState.operatorChart = null
        chartState.landscapeChart = null
        chartState.intraGenerationChart = null
        chartState.seenGenerations = new Set()
        chartState.operatorUsage = new Map()
        chartState.executionId = null
        chartState.heartbeatIntervalId = null
        chartState.lastHeartbeatAt = null
        chartState.lastHeartbeatPhase = ""
        chartState.lastHeartbeatStage = ""
        chartState.lastHeartbeatOperationLabel = ""
        chartState.lastHeartbeatOperationElapsedSeconds = null
        chartState.lastHeartbeatIslandId = null
        chartState.lastHeartbeatGeneration = null
        chartState.lastHeartbeatLocalGeneration = null
        chartState.lastHeartbeatPopulationTarget = null
        chartState.lastHeartbeatOffspringBuilt = null
        chartState.incompleteGenerationSnapshotCount = 0
        chartState.ignoredMetricWarnings = new Set()
        chartState.ignoredMetricWarningCount = 0
        chartState.latestIgnoredMetricDiagnostic = null
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

    function createIntraGenerationChart(element) {
        return new Chart(element, {
            type: "line",
            data: {
                labels: [],
                datasets: [
                    {
                        label: "Descendentes (%)",
                        data: [],
                        yAxisID: "y",
                        borderColor: "#0f766e",
                        backgroundColor: "rgba(15, 118, 110, 0.18)",
                        pointRadius: 2,
                    },
                    {
                        label: "Tempo na etapa (s)",
                        data: [],
                        yAxisID: "y1",
                        borderColor: "#334155",
                        backgroundColor: "rgba(51, 65, 85, 0.18)",
                        pointRadius: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                animation: false,
                maintainAspectRatio: false,
                resizeDelay: 100,
                interaction: {
                    mode: "index",
                    intersect: false,
                },
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        ticks: {
                            callback(value) {
                                return `${value}%`
                            },
                        },
                    },
                    y1: {
                        min: 0,
                        position: "right",
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            callback(value) {
                                return `${value}s`
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
        1: "Exploracao",
        2: "Exploracao+",
        3: "Controlado",
        4: "Equilibrado",
        5: "Estagnacao I",
        6: "Estagnacao II",
        7: "Estagnacao III",
        8: "Convergencia",
    }

    const LANDSCAPE_PHENOMENON_LABELS = {
        neutral: "Neutro",
        plateau: "Plateau",
        local_minimum: "Minimo local",
        deep_valley: "Vale profundo",
    }

    const LANDSCAPE_EPISODE_EXIT_LABELS = {
        active: "Ativo",
        recovered: "Recuperado",
        phenomenon_shift: "Mudanca de fenomeno",
    }

    const LANDSCAPE_TREND_SEVERITY = {
        neutral: 0,
        plateau: 1,
        local_minimum: 2,
        deep_valley: 3,
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

    function landscapeEpisodeExitLabel(value) {
        if (typeof value !== "string" || value.trim() === "") {
            return "Ativo"
        }

        return LANDSCAPE_EPISODE_EXIT_LABELS[value.trim().toLowerCase()] ?? value
    }

    function resolveLandscapeTransition(currentEpisode, previousEpisode) {
        const currentPhenomenon = String(currentEpisode?.phenomenon ?? "")
        const previousPhenomenon = String(previousEpisode?.phenomenon ?? "")
        const previousExitMode = String(previousEpisode?.exit_mode ?? "")
        const previousLastGeneration = Number(previousEpisode?.last_generation ?? 0)
        const currentStartGeneration = Number(currentEpisode?.start_generation ?? 0)

        if (currentPhenomenon === "deep_valley") {
            return {
                badge: "Vale profundo ativo",
                badgeClassName: "inline-flex w-fit rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-900",
                detail: `O solver entrou em vale profundo e esta em episodio de alta intensidade desde a geracao ${currentStartGeneration}.`,
            }
        }

        if (
            previousPhenomenon !== "" &&
            currentPhenomenon !== "" &&
            previousExitMode === "phenomenon_shift" &&
            previousPhenomenon !== currentPhenomenon &&
            previousLastGeneration > 0
        ) {
            return {
                badge: "Mudanca de fenomeno",
                badgeClassName: "inline-flex w-fit rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900",
                detail: `O episodio ${landscapePhenomenonLabel(previousPhenomenon)} terminou na geracao ${previousLastGeneration} e a busca migrou para ${landscapePhenomenonLabel(currentPhenomenon)}.`,
            }
        }

        if (previousPhenomenon !== "" && previousExitMode === "recovered" && previousLastGeneration > 0) {
            return {
                badge: "Recuperacao detectada",
                badgeClassName: "inline-flex w-fit rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-900",
                detail: `O episodio ${landscapePhenomenonLabel(previousPhenomenon)} terminou por recuperacao na geracao ${previousLastGeneration}.`,
            }
        }

        if (currentPhenomenon !== "" && currentPhenomenon !== "neutral" && currentStartGeneration > 0) {
            return {
                badge: "Episodio monitorado",
                badgeClassName: "inline-flex w-fit rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-900",
                detail: `O episodio ${landscapePhenomenonLabel(currentPhenomenon)} segue em observacao desde a geracao ${currentStartGeneration}.`,
            }
        }

        return {
            badge: "Sem transicao destacada",
            badgeClassName: "inline-flex w-fit rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700",
            detail: "O dashboard destacara entradas em vale profundo, mudancas de fenomeno e recuperacoes assim que elas aparecerem.",
        }
    }

    function resolveLandscapeTrend(recentEpisodeHistory, currentEpisode, previousEpisode) {
        const completedHistory = Array.isArray(recentEpisodeHistory)
            ? recentEpisodeHistory.filter((episode) => episode && typeof episode === "object")
            : []

        const trendSequence = completedHistory.map((episode) => String(episode.phenomenon ?? "neutral"))
        const currentPhenomenon = String(currentEpisode?.phenomenon ?? "")
        const previousPhenomenon = String(previousEpisode?.phenomenon ?? "")
        const previousExitMode = String(previousEpisode?.exit_mode ?? "")

        if (currentPhenomenon !== "" && currentPhenomenon !== "neutral") {
            trendSequence.push(currentPhenomenon)
        }

        const normalizedSequence = trendSequence.slice(-3)
        const severitySequence = normalizedSequence
            .map((phenomenon) => LANDSCAPE_TREND_SEVERITY[phenomenon] ?? 0)

        if (
            normalizedSequence.length >= 3 &&
            severitySequence[0] < severitySequence[1] &&
            severitySequence[1] < severitySequence[2]
        ) {
            return {
                badge: "Tendencia de piora",
                badgeClassName: "inline-flex w-fit rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-900",
                detail: `Sequencia recente em agravamento: ${normalizedSequence.map((item) => landscapePhenomenonLabel(item)).join(" -> ")}.`,
            }
        }

        if (
            completedHistory.length >= 2 &&
            previousPhenomenon !== "" &&
            previousExitMode === "recovered"
        ) {
            const recentRecoveredSequence = [
                String(completedHistory.at(-2)?.phenomenon ?? ""),
                previousPhenomenon,
                "recovered",
            ].filter((item) => item !== "")

            return {
                badge: "Tendencia de melhora",
                badgeClassName: "inline-flex w-fit rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-900",
                detail: `Sequencia recente em recuperacao: ${recentRecoveredSequence.map((item) => item === "recovered" ? "Recuperado" : landscapePhenomenonLabel(item)).join(" -> ")}.`,
            }
        }

        if (
            normalizedSequence.length >= 2 &&
            severitySequence.at(-1) !== undefined &&
            severitySequence.at(-2) !== undefined &&
            severitySequence.at(-1) < severitySequence.at(-2)
        ) {
            return {
                badge: "Sinal de melhora",
                badgeClassName: "inline-flex w-fit rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-900",
                detail: `A sequencia recente reduziu a intensidade do landscape: ${normalizedSequence.map((item) => landscapePhenomenonLabel(item)).join(" -> ")}.`,
            }
        }

        if (
            normalizedSequence.length >= 2 &&
            severitySequence.at(-1) !== undefined &&
            severitySequence.at(-2) !== undefined &&
            severitySequence.at(-1) > severitySequence.at(-2)
        ) {
            return {
                badge: "Sinal de piora",
                badgeClassName: "inline-flex w-fit rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900",
                detail: `A sequencia recente aumentou a intensidade do landscape: ${normalizedSequence.map((item) => landscapePhenomenonLabel(item)).join(" -> ")}.`,
            }
        }

        return {
            badge: "Tendencia indefinida",
            badgeClassName: "inline-flex w-fit rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700",
            detail: "A tendencia entre episodios sera destacada quando houver sequencia suficiente para comparacao.",
        }
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
            thresholds = { monitoring: 30, warning: 120, critical: 300 }
        } else if (phase === "initial_population") {
            thresholds = { monitoring: 20, warning: 90, critical: 300 }
        } else if (phase === "evolving" || phase === "evolution") {
            thresholds = { monitoring: 30, warning: 120, critical: 300 }
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
        const operationDetails = []

        if (chartState.lastHeartbeatOperationLabel) {
            operationDetails.push(`Operacao atual: ${chartState.lastHeartbeatOperationLabel}.`)
        }

        if (Number.isFinite(chartState.lastHeartbeatOperationElapsedSeconds) && chartState.lastHeartbeatOperationElapsedSeconds > 0) {
            operationDetails.push(`Essa etapa esta em execucao ha ${formatElapsedSeconds(chartState.lastHeartbeatOperationElapsedSeconds)}.`)
        }
        const note = [severity.note, ...operationDetails].join(" ").trim()

        lastHeartbeatElement.textContent = chartState.lastHeartbeatAt.toLocaleString("pt-BR")
        delayElement.textContent = formatElapsedSeconds(ageSeconds)
        statusElement.textContent = severity.status
        noteElement.textContent = note
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
        chartState.lastHeartbeatOperationLabel = String(metric.operation_label ?? "")
        chartState.lastHeartbeatOperationElapsedSeconds = Number(metric.operation_elapsed_seconds ?? Number.NaN)
        chartState.lastHeartbeatIslandId = Number(metric.island_id ?? Number.NaN)
        chartState.lastHeartbeatGeneration = Number(metric.generation ?? Number.NaN)
        chartState.lastHeartbeatLocalGeneration = Number(metric.local_generation ?? Number.NaN)
        chartState.lastHeartbeatPopulationTarget = Number(metric.population_target ?? Number.NaN)
        chartState.lastHeartbeatOffspringBuilt = Number(metric.offspring_built ?? Number.NaN)

        const incompleteSnapshotCount = Number(metric.incomplete_generation_snapshot_count ?? Number.NaN)

        if (Number.isFinite(incompleteSnapshotCount) && incompleteSnapshotCount >= 0) {
            chartState.incompleteGenerationSnapshotCount = incompleteSnapshotCount
        }

        renderDashboardHeartbeatMonitor()
        renderIntraGenerationProgressPanel()
        renderIgnoredMetricDiagnostic()
        renderIncompleteSnapshotSummaryCard()
    }

    function renderIncompleteSnapshotSummaryCard() {
        const root = chartState.root

        if (!root) {
            return
        }

        const countElement = root.querySelector("[data-dashboard-incomplete-snapshot-count]")
        const noteElement = root.querySelector("[data-dashboard-incomplete-snapshot-note]")

        if (!countElement || !noteElement) {
            return
        }

        const backendCount = Number.isFinite(chartState.incompleteGenerationSnapshotCount)
            ? Math.max(0, Math.trunc(chartState.incompleteGenerationSnapshotCount))
            : 0
        const displayCount = Math.max(chartState.ignoredMetricWarningCount, backendCount)

        countElement.textContent = String(displayCount)
        noteElement.textContent = displayCount > 0
            ? `Curvas preservadas em ${displayCount} heartbeat${displayCount === 1 ? "" : "s"} incompleto${displayCount === 1 ? "" : "s"}.`
            : "Nenhum snapshot incompleto detectado."
    }

    function renderIntraGenerationProgressPanel() {
        const root = chartState.root

        if (!root) {
            return
        }

        const headline = root.querySelector("[data-intra-generation-headline]")
        const stage = root.querySelector("[data-intra-generation-stage]")
        const island = root.querySelector("[data-intra-generation-island]")
        const generation = root.querySelector("[data-intra-generation-generation]")
        const offspring = root.querySelector("[data-intra-generation-offspring]")
        const progress = root.querySelector("[data-intra-generation-progress]")
        const elapsed = root.querySelector("[data-intra-generation-elapsed]")

        if (!headline || !stage || !island || !generation || !offspring || !progress || !elapsed) {
            return
        }

        const hasHeartbeat = chartState.lastHeartbeatAt instanceof Date
            && !Number.isNaN(chartState.lastHeartbeatAt.getTime())

        const phase = String(chartState.lastHeartbeatPhase ?? "").trim().toLowerCase()
        const isEvolutionPhase = phase === "evolution" || phase === "evolving"

        if (!hasHeartbeat || !isEvolutionPhase) {
            headline.textContent = "Aguardando heartbeat de evolução"
            stage.textContent = "sem etapa"
            island.textContent = "-"
            generation.textContent = "-"
            offspring.textContent = "-"
            progress.textContent = "-"
            elapsed.textContent = "-"
            return
        }

        const normalizedStage = String(chartState.lastHeartbeatStage ?? "").replaceAll("_", " ").trim() || "etapa nao informada"
        const hasIsland = Number.isFinite(chartState.lastHeartbeatIslandId) && chartState.lastHeartbeatIslandId > 0
        const hasGeneration = Number.isFinite(chartState.lastHeartbeatGeneration) && chartState.lastHeartbeatGeneration >= 0
        const hasLocalGeneration = Number.isFinite(chartState.lastHeartbeatLocalGeneration) && chartState.lastHeartbeatLocalGeneration > 0
        const hasTarget = Number.isFinite(chartState.lastHeartbeatPopulationTarget) && chartState.lastHeartbeatPopulationTarget > 0
        const hasBuilt = Number.isFinite(chartState.lastHeartbeatOffspringBuilt) && chartState.lastHeartbeatOffspringBuilt >= 0
        const hasElapsed = Number.isFinite(chartState.lastHeartbeatOperationElapsedSeconds)
        const built = hasBuilt ? chartState.lastHeartbeatOffspringBuilt : 0
        const target = hasTarget ? chartState.lastHeartbeatPopulationTarget : 0
        const ratio = hasTarget ? Math.max(0, Math.min(1, built / target)) : null

        if (!hasTarget && !hasBuilt && !hasElapsed) {
            headline.textContent = "Aguardando métricas operacionais da evolução"
            stage.textContent = normalizedStage
            island.textContent = hasIsland ? `Ilha ${chartState.lastHeartbeatIslandId}` : "-"
            generation.textContent = hasGeneration
                ? (hasLocalGeneration
                    ? `Global ${chartState.lastHeartbeatGeneration} · Local ${chartState.lastHeartbeatLocalGeneration}`
                    : `Global ${chartState.lastHeartbeatGeneration}`)
                : "-"
            offspring.textContent = "-"
            progress.textContent = "-"
            elapsed.textContent = "-"

            return
        }

        stage.textContent = normalizedStage
        island.textContent = hasIsland ? `Ilha ${chartState.lastHeartbeatIslandId}` : "-"
        generation.textContent = hasGeneration
            ? (hasLocalGeneration
                ? `Global ${chartState.lastHeartbeatGeneration} · Local ${chartState.lastHeartbeatLocalGeneration}`
                : `Global ${chartState.lastHeartbeatGeneration}`)
            : "-"
        offspring.textContent = hasTarget ? `${built}/${target}` : (hasBuilt ? String(built) : "-")
        progress.textContent = ratio === null ? "-" : `${(ratio * 100).toFixed(0)}%`
        elapsed.textContent = Number.isFinite(chartState.lastHeartbeatOperationElapsedSeconds)
            ? formatElapsedSeconds(chartState.lastHeartbeatOperationElapsedSeconds)
            : "-"

        headline.textContent = chartState.lastHeartbeatOperationLabel
            ? chartState.lastHeartbeatOperationLabel
            : "Evoluindo população da geração atual"

        const chart = chartState.intraGenerationChart

        if (!chart) {
            return
        }

        const label = hasHeartbeat
            ? chartState.lastHeartbeatAt.toLocaleTimeString("pt-BR")
            : "--"
        const percentValue = ratio === null ? null : Number((ratio * 100).toFixed(2))
        const elapsedValue = hasElapsed
            ? Number(chartState.lastHeartbeatOperationElapsedSeconds.toFixed(2))
            : null
        const labels = chart.data.labels
        const percentDataset = chart.data.datasets[0].data
        const elapsedDataset = chart.data.datasets[1].data
        const lastIndex = labels.length - 1
        const lastLabel = lastIndex >= 0 ? labels[lastIndex] : null

        if (lastLabel === label) {
            percentDataset[lastIndex] = percentValue
            elapsedDataset[lastIndex] = elapsedValue
        } else {
            labels.push(label)
            percentDataset.push(percentValue)
            elapsedDataset.push(elapsedValue)
        }

        const maxPoints = 30

        while (labels.length > maxPoints) {
            labels.shift()
            percentDataset.shift()
            elapsedDataset.shift()
        }

        chart.update()
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

    function setInitialRiskBadgeState(element, text, severity) {
        if (!element) {
            return
        }

        const severityClasses = {
            neutral: ["border-slate-200", "bg-slate-50", "text-slate-700"],
            low: ["border-emerald-200", "bg-emerald-50", "text-emerald-800"],
            medium: ["border-amber-200", "bg-amber-50", "text-amber-900"],
            high: ["border-rose-200", "bg-rose-50", "text-rose-900"],
        }

        Object.values(severityClasses).flat().forEach((className) => {
            element.classList.remove(className)
        })

        if (!text) {
            element.classList.add("hidden")
            element.textContent = ""
            element.removeAttribute("title")

            return
        }

        const classes = severityClasses[severity] ?? severityClasses.neutral
        classes.forEach((className) => element.classList.add(className))
        element.classList.remove("hidden")
        element.textContent = text
        element.title = text
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

    function resolveNumericMetricValue(metric, keys) {
        for (const key of keys) {
            if (!Object.prototype.hasOwnProperty.call(metric, key)) {
                continue
            }

            const value = Number(metric[key])

            if (Number.isFinite(value)) {
                return value
            }
        }

        return null
    }

    function resolveGenerationMetricSnapshot(metric) {
        if (!metric || typeof metric !== "object") {
            return null
        }

        const generation = resolveNumericMetricValue(metric, ["generation"])
        const bestFitness = resolveNumericMetricValue(metric, ["best_fitness", "bestFitness"])
        const avgFitness = resolveNumericMetricValue(metric, ["avg_fitness", "avgFitness"])
        const diversity = resolveNumericMetricValue(metric, ["diversity"])
        const entropy = resolveNumericMetricValue(metric, ["entropy"])
        const mutationRate = resolveNumericMetricValue(metric, ["mutation_rate", "mutationRate"])

        if (
            generation === null ||
            bestFitness === null ||
            avgFitness === null ||
            diversity === null ||
            entropy === null ||
            mutationRate === null
        ) {
            return null
        }

        return {
            generation,
            bestFitness,
            avgFitness,
            diversity,
            entropy,
            mutationRate,
        }
    }

    function renderIgnoredMetricDiagnostic() {
        const root = chartState.root

        if (!root) {
            return
        }

        const panel = root.querySelector("[data-ignored-metric-panel]")
        const detail = root.querySelector("[data-ignored-metric-detail]")
        const count = root.querySelector("[data-ignored-metric-count]")

        if (!panel || !detail || !count) {
            return
        }

        renderIncompleteSnapshotSummaryCard()

        const backendCount = Number.isFinite(chartState.incompleteGenerationSnapshotCount)
            ? Math.max(0, Math.trunc(chartState.incompleteGenerationSnapshotCount))
            : 0
        const displayCount = Math.max(chartState.ignoredMetricWarningCount, backendCount)

        if (!chartState.latestIgnoredMetricDiagnostic && displayCount <= 0) {
            panel.classList.add("hidden")
            detail.textContent = "Aguardando diagnostico."
            count.textContent = "0 ocorrencias"
            return
        }

        if (!chartState.latestIgnoredMetricDiagnostic && displayCount > 0) {
            panel.classList.remove("hidden")
            detail.textContent = "Snapshots incompletos detectados nesta execucao. Aguardando proximo heartbeat com detalhes."
            count.textContent = `${displayCount} ocorrencia${displayCount === 1 ? "" : "s"}`
            return
        }

        const diagnostic = chartState.latestIgnoredMetricDiagnostic
        const stage = String(diagnostic.stage ?? "etapa nao informada").replaceAll("_", " ")
        const islandId = diagnostic.islandId ?? null
        const generation = diagnostic.generation ?? null
        const localGeneration = diagnostic.localGeneration ?? null
        const missingKeys = Array.isArray(diagnostic.missingKeys) ? diagnostic.missingKeys : []
        const detailParts = [
            `Etapa ${stage}`,
            islandId !== null ? `ilha ${islandId}` : null,
            generation !== null ? `geracao ${generation}` : null,
            localGeneration !== null ? `local ${localGeneration}` : null,
            missingKeys.length > 0 ? `campos ausentes: ${missingKeys.join(", ")}` : null,
        ].filter(Boolean)

        panel.classList.remove("hidden")
        detail.textContent = detailParts.join(" | ")
        count.textContent = `${displayCount} ocorrencia${displayCount === 1 ? "" : "s"}`
    }

    function clearIgnoredMetricDiagnostic() {
        chartState.ignoredMetricWarnings = new Set()
        chartState.ignoredMetricWarningCount = 0
        chartState.latestIgnoredMetricDiagnostic = null
        renderIgnoredMetricDiagnostic()
        renderIncompleteSnapshotSummaryCard()
    }

    function logIgnoredIncompleteGenerationMetric(metric) {
        if (!metric || typeof metric !== "object") {
            return
        }

        const phase = String(metric.phase ?? "unknown")

        if (!["evolution", "evolving", "alns_intensification"].includes(phase)) {
            return
        }

        const warningKey = JSON.stringify({
            executionId: metric.execution_id ?? metric.executionId ?? null,
            phase,
            stage: metric.stage ?? null,
            islandId: metric.island_id ?? null,
            generation: metric.generation ?? null,
            localGeneration: metric.local_generation ?? null,
            timestamp: metric.timestamp ?? metric.created_at ?? metric.updated_at ?? null,
        })

        if (chartState.ignoredMetricWarnings.has(warningKey)) {
            return
        }

        chartState.ignoredMetricWarnings.add(warningKey)
        chartState.ignoredMetricWarningCount += 1

        const metricKeys = [
            "generation",
            "best_fitness",
            "avg_fitness",
            "diversity",
            "entropy",
            "mutation_rate",
        ]
        const missingKeys = metricKeys.filter((key) => !Object.prototype.hasOwnProperty.call(metric, key))

        chartState.latestIgnoredMetricDiagnostic = {
            executionId: metric.execution_id ?? metric.executionId ?? null,
            phase,
            stage: metric.stage ?? null,
            islandId: metric.island_id ?? null,
            generation: metric.generation ?? null,
            localGeneration: metric.local_generation ?? null,
            missingKeys,
        }

        renderIgnoredMetricDiagnostic()

        console.info("[solver-dashboard] Heartbeat de evolucao ignorado pelas curvas por metrica incompleta", {
            executionId: metric.execution_id ?? metric.executionId ?? null,
            phase,
            stage: metric.stage ?? null,
            islandId: metric.island_id ?? null,
            generation: metric.generation ?? null,
            localGeneration: metric.local_generation ?? null,
            missingKeys,
            metric,
        })
    }

    function appendMetric(metric) {
        if (!metric || !chartState.fitnessChart) {
            return
        }

        updateDashboardHeartbeatMonitor(metric)

        if ((metric.phase ?? null) === "terminal") {
            updateTerminalExecutionSummary(metric)
            const terminalSummary = metric.terminal_summary ?? {}

            if (terminalSummary && typeof terminalSummary === "object") {
                updateInitialPopulationBottlenecks(
                    terminalSummary.initial_population_bottlenecks ?? {},
                )
            }

            return
        }

        if ((metric.phase ?? null) === "monitoring") {
            return
        }

        if ((metric.phase ?? null) === "initial_population") {
            updateInitialPopulationObservationReadable(metric)
            updateInitialPopulationBottlenecks(metric.initial_population_bottlenecks ?? {})
            return
        }

        const generationSnapshot = resolveGenerationMetricSnapshot(metric)

        if (!generationSnapshot) {
            logIgnoredIncompleteGenerationMetric(metric)
            return
        }

        const generation = generationSnapshot.generation

        if (chartState.seenGenerations.has(generation)) {
            return
        }

        clearIgnoredMetricDiagnostic()

        chartState.seenGenerations.add(generation)

        chartState.fitnessChart.data.labels.push(generation)
        chartState.fitnessChart.data.datasets[0].data.push(generationSnapshot.bestFitness)
        chartState.fitnessChart.data.datasets[1].data.push(generationSnapshot.avgFitness)

        chartState.diversityChart.data.labels.push(generation)
        chartState.diversityChart.data.datasets[0].data.push(generationSnapshot.diversity)

        chartState.entropyChart.data.labels.push(generation)
        chartState.entropyChart.data.datasets[0].data.push(generationSnapshot.entropy)

        chartState.mutationChart.data.labels.push(generation)
        chartState.mutationChart.data.datasets[0].data.push(generationSnapshot.mutationRate)

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

    function updateTerminalExecutionSummary(metric) {
        const root = chartState.root

        if (!root) {
            return
        }

        const panel = root.querySelector("[data-terminal-summary-panel]")

        if (!panel) {
            return
        }

        const summary = metric.terminal_summary && typeof metric.terminal_summary === "object"
            ? metric.terminal_summary
            : {}
        const title = root.querySelector("[data-terminal-summary-title]")
        const message = root.querySelector("[data-terminal-summary-message]")
        const status = root.querySelector("[data-terminal-summary-status]")
        const phase = root.querySelector("[data-terminal-summary-phase]")
        const stage = root.querySelector("[data-terminal-summary-stage]")
        const attempt = root.querySelector("[data-terminal-summary-attempt]")
        const queueSize = root.querySelector("[data-terminal-summary-queue-size]")
        const hardConflicts = root.querySelector("[data-terminal-summary-hard-conflicts]")
        const hardPenalty = root.querySelector("[data-terminal-summary-hard-penalty]")
        const reason = root.querySelector("[data-terminal-summary-reason]")
        const suggestions = root.querySelector("[data-terminal-summary-suggestions]")

        panel.classList.remove("hidden")

        if (title) {
            title.textContent = String(summary.title ?? "Execucao interrompida")
        }

        if (message) {
            message.textContent = String(summary.user_message ?? summary.reason ?? "A execucao foi encerrada.")
        }

        if (status) {
            status.textContent = String(metric.execution_status ?? summary.execution_status ?? "desconhecido").replaceAll("_", " ")
        }

        if (phase) {
            phase.textContent = String(summary.phase ?? metric.phase ?? "desconhecida").replaceAll("_", " ")
        }

        if (stage) {
            stage.textContent = String(summary.stage ?? "desconhecida").replaceAll("_", " ")
        }

        if (attempt) {
            attempt.textContent = summary.attempt ?? "-"
        }

        if (queueSize) {
            queueSize.textContent = summary.queue_size ?? "-"
        }

        if (hardConflicts) {
            hardConflicts.textContent = summary.hard_conflict_allocations ?? "-"
        }

        if (hardPenalty) {
            hardPenalty.textContent = summary.hard_penalty !== undefined && summary.hard_penalty !== null
                ? Number(summary.hard_penalty).toFixed(2)
                : "-"
        }

        if (reason) {
            reason.textContent = String(summary.reason ?? "Motivo tecnico indisponivel.")
        }

        if (suggestions) {
            const items = Array.isArray(summary.suggestions) ? summary.suggestions : []

            suggestions.innerHTML = items.length > 0
                ? items.map((item) => `
                <p class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2 text-sm text-rose-800">
                    ${String(item)}
                </p>
            `).join("")
                : `
                <p class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2 text-sm text-rose-800">
                    Nenhuma sugestao disponivel.
                </p>
            `
        }
    }

    function formatDurationMilliseconds(value) {
        const milliseconds = Number(value ?? 0)

        if (!Number.isFinite(milliseconds) || milliseconds <= 0) {
            return "--"
        }

        return formatElapsedSeconds(milliseconds / 1000)
    }

    function updateInitialPopulationBottlenecks(summary) {
        const root = chartState.root

        if (!root || !summary || typeof summary !== "object") {
            return
        }

        const headline = root.querySelector("[data-initial-bottlenecks-headline]")
        const failFastCount = root.querySelector("[data-bottleneck-fail-fast-count]")
        const gateRejections = root.querySelector("[data-bottleneck-gate-rejections]")
        const slowestAttempt = root.querySelector("[data-bottleneck-slowest-attempt]")
        const peakHardConflicts = root.querySelector("[data-bottleneck-peak-hard-conflicts]")
        const currentAttemptLimit = root.querySelector("[data-bottleneck-current-attempt-limit]")
        const currentAttemptLimitInline = root.querySelector("[data-bottleneck-current-attempt-limit-inline]")
        const attemptLimitBase = root.querySelector("[data-bottleneck-attempt-limit-base]")
        const attemptLimitStatus = root.querySelector("[data-bottleneck-attempt-limit-status]")
        const attemptLimitBadge = root.querySelector("[data-bottleneck-attempt-limit-badge]")
        const attemptLimitCriteria = root.querySelector("[data-bottleneck-attempt-limit-criteria]")
        const bottleneckList = root.querySelector("[data-bottleneck-list]")
        const suggestionList = root.querySelector("[data-bottleneck-suggestions]")

        if (
            !headline ||
            !failFastCount ||
            !gateRejections ||
            !slowestAttempt ||
            !peakHardConflicts ||
            !currentAttemptLimit ||
            !currentAttemptLimitInline ||
            !attemptLimitBase ||
            !attemptLimitStatus ||
            !attemptLimitBadge ||
            !attemptLimitCriteria ||
            !bottleneckList ||
            !suggestionList
        ) {
            return
        }

        headline.textContent = String(summary.headline ?? "Aguardando dados para identificar os gargalos da populacao inicial.")
        failFastCount.textContent = String(Number(summary.fail_fast_count ?? 0))
        gateRejections.textContent = String(Number(summary.quality_gate_rejections ?? 0))
        slowestAttempt.textContent = formatDurationMilliseconds(summary.slowest_attempt_ms)
        peakHardConflicts.textContent = String(Number(summary.peak_hard_conflict_allocations ?? 0))
        currentAttemptLimit.textContent = String(Number(summary.current_attempt_limit ?? 0))
        currentAttemptLimitInline.textContent = String(Number(summary.current_attempt_limit ?? 0))
        attemptLimitBase.textContent = String(Number(summary.base_attempt_limit ?? summary.current_attempt_limit ?? 0))

        const attemptLimitReduced = Boolean(summary.attempt_limit_reduced ?? false)
        const attemptLimitReductionCriteria = Array.isArray(summary.attempt_limit_reduction_criteria)
            ? summary.attempt_limit_reduction_criteria
            : []

        attemptLimitStatus.textContent = attemptLimitReduced
            ? "reduzido por degradacao"
            : "sem reducao"

        attemptLimitBadge.textContent = attemptLimitReduced
            ? "Reducao adaptativa ativa"
            : "Sem reducao por degradacao"
        attemptLimitBadge.className = attemptLimitReduced
            ? "inline-flex w-fit rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900"
            : "inline-flex w-fit rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700"

        attemptLimitCriteria.innerHTML = attemptLimitReductionCriteria.length > 0
            ? attemptLimitReductionCriteria.map((item) => `
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                ${String(item)}
            </p>
        `).join("")
            : `
            <p class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-500">
                O limite segue no valor base enquanto nao houver sinais suficientes de degradacao.
            </p>
        `

        const likelyBottlenecks = Array.isArray(summary.likely_bottlenecks) ? summary.likely_bottlenecks : []
        const optimizationSuggestions = Array.isArray(summary.optimization_suggestions) ? summary.optimization_suggestions : []

        bottleneckList.innerHTML = likelyBottlenecks.length > 0
            ? likelyBottlenecks.map((item) => `
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                ${String(item)}
            </p>
        `).join("")
            : `
            <p class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-500">
                Ainda nao ha gargalos consolidados para esta execucao.
            </p>
        `

        suggestionList.innerHTML = optimizationSuggestions.length > 0
            ? optimizationSuggestions.map((item) => `
            <p class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                ${String(item)}
            </p>
        `).join("")
            : `
            <p class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-500">
                As sugestoes aparecerao quando a execucao registrar tentativas suficientes.
            </p>
        `
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
        const hardBadgeElement = root.querySelector("[data-initial-hard-badge]")
        const invalidBadgeElement = root.querySelector("[data-initial-invalid-badge]")
        const penaltyBadgeElement = root.querySelector("[data-initial-penalty-badge]")

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
        ]

        if (repairEvent) {
            summaryParts.push(`Reparo passe ${repairPass || "-"}: ${translateRepairEvent(repairEvent)}`)
        }

        if (repairTotal > 0) {
            summaryParts.push(`Genes verificados ${repairProcessed}/${repairTotal}`)
        }

        if (message) {
            summaryParts.push(message)
        }

        stageElement.textContent = stage.replaceAll("_", " ")
        attemptElement.textContent = attempt > 0 ? String(attempt) : "-"
        fillRatioElement.textContent = `${(fillRatio * 100).toFixed(0)}%`
        summaryElement.textContent = summaryParts.join(" ?? ")
        summaryElement.title = summaryParts.join(" ?? ")

        const hardSeverity = hardConflictAllocations >= 10
            ? "high"
            : hardConflictAllocations >= 1
                ? "medium"
                : "low"
        const invalidSeverity = repairInvalidAfter >= 10
            ? "high"
            : repairInvalidAfter >= 1
                ? "medium"
                : "low"
        const numericHardPenalty = hardPenalty === null ? null : Number(hardPenalty)
        const penaltySeverity = numericHardPenalty !== null && numericHardPenalty >= 100
            ? "high"
            : numericHardPenalty !== null && numericHardPenalty > 0
                ? "medium"
                : "low"

        setInitialRiskBadgeState(
            hardBadgeElement,
            `Conflitos hard ${hardConflictAllocations}`,
            hardSeverity,
        )
        setInitialRiskBadgeState(
            invalidBadgeElement,
            repairInvalidAfter > 0 ? `Invalidos ${repairInvalidAfter}` : "Sem invalidos apos reparo",
            invalidSeverity,
        )
        setInitialRiskBadgeState(
            penaltyBadgeElement,
            numericHardPenalty !== null
                ? `Penalidade hard ${numericHardPenalty.toFixed(2)}`
                : "",
            penaltySeverity,
        )
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
        const currentEpisodeTitleElement = root.querySelector("[data-landscape-current-episode-title]")
        const currentEpisodeDetailElement = root.querySelector("[data-landscape-current-episode-detail]")
        const previousEpisodeTitleElement = root.querySelector("[data-landscape-previous-episode-title]")
        const previousEpisodeDetailElement = root.querySelector("[data-landscape-previous-episode-detail]")
        const transitionBadgeElement = root.querySelector("[data-landscape-transition-badge]")
        const transitionDetailElement = root.querySelector("[data-landscape-transition-detail]")
        const trendBadgeElement = root.querySelector("[data-landscape-trend-badge]")
        const trendDetailElement = root.querySelector("[data-landscape-trend-detail]")
        const transitionHistoryElement = root.querySelector("[data-landscape-transition-history]")
        const alnsBrakeBadgeElement = root.querySelector("[data-landscape-alns-brake-badge]")
        const alnsBrakeDetailElement = root.querySelector("[data-landscape-alns-brake-detail]")

        if (
            !phenomenonElement ||
            !confidenceElement ||
            !depthScoreElement ||
            !summaryElement ||
            !currentEpisodeTitleElement ||
            !currentEpisodeDetailElement ||
            !previousEpisodeTitleElement ||
            !previousEpisodeDetailElement ||
            !transitionBadgeElement ||
            !transitionDetailElement ||
            !trendBadgeElement ||
            !trendDetailElement ||
            !transitionHistoryElement ||
            !alnsBrakeBadgeElement ||
            !alnsBrakeDetailElement
        ) {
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
        const currentEpisode = normalizedObservation.current_episode ?? {}
        const previousEpisode = normalizedObservation.previous_episode ?? {}
        const recentEpisodeHistory = Array.isArray(normalizedObservation.recent_episode_history)
            ? normalizedObservation.recent_episode_history
            : []
        const mutationShock = normalizedObservation.mutation_shock ?? {}
        const selectionPressure = normalizedObservation.selection_pressure ?? {}
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
        const alnsBaseCooldownGenerations = Number(alnsTrigger.base_cooldown_generations ?? 0)
        const alnsCooldownGenerations = Number(alnsTrigger.cooldown_generations ?? 0)
        const alnsResponse = alnsTrigger.response ?? {}
        const alnsAggressionLabel = String(alnsResponse.aggression_label ?? "")
        const alnsDestroyRatio = Number(alnsResponse.destroy_ratio ?? 0)
        const alnsRecentSuccessRate = Number(alnsResponse.recent_success_rate ?? 0)
        const alnsCooldownBrake = alnsTrigger.cooldown_brake ?? {}
        const alnsCooldownBrakeApplied = Boolean(alnsCooldownBrake.applied ?? false)
        const alnsCooldownBrakeExtraGenerations = Number(alnsCooldownBrake.extra_generations ?? 0)
        const alnsCooldownBrakeReason = String(alnsCooldownBrake.reason ?? "")
        const alnsRecentEffectiveness = alnsTrigger.recent_effectiveness ?? {}
        const alnsRecentEffectivenessSampleSize = Number(alnsRecentEffectiveness.sample_size ?? 0)
        const alnsRecentEffectivenessMeanImprovement = Number(alnsRecentEffectiveness.mean_improvement ?? 0)
        const alnsRecentEffectivenessSuccessRate = Number(alnsRecentEffectiveness.success_rate ?? 0)
        const alnsRealActivation = alnsTrigger.real_activation ?? {}
        const alnsRealActivationApplied = Boolean(alnsRealActivation.applied ?? false)
        const alnsRealActivationPolicy = String(alnsRealActivation.policy ?? "")
        const mutationShockApplied = Boolean(mutationShock.applied ?? false)
        const mutationShockActive = Boolean(mutationShock.active ?? false)
        const mutationShockMultiplier = Number(mutationShock.active_multiplier ?? mutationShock.multiplier ?? 0)
        const mutationShockRemaining = Number(mutationShock.remaining_generations_after ?? 0)
        const selectionPressureSupported = Boolean(selectionPressure.supported ?? false)
        const selectionPressureState = String(selectionPressure.state ?? "nominal")
        const selectionPressureEffectiveMultiplier = Number(selectionPressure.effective_multiplier ?? 1)
        const selectionPressureTournamentSize = Number(selectionPressure.effective_tournament_size ?? 0)
        const selectionPressureReductionActive = Boolean(selectionPressure.reduction_active ?? false)
        const selectionPressureReductionMultiplier = Number(selectionPressure.reduction_multiplier ?? 0)
        const selectionPressureReductionRemaining = Number(selectionPressure.remaining_generations_after ?? 0)
        const selectionPressureRealReduction = selectionPressure.real_reduction ?? {}
        const selectionPressureRealReductionApplied = Boolean(selectionPressureRealReduction.applied ?? false)
        const selectionPressureRealReductionPolicy = String(selectionPressureRealReduction.policy ?? "")

        phenomenonElement.textContent = landscapePhenomenonLabel(phenomenon)
        confidenceElement.textContent = confidence.toFixed(2)
        depthScoreElement.textContent = depthScore.toFixed(2)

        const brakeBadgeClassName = alnsCooldownBrakeApplied
            ? "inline-flex w-fit rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900"
            : "inline-flex w-fit rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700"

        const brakeDetailParts = []

        if (alnsCooldownBrakeApplied) {
            brakeDetailParts.push(`Cooldown ampliado em +${alnsCooldownBrakeExtraGenerations} geracoes`)
            if (alnsBaseCooldownGenerations > 0 || alnsCooldownGenerations > 0) {
                brakeDetailParts.push(`janela ${alnsBaseCooldownGenerations} -> ${alnsCooldownGenerations}`)
            }
        } else if (alnsCooldownGenerations > 0) {
            brakeDetailParts.push(
                alnsCooldownGenerations === 1
                    ? `Cooldown atual ${alnsCooldownGenerations} geracao`
                    : `Cooldown atual ${alnsCooldownGenerations} geracoes`,
            )
        } else {
            brakeDetailParts.push("Sem freio adaptativo ativo no momento")
        }

        if (alnsRecentEffectivenessSampleSize > 0) {
            brakeDetailParts.push(`retorno medio ${alnsRecentEffectivenessMeanImprovement.toFixed(2)}`)
            brakeDetailParts.push(`sucesso ${(alnsRecentEffectivenessSuccessRate * 100).toFixed(0)}% em ${alnsRecentEffectivenessSampleSize} amostras`)
        }

        if (alnsCooldownBrakeApplied && alnsCooldownBrakeReason !== "") {
            brakeDetailParts.push(alnsCooldownBrakeReason)
        }

        alnsBrakeBadgeElement.textContent = alnsCooldownBrakeApplied
            ? `Freio ativo +${alnsCooldownBrakeExtraGenerations}g`
            : "Sem freio adaptativo"
        alnsBrakeBadgeElement.className = brakeBadgeClassName
        alnsBrakeDetailElement.textContent = brakeDetailParts.join(" | ")

        const currentEpisodePhenomenon = String(currentEpisode.phenomenon ?? "")
        const currentEpisodeStart = Number(currentEpisode.start_generation ?? 0)
        const currentEpisodeLast = Number(currentEpisode.last_generation ?? 0)
        const currentEpisodePeakConfidence = Number(currentEpisode.peak_confidence ?? 0)
        const currentEpisodePeakDepthScore = Number(currentEpisode.peak_depth_score ?? 0)
        const currentEpisodeStableSignatureRate = Number(currentEpisode.stable_best_signature_rate ?? 0)
        const currentEpisodeAvgTurnover = Number(currentEpisode.avg_population_turnover ?? 0)
        const currentEpisodeAvgEliteSimilarity = Number(currentEpisode.avg_elite_similarity ?? 0)

        if (currentEpisodePhenomenon !== "") {
            currentEpisodeTitleElement.textContent = `${landscapePhenomenonLabel(currentEpisodePhenomenon)} em curso`
            currentEpisodeDetailElement.textContent = `Inicio g${currentEpisodeStart} | ultimo g${currentEpisodeLast} | duracao ${episodeDuration} geracoes | pico confianca ${currentEpisodePeakConfidence.toFixed(2)} | pico profundidade ${currentEpisodePeakDepthScore.toFixed(2)} | assinatura estavel ${(currentEpisodeStableSignatureRate * 100).toFixed(0)}% | turnover medio ${(currentEpisodeAvgTurnover * 100).toFixed(0)}% | elite media ${currentEpisodeAvgEliteSimilarity.toFixed(2)}`
        } else {
            currentEpisodeTitleElement.textContent = "Nenhum episodio ativo ainda"
            currentEpisodeDetailElement.textContent = "O dashboard exibira inicio, duracao e intensidade maxima do episodio atual."
        }

        const previousEpisodePhenomenon = String(previousEpisode.phenomenon ?? "")
        const previousEpisodeStart = Number(previousEpisode.start_generation ?? 0)
        const previousEpisodeLast = Number(previousEpisode.last_generation ?? 0)
        const previousEpisodeDuration = Number(previousEpisode.duration ?? 0)
        const previousEpisodePeakConfidence = Number(previousEpisode.peak_confidence ?? 0)
        const previousEpisodePeakDepthScore = Number(previousEpisode.peak_depth_score ?? 0)
        const previousEpisodeExitMode = String(previousEpisode.exit_mode ?? "")

        if (previousEpisodePhenomenon !== "") {
            previousEpisodeTitleElement.textContent = `${landscapePhenomenonLabel(previousEpisodePhenomenon)} encerrado`
            previousEpisodeDetailElement.textContent = `Inicio g${previousEpisodeStart} | fim g${previousEpisodeLast} | duracao ${previousEpisodeDuration} geracoes | pico confianca ${previousEpisodePeakConfidence.toFixed(2)} | pico profundidade ${previousEpisodePeakDepthScore.toFixed(2)} | saiu por ${landscapeEpisodeExitLabel(previousEpisodeExitMode)}`
        } else {
            previousEpisodeTitleElement.textContent = "Nenhum episodio encerrado ainda"
            previousEpisodeDetailElement.textContent = "Quando um episodio terminar, o dashboard exibira como ele terminou e qual foi o pico de intensidade."
        }

        const transition = resolveLandscapeTransition(currentEpisode, previousEpisode)
        transitionBadgeElement.textContent = transition.badge
        transitionBadgeElement.className = transition.badgeClassName
        transitionDetailElement.textContent = transition.detail

        const persistedTrend = normalizedObservation.episode_trend && typeof normalizedObservation.episode_trend === "object"
            ? normalizedObservation.episode_trend
            : null
        const trend = persistedTrend
            ? {
                badge: String(persistedTrend.headline ?? "Tendencia indefinida"),
                badgeClassName: (() => {
                    const direction = String(persistedTrend.direction ?? "indeterminate")
                    const strength = String(persistedTrend.strength ?? "none")

                    if (direction === "worsening" && strength === "strong") {
                        return "inline-flex w-fit rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-900"
                    }

                    if (direction === "improving" && strength === "strong") {
                        return "inline-flex w-fit rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-900"
                    }

                    if (direction === "worsening") {
                        return "inline-flex w-fit rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900"
                    }

                    if (direction === "improving") {
                        return "inline-flex w-fit rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-900"
                    }

                    return "inline-flex w-fit rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700"
                })(),
                detail: (() => {
                    const sequence = Array.isArray(persistedTrend.sequence) ? persistedTrend.sequence : []
                    const readableSequence = sequence.length > 0
                        ? sequence
                            .map((item) => item === "recovered" ? "Recuperado" : landscapePhenomenonLabel(String(item)))
                            .join(" -> ")
                        : ""
                    const detail = String(persistedTrend.detail ?? "")

                    return readableSequence !== ""
                        ? `${detail} Sequencia: ${readableSequence}.`
                        : detail
                })(),
            }
            : resolveLandscapeTrend(recentEpisodeHistory, currentEpisode, previousEpisode)
        trendBadgeElement.textContent = trend.badge
        trendBadgeElement.className = trend.badgeClassName
        trendDetailElement.textContent = trend.detail

        transitionHistoryElement.innerHTML = recentEpisodeHistory.length > 0
            ? [...recentEpisodeHistory]
                .reverse()
                .map((episode) => {
                    const historyPhenomenon = landscapePhenomenonLabel(String(episode.phenomenon ?? "neutral"))
                    const historyExitMode = landscapeEpisodeExitLabel(String(episode.exit_mode ?? "active"))
                    const historyStart = Number(episode.start_generation ?? 0)
                    const historyLast = Number(episode.last_generation ?? 0)
                    const historyDuration = Number(episode.duration ?? 0)
                    const historyPeakDepth = Number(episode.peak_depth_score ?? 0)
                    const historyPeakConfidence = Number(episode.peak_confidence ?? 0)

                    return `
                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900">${historyPhenomenon}</p>
                        <p class="mt-1">g${historyStart} -> g${historyLast} | duracao ${historyDuration} geracoes | pico confianca ${historyPeakConfidence.toFixed(2)} | pico profundidade ${historyPeakDepth.toFixed(2)}</p>
                        <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">Saida: ${historyExitMode}</p>
                    </div>
                `
                })
                .join("")
            : `
            <p class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-500">
                O historico recente sera preenchido quando os episodios comecarem a encerrar.
            </p>
        `

        const line1 = `Episodio ${episodeDuration}: melhora recente ${bestDeltaWindow.toFixed(3)}${searchResponseWouldEscalate ? ` (meta ${auditTargetBestDeltaWindow.toFixed(3)})` : ""}.`
        const line2Parts = [
            `Turnover ${(populationTurnover * 100).toFixed(0)}%${searchResponseWouldEscalate ? ` (meta ${(auditTargetPopulationTurnover * 100).toFixed(0)}%)` : ""}`,
            `Similaridade da elite ${eliteSimilarity.toFixed(2)}`,
            basinLockDetected ? `Basin lock ${basinLockConfidence.toFixed(2)}` : null,
            bestSignatureChanged ? "Assinatura da melhor solução mudou" : null,
        ].filter(Boolean)
        const line3Parts = [
            selectionPressureSupported
                ? `Selecao x${selectionPressureEffectiveMultiplier.toFixed(2)}${selectionPressureTournamentSize > 0 ? ` (torneio ${selectionPressureTournamentSize})` : ""}${selectionPressureState !== "nominal" ? `, estado ${selectionPressureState}` : ""}`
                : null,
            selectionPressureRealReductionApplied
                ? `Reducao ativa por ${selectionPressureRealReductionPolicy || "gate"}`
                : null,
            selectionPressureReductionActive
                ? `Reducao temporaria x${selectionPressureReductionMultiplier.toFixed(2)} por ${selectionPressureReductionRemaining} geracoes`
                : null,
            alnsEffectiveFrequency > 0
                ? `ALNS a cada ${alnsEffectiveFrequency} geracoes${alnsTriggered ? ` (${alnsTriggerReason || "trigger"})` : ""}`
                : null,
            alnsCooldownBrakeApplied
                ? `Freio do ALNS +${alnsCooldownBrakeExtraGenerations} geracoes`
                : null,
            alnsRealActivationApplied
                ? `Ativacao real do ALNS via ${alnsRealActivationPolicy || "gate"}`
                : null,
            mutationShockApplied
                ? `Choque de mutacao armado x${mutationShockMultiplier.toFixed(2)}`
                : null,
            mutationShockActive
                ? `Choque de mutacao ativo x${mutationShockMultiplier.toFixed(2)} por mais ${mutationShockRemaining} geracoes`
                : null,
            alnsAggressionLabel
                ? `Agressividade ALNS ${alnsAggressionLabel}${alnsDestroyRatio > 0 ? ` (destruicao ${(alnsDestroyRatio * 100).toFixed(0)}%)` : ""}${alnsRecentSuccessRate > 0 ? `, sucesso ${(alnsRecentSuccessRate * 100).toFixed(0)}%` : ""}`
                : null,
            searchResponseWouldEscalate
                ? `Plano de resposta: ${searchResponsePolicy}`
                : null,
            normalizedObservation.search_response_outcome
                ? `Resultado da resposta: ${searchResponseOutcomeSatisfied ? "alvo atingido" : "alvo parcial"} (progresso ${searchResponseOutcomeProgress.toFixed(2)})`
                : null,
            effectivenessTotalResolved > 0
                ? `Historico: melhor por sucesso ${effectivenessBestBySuccess || "-"}, melhor por progresso ${effectivenessBestByProgress || "-"} (${effectivenessTotalResolved} casos)`
                : null,
            activationEligible
                ? `Candidata pronta para ativacao: ${activationCandidate}`
                : null,
            searchResponsePendingAudits > 0
                ? `Auditorias pendentes: ${searchResponsePendingAudits}`
                : null,
        ].filter(Boolean)
        const line2 = line2Parts.length > 0
            ? line2Parts.join(" | ") + "."
            : "Sem sinais adicionais de risco no landscape."
        const line3 = line3Parts.length > 0
            ? line3Parts.join(" | ") + "."
            : null

        summaryElement.textContent = [line1, line2, line3].filter(Boolean).join("\n")
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
        const headline = String(readiness.headline ?? "Nenhuma evidencia de prontidao coletada ainda")
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
            : "Somente diagnostico"
        gateCandidateElement.textContent = activationGate?.candidate_policy
            ? `Candidata: ${activationGate.candidate_policy}`
            : String(activationGate?.reason ?? "Nenhuma candidata ainda")

        bestOutcomeElement.textContent = bestBySuccess?.policy
            ? `${bestBySuccess.policy} | sucesso ${(Number(bestBySuccess.success_rate ?? 0) * 100).toFixed(0)}%`
            : "Evidencia insuficiente"
        bestProgressElement.textContent = bestByProgress?.policy
            ? `${bestByProgress.policy} | progresso ${Number(bestByProgress.avg_progress_score ?? 0).toFixed(2)}`
            : "Progresso indisponivel"

        latestOutcomeElement.textContent = latestOutcome?.policy
            ? `${latestOutcome.policy} | ${Boolean(latestOutcome.targets_satisfied) ? "alvos atingidos" : "alvos nao atingidos"}`
            : "Nenhum resultado resolvido ainda"
        latestOutcomeDetailElement.textContent = latestOutcome?.policy
            ? `Progresso ${Number(latestOutcome.progress_score ?? 0).toFixed(2)} | geracao resolvida ${Number(latestOutcome.resolved_generation ?? 0)}`
            : "Aguardando o primeiro horizonte expirar"

        if (policyRows.length === 0) {
            policyRowsElement.innerHTML = `
            <tr>
                <td colspan="5" class="py-4 text-sm text-slate-500">Nenhuma politica avaliada ainda.</td>
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
        summaryElement.textContent = summaryParts.join(" ?? ")
        summaryElement.title = summaryParts.join(" ?? ")
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
        const executionStatusContext = window.executionStatusContext && typeof window.executionStatusContext === "object"
            ? window.executionStatusContext
            : {}

        metrics.forEach((metric) => {
            appendMetric(metric)
        })

        if (executionStatusContext.initial_population_bottlenecks) {
            updateInitialPopulationBottlenecks(executionStatusContext.initial_population_bottlenecks)
        }

        if (Object.keys(executionStatusContext).length > 0) {
            updateTerminalExecutionSummary({
                phase: "terminal",
                execution_status: executionStatusContext.execution_status ?? null,
                terminal_summary: executionStatusContext,
                timestamp: executionStatusContext.last_progress_timestamp ?? executionStatusContext.captured_at ?? null,
            })
        }

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
        const intraGenerationCanvas = root.querySelector("#intraGenerationChart")

        if (!fitnessCanvas || !diversityCanvas || !entropyCanvas || !mutationCanvas || !operatorCanvas || !landscapeCanvas) {
            return
        }

        destroyCharts()

        chartState.root = root
        chartState.executionId = window.executionId ?? null
        ensureHeartbeatTicker()
        chartState.fitnessChart = createMultiLineChart(fitnessCanvas, ["Melhor fitness", "Fitness medio"])
        chartState.diversityChart = createLineChart(diversityCanvas, "Diversidade")
        chartState.entropyChart = createLineChart(entropyCanvas, "Entropia")
        chartState.mutationChart = createLineChart(mutationCanvas, "Taxa de mutacao")
        chartState.operatorChart = createBarChart(operatorCanvas, "Recompensa media por operador")
        chartState.landscapeChart = createBubbleChart(landscapeCanvas, "Estado do landscape")

        if (intraGenerationCanvas) {
            chartState.intraGenerationChart = createIntraGenerationChart(intraGenerationCanvas)
        }

        loadInitialMetrics()
        renderDashboardHeartbeatMonitor()
        renderIntraGenerationProgressPanel()
        renderIgnoredMetricDiagnostic()
    }

    function processMetricEventPayload(detail, retryAttempt = 0) {
        let metric = null

        try {
            metric = normalizeMetricEvent(detail)

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
        } catch (error) {
            const message = error instanceof Error ? error.message : String(error)
            const stack = error instanceof Error ? error.stack : null
            console.error("[solver-dashboard] Falha ao processar metrics-update", {
                retryAttempt,
                message,
                stack,
                detail,
            })

            if (retryAttempt < 1) {
                window.setTimeout(() => {
                    processMetricEventPayload(detail, retryAttempt + 1)
                }, 150)
            }
        }
    }

    function handleMetricEvent(event) {
        processMetricEventPayload(event?.detail)
    }

    window.addEventListener("unhandledrejection", (event) => {
        const reason = event?.reason
        const stack = reason instanceof Error ? reason.stack : null

        console.error("[solver-dashboard] Unhandled Promise Rejection", {
            reason,
            stack,
        })
    })

    document.addEventListener("DOMContentLoaded", initializeDashboard)
    document.addEventListener("livewire:navigated", initializeDashboard)
    window.addEventListener("metrics-update", handleMetricEvent)
    document.addEventListener("metrics-update", handleMetricEvent)
}
