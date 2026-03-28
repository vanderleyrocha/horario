<div wire:poll.10s class="mx-auto max-w-7xl space-y-8 py-10">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Solver Center</p>
            <h1 class="mt-2 text-3xl font-semibold text-slate-950">
                {{ $horario->nome }}
            </h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">
                Acompanhe a prontidao historica das policies simuladas do SLAE entre execucoes diferentes antes de habilitar qualquer reacao automatica.
            </p>
        </div>

        <div class="inline-flex items-center rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-700">
            {{ str_replace('_', ' ', (string) ($historicalReadinessReport['status'] ?? 'idle')) }}
        </div>
    </div>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Readiness Historico</p>
                    <h2 class="mt-1 text-xl font-semibold text-slate-950">Comparacao entre execucoes</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ $historicalReadinessReport['headline'] ?? 'No executions available for historical readiness comparison.' }}
                    </p>
                </div>

                @php
                    $bestExecutionBySuccess = $historicalReadinessReport['best_execution_by_success'] ?? null;
                    $bestExecutionByProgress = $historicalReadinessReport['best_execution_by_progress'] ?? null;
                @endphp

                <div class="grid gap-3 text-sm text-slate-600 lg:min-w-[24rem] lg:grid-cols-2">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Melhor por sucesso</p>
                        <p class="mt-2 text-sm font-semibold text-emerald-950">
                            @if (is_array($bestExecutionBySuccess))
                                Execucao #{{ $bestExecutionBySuccess['execution_id'] }} · {{ $bestExecutionBySuccess['policy'] ?? '-' }}
                            @else
                                Sem evidencias suficientes
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-emerald-800">
                            @if (is_array($bestExecutionBySuccess))
                                Taxa de sucesso {{ number_format(((float) ($bestExecutionBySuccess['success_rate'] ?? 0)) * 100, 1) }}%
                            @else
                                Aguarde outcomes resolvidos
                            @endif
                        </p>
                    </div>

                    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700">Melhor por progresso</p>
                        <p class="mt-2 text-sm font-semibold text-sky-950">
                            @if (is_array($bestExecutionByProgress))
                                Execucao #{{ $bestExecutionByProgress['execution_id'] }} · {{ $bestExecutionByProgress['policy'] ?? '-' }}
                            @else
                                Sem evidencias suficientes
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-sky-800">
                            @if (is_array($bestExecutionByProgress))
                                Progresso medio {{ number_format((float) ($bestExecutionByProgress['avg_progress_score'] ?? 0), 2) }}
                            @else
                                Aguarde outcomes resolvidos
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 px-6 py-6 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Execucoes comparadas</p>
                <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $historicalReadinessReport['compared_executions_count'] ?? 0 }}</p>
                <p class="mt-1 text-sm text-slate-600">Base usada para consolidar readiness entre runs.</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Com sinal de readiness</p>
                <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $historicalReadinessReport['executions_with_readiness'] ?? 0 }}</p>
                <p class="mt-1 text-sm text-slate-600">Execucoes com evidence, pending audits ou gate analitico.</p>
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Candidates prontos</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-950">{{ $historicalReadinessReport['candidate_ready_executions'] ?? 0 }}</p>
                <p class="mt-1 text-sm text-emerald-800">Execucoes cujo gate ja apontaria policy candidata real.</p>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">Policies vistas</p>
                <p class="mt-2 text-3xl font-semibold text-amber-950">{{ count($historicalReadinessReport['policy_rows'] ?? []) }}</p>
                <p class="mt-1 text-sm text-amber-800">Policies comparadas na trilha shadow do SLAE.</p>
            </div>
        </div>

        <div class="grid gap-6 border-t border-slate-200 px-6 py-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-950">Comparativo por policy</p>
                        <p class="text-xs text-slate-500">Evidencia acumulada entre execucoes.</p>
                    </div>
                </div>

                @if (empty($historicalReadinessReport['policy_rows']))
                    <p class="px-4 py-5 text-sm text-slate-500">Nenhuma policy historica consolidada ainda.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Policy</th>
                                    <th class="px-4 py-3">Execucoes</th>
                                    <th class="px-4 py-3">Candidates</th>
                                    <th class="px-4 py-3">Success</th>
                                    <th class="px-4 py-3">Progress</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach (($historicalReadinessReport['policy_rows'] ?? []) as $policyRow)
                                    <tr class="align-top">
                                        <td class="px-4 py-3 font-medium text-slate-900">{{ $policyRow['policy'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $policyRow['executions_seen'] ?? 0 }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $policyRow['candidate_ready_executions'] ?? 0 }}</td>
                                        <td class="px-4 py-3 text-slate-600">
                                            {{ number_format(((float) ($policyRow['avg_success_rate'] ?? 0)) * 100, 1) }}%
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">
                                            {{ number_format((float) ($policyRow['avg_progress_score'] ?? 0), 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <p class="text-sm font-semibold text-slate-950">Como ler este painel</p>
                <div class="mt-4 space-y-3 text-sm text-slate-600">
                    <p>
                        <span class="font-medium text-slate-900">Evidence acumulada</span> mostra quantas execucoes ja produziram shadow outcomes resolvidos para readiness.
                    </p>
                    <p>
                        <span class="font-medium text-slate-900">Gate status</span> indica se uma policy ja teria evidencias suficientes para virar candidata real.
                    </p>
                    <p>
                        <span class="font-medium text-slate-900">Best/ultimo outcome</span> ajuda a comparar se a policy esta entregando sucesso consistente ou so progresso parcial.
                    </p>
                    <p>
                        <span class="font-medium text-slate-900">Bloqueios</span> tornam auditavel o motivo de ainda nao ativarmos automaticamente nenhuma resposta adaptativa.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Policy Readiness Global</p>
                    <h2 class="mt-1 text-xl font-semibold text-slate-950">Readiness agregado por policy</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ $globalPolicyReadinessReport['headline'] ?? 'No executions available for global policy readiness analysis.' }}
                    </p>
                </div>

                @php
                    $leadingPolicyByNearGate = $globalPolicyReadinessReport['leading_policy_by_near_gate'] ?? null;
                    $leadingPolicyByCandidateReady = $globalPolicyReadinessReport['leading_policy_by_candidate_ready'] ?? null;
                @endphp

                <div class="grid gap-3 text-sm text-slate-600 lg:min-w-[24rem] lg:grid-cols-2">
                    <div class="rounded-2xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-fuchsia-700">Mais perto do gate</p>
                        <p class="mt-2 text-sm font-semibold text-fuchsia-950">
                            @if (is_array($leadingPolicyByNearGate))
                                {{ $leadingPolicyByNearGate['policy'] ?? '-' }}
                            @else
                                Sem policy lider ainda
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-fuchsia-800">
                            @if (is_array($leadingPolicyByNearGate))
                                {{ $leadingPolicyByNearGate['near_gate_occurrences'] ?? 0 }} ocorrencias near gate
                            @else
                                Aguarde mais execucoes com telemetry de readiness
                            @endif
                        </p>
                    </div>

                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Mais pronta para ativacao</p>
                        <p class="mt-2 text-sm font-semibold text-emerald-950">
                            @if (is_array($leadingPolicyByCandidateReady))
                                {{ $leadingPolicyByCandidateReady['policy'] ?? '-' }}
                            @else
                                Nenhuma policy pronta ainda
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-emerald-800">
                            @if (is_array($leadingPolicyByCandidateReady))
                                {{ $leadingPolicyByCandidateReady['candidate_ready_occurrences'] ?? 0 }} candidates ready
                            @else
                                Ainda em modo exclusivamente diagnostico
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 px-6 py-6 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Janela analisada</p>
                <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $globalPolicyReadinessReport['window_executions_count'] ?? 0 }}</p>
                <p class="mt-1 text-sm text-slate-600">Ultimas execucoes usadas no agregado global.</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Policies consolidadas</p>
                <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $globalPolicyReadinessReport['policies_count'] ?? 0 }}</p>
                <p class="mt-1 text-sm text-slate-600">Policies com sinais suficientes para comparacao.</p>
            </div>

            <div class="rounded-2xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-fuchsia-700">Status global</p>
                <p class="mt-2 text-2xl font-semibold text-fuchsia-950">
                    {{ str_replace('_', ' ', (string) ($globalPolicyReadinessReport['status'] ?? 'idle')) }}
                </p>
                <p class="mt-1 text-sm text-fuchsia-800">Resume se o agregado ja mostra sinal de near gate ou candidate ready.</p>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">Contexto dominante</p>
                <p class="mt-2 text-sm font-semibold text-amber-950">
                    @if (is_array($leadingPolicyByNearGate))
                        {{ $leadingPolicyByNearGate['top_landscape_state'] ?? '-' }}
                        @if (!empty($leadingPolicyByNearGate['top_landscape_phenomenon']))
                            / {{ $leadingPolicyByNearGate['top_landscape_phenomenon'] }}
                        @endif
                    @else
                        Sem contexto dominante ainda
                    @endif
                </p>
                <p class="mt-1 text-sm text-amber-800">Paisagem mais comum quando a policy lider se aproxima do gate real.</p>
            </div>
        </div>

        <div class="overflow-x-auto border-t border-slate-200">
            @if (empty($globalPolicyReadinessReport['policy_rows']))
                <p class="px-6 py-8 text-sm text-slate-500">Nenhuma policy consolidada ainda para o painel global.</p>
            @else
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Policy</th>
                            <th class="px-4 py-3">Near gate</th>
                            <th class="px-4 py-3">Candidate ready</th>
                            <th class="px-4 py-3">Landscape comum</th>
                            <th class="px-4 py-3">Sinais medios</th>
                            <th class="px-4 py-3">Bloqueio tipico</th>
                            <th class="px-4 py-3">Amostra</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach (($globalPolicyReadinessReport['policy_rows'] ?? []) as $policyRow)
                            <tr class="align-top">
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-950">{{ $policyRow['policy'] ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $policyRow['executions_seen'] ?? 0 }} execucoes
                                        · success {{ number_format(((float) ($policyRow['avg_success_rate'] ?? 0)) * 100, 1) }}%
                                        · progress {{ number_format((float) ($policyRow['avg_progress_score'] ?? 0), 2) }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    <p class="font-medium text-slate-900">{{ $policyRow['near_gate_occurrences'] ?? 0 }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        evidence {{ number_format((float) ($policyRow['avg_near_gate_resolved_evidence'] ?? 0), 2) }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    <p class="font-medium text-emerald-800">{{ $policyRow['candidate_ready_occurrences'] ?? 0 }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $policyRow['blocked_near_gate_occurrences'] ?? 0 }} bloqueadas antes do gate
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    <p>{{ $policyRow['top_landscape_state'] ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $policyRow['top_landscape_phenomenon'] ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    <p>bLock {{ number_format((float) ($policyRow['avg_basin_lock_confidence'] ?? 0), 2) }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        depth {{ number_format((float) ($policyRow['avg_depth_score'] ?? 0), 2) }}
                                        · turnover {{ number_format(((float) ($policyRow['avg_population_turnover'] ?? 0)) * 100, 0) }}%
                                        · elite {{ number_format((float) ($policyRow['avg_elite_similarity'] ?? 0), 2) }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    <p class="max-w-xs text-xs leading-5 text-slate-500">
                                        {{ $policyRow['top_blocking_reason'] ?? 'Sem bloqueio dominante' }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    <p class="text-xs text-slate-500">
                                        {{ collect($policyRow['sample_execution_ids'] ?? [])->map(fn ($id) => '#' . $id)->implode(', ') ?: '-' }}
                                    </p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    <div class="grid gap-8 xl:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Nova Execucao</p>
            <h2 class="mt-2 text-xl font-semibold text-slate-950">Iniciar solver</h2>
            <p class="mt-2 text-sm text-slate-600">
                Dispare uma nova execucao para aumentar a base historica do readiness e comparar a consistencia das policies simuladas.
            </p>

            <div class="mt-6">
                <button wire:click="runSolver" class="inline-flex items-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Executar Solver
                </button>
            </div>
        </div>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Execucoes Recentes</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-950">Auditoria historica por execucao</h2>
            </div>

            @if (empty($historicalReadinessReport['executions']))
                <p class="px-6 py-8 text-sm text-slate-500">Nenhuma execucao registrada ainda.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Execucao</th>
                                <th class="px-4 py-3">Solver</th>
                                <th class="px-4 py-3">Readiness</th>
                                <th class="px-4 py-3">Evidence</th>
                                <th class="px-4 py-3">Gate</th>
                                <th class="px-4 py-3">Best/Ultimo outcome</th>
                                <th class="px-4 py-3">Bloqueios</th>
                                <th class="px-4 py-3">Acoes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @foreach (($historicalReadinessReport['executions'] ?? []) as $executionRow)
                                @php
                                    $solverStatus = strtolower((string) ($executionRow['execution_status'] ?? 'idle'));
                                    $readinessStatus = strtolower((string) ($executionRow['readiness_status'] ?? 'idle'));
                                    $latestOutcome = $executionRow['latest_outcome'] ?? null;
                                    $blockingReasons = array_slice($executionRow['blocking_reasons'] ?? [], 0, 2);
                                    $hiddenBlockingReasons = max(count($executionRow['blocking_reasons'] ?? []) - count($blockingReasons), 0);
                                    $bestPolicySuccess = $executionRow['best_policy_by_success'] ?? null;
                                    $candidatePolicy = $executionRow['candidate_policy'] ?? null;
                                @endphp
                                <tr class="align-top">
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-slate-950">#{{ $executionRow['execution_id'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $executionRow['start_time'] ?? '-' }}
                                            @if (!empty($executionRow['latest_generation']))
                                                · g{{ $executionRow['latest_generation'] }}
                                            @endif
                                        </p>
                                        <p class="mt-2 text-xs text-slate-500">
                                            Fitness {{ isset($executionRow['best_fitness']) ? number_format((float) $executionRow['best_fitness'], 4) : '-' }}
                                        </p>
                                    </td>

                                    <td class="px-4 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                            'bg-emerald-100 text-emerald-800' => in_array($solverStatus, ['completed', 'finished', 'concluida', 'concluida_com_sucesso'], true),
                                            'bg-amber-100 text-amber-800' => in_array($solverStatus, ['running', 'cancel_requested', 'em_execucao'], true),
                                            'bg-rose-100 text-rose-800' => in_array($solverStatus, ['failed', 'error', 'falhou'], true),
                                            'bg-slate-200 text-slate-800' => ! in_array($solverStatus, ['completed', 'finished', 'concluida', 'concluida_com_sucesso', 'running', 'cancel_requested', 'em_execucao', 'failed', 'error', 'falhou'], true),
                                        ])>
                                            {{ ucfirst((string) ($executionRow['execution_status'] ?? 'idle')) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                            'bg-emerald-100 text-emerald-800' => $readinessStatus === 'candidate_ready',
                                            'bg-sky-100 text-sky-800' => in_array($readinessStatus, ['evidence_positive', 'collecting_evidence'], true),
                                            'bg-slate-200 text-slate-700' => ! in_array($readinessStatus, ['candidate_ready', 'evidence_positive', 'collecting_evidence'], true),
                                        ])>
                                            {{ str_replace('_', ' ', (string) ($executionRow['readiness_status'] ?? 'idle')) }}
                                        </span>
                                        <p class="mt-2 max-w-xs text-xs text-slate-500">
                                            {{ $executionRow['readiness_headline'] ?? 'Sem sinal historico.' }}
                                        </p>
                                    </td>

                                    <td class="px-4 py-4 text-slate-600">
                                        <p>{{ $executionRow['resolved_evidence_count'] ?? 0 }} resolved</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $executionRow['pending_audits'] ?? 0 }} pending</p>
                                    </td>

                                    <td class="px-4 py-4 text-slate-600">
                                        @if (($executionRow['candidate_eligible'] ?? false) === true)
                                            <p class="font-medium text-emerald-800">{{ $candidatePolicy ?? 'candidate' }}</p>
                                            <p class="mt-1 text-xs text-emerald-700">Pronto para avaliacao humana</p>
                                        @else
                                            <p class="font-medium text-slate-700">{{ $candidatePolicy ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Ainda bloqueado para ativacao real</p>
                                        @endif
                                    </td>

                                    <td class="px-4 py-4 text-slate-600">
                                        <p class="font-medium text-slate-900">
                                            {{ $bestPolicySuccess['policy'] ?? '-' }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Success {{ number_format(((float) ($bestPolicySuccess['success_rate'] ?? 0)) * 100, 1) }}%
                                        </p>

                                        @if (is_array($latestOutcome))
                                            <p class="mt-3 text-xs font-medium text-slate-700">
                                                Ultimo outcome: {{ $latestOutcome['policy'] ?? '-' }}
                                            </p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ (($latestOutcome['targets_satisfied'] ?? false) === true) ? 'Targets hit' : 'Targets miss' }}
                                                · score {{ number_format((float) ($latestOutcome['progress_score'] ?? 0), 2) }}
                                            </p>
                                        @endif
                                    </td>

                                    <td class="px-4 py-4">
                                        @if (empty($blockingReasons))
                                            <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                                Sem bloqueios ativos
                                            </span>
                                        @else
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($blockingReasons as $blockingReason)
                                                    <span class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-medium text-rose-700">
                                                        {{ $blockingReason }}
                                                    </span>
                                                @endforeach

                                                @if ($hiddenBlockingReasons > 0)
                                                    <span class="inline-flex rounded-full bg-slate-200 px-3 py-1 text-xs font-medium text-slate-700">
                                                        +{{ $hiddenBlockingReasons }} outros
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-4 py-4">
                                        <div class="flex flex-col gap-2">
                                            <a href="{{ route('algoritmo.execution', $executionRow['execution_id']) }}" class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
                                                Abrir Dashboard
                                            </a>

                                            @if (in_array($solverStatus, ['running', 'cancel_requested'], true))
                                                <button
                                                    wire:click="cancelExecution({{ $executionRow['execution_id'] }})"
                                                    wire:confirm="Deseja solicitar o cancelamento desta execucao?"
                                                    class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-500">
                                                    Cancelar
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</div>
