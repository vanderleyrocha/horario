@php
    $stats = $this->overviewStats();
    $ultimaExecucao = $stats['ultima_execucao'];
    $execucoesRecentes = $stats['execucoes_recentes'];
    $saude = $stats['saude'];
    $riskBadgeClasses = match ($saude['nivel_risco']) {
        'CRITICO' => 'bg-rose-100 text-rose-700',
        'ALTO' => 'bg-amber-100 text-amber-700',
        'MODERADO' => 'bg-yellow-100 text-yellow-700',
        'BAIXO' => 'bg-sky-100 text-sky-700',
        default => 'bg-emerald-100 text-emerald-700',
    };
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-sm font-medium text-slate-500">Status</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ ucfirst($horario->status) }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ $horario->ano }}/{{ $horario->semestre }}</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-sm font-medium text-slate-500">Aulas cadastradas</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['aulas']['total_registros'] }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ $stats['aulas']['total_ativas'] }} ativas no horário</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-sm font-medium text-slate-500">Carga semanal</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['aulas']['total_aulas_semana'] }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ $stats['aulas']['total_tempos'] }} tempos necessários</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-sm font-medium text-slate-500">Execuções do solver</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['execucoes']['total'] }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ $stats['execucoes']['concluidas'] }} concluídas</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.4fr_1fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Cobertura do horário</p>
                    <h3 class="mt-1 text-xl font-semibold text-slate-900">Resumo estrutural</h3>
                </div>
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                    {{ $stats['alocacoes'] }} alocações atuais
                </span>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-sm text-slate-500">Turmas envolvidas</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['aulas']['total_turmas'] }}</p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-sm text-slate-500">Professores envolvidos</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['aulas']['total_professores'] }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-sm text-slate-500">Disciplinas relacionadas</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['aulas']['total_disciplinas'] }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-sm text-slate-500">Restrições cadastradas</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['restricoes'] }}</p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-sm text-slate-500">Melhor fitness atual</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">
                        {{ $horario->fitness_score !== null ? number_format($horario->fitness_score, 2, ',', '.') . '%' : '-' }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-sm text-slate-500">Tempo total processado</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['tempo_processamento'] }}</p>
                </div>
            </div>

            <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                Horário de {{ $horario->ano }}/{{ $horario->semestre }} com {{ $stats['aulas']['total_registros'] }}
                aulas distribuídas entre
                {{ $stats['aulas']['total_turmas'] }} turmas e {{ $stats['aulas']['total_professores'] }} professores.
                A carga semanal consolidada demanda {{ $stats['aulas']['total_tempos'] }} tempos para alocação.
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Saúde do cronograma</p>
                        <h4 class="mt-1 text-lg font-semibold text-slate-900">Risco e conflitos estruturais</h4>
                    </div>

                    <span class="{{ $riskBadgeClasses }} rounded-full px-3 py-1 text-xs font-semibold">
                        {{ $saude['nivel_risco'] }}
                    </span>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Índice de risco</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $saude['indice_risco'] }}/100</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Conflitos hard</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $saude['conflitos_hard'] }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Conflitos soft</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $saude['conflitos_soft'] }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Total de conflitos</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $saude['total_conflitos'] }}</p>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Turmas críticas</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $saude['turmas_criticas'] }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Professores críticos</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $saude['professores_criticos'] }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Aulas duplas problemáticas</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $saude['aulas_duplas_problema'] }}</p>
                    </div>
                </div>

                <div class="mt-5 rounded-xl bg-white p-4 text-sm text-slate-700">
                    @if ($saude['diagnostico_disponivel'])
                        O último diagnóstico estrutural apontou risco
                        <strong>{{ strtolower($saude['nivel_risco']) }}</strong>
                        com saturação global de
                        <strong>{{ $saude['saturacao_global'] !== null ? number_format((float) $saude['saturacao_global'], 2, ',', '.') . '%' : '-' }}</strong>.
                        Atualmente o horário acumula <strong>{{ $saude['conflitos_hard'] }}</strong> conflitos hard e
                        <strong>{{ $saude['conflitos_soft'] }}</strong> conflitos soft.
                    @else
                        Ainda não há resumo diagnóstico persistido para este horário. Os indicadores acima refletem os
                        conflitos e o índice de risco salvos no próprio registro do horário.
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Solver</p>
            <h3 class="mt-1 text-xl font-semibold text-slate-900">Execuções e desempenho</h3>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-1">
                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-sm text-slate-500">Execuções concluídas</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['execucoes']['concluidas'] }}</p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-sm text-slate-500">Em execução</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['execucoes']['em_execucao'] }}</p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-sm text-slate-500">Execuções com falha</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['execucoes']['falhas'] }}</p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-sm text-slate-500">Melhor fitness em execuções</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">
                        {{ $stats['execucoes']['melhor_fitness'] !== null ? number_format($stats['execucoes']['melhor_fitness'], 2, ',', '.') . '%' : '-' }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-sm text-slate-500">Fitness médio</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">
                        {{ $stats['execucoes']['media_fitness'] !== null ? number_format($stats['execucoes']['media_fitness'], 2, ',', '.') . '%' : '-' }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-sm text-slate-500">Tempo médio por execução</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['execucoes']['tempo_medio'] }}</p>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Última execução</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">
                            {{ $ultimaExecucao['status'] ?? 'Nenhuma execução registrada' }}
                        </p>
                    </div>

                    @if ($ultimaExecucao)
                        <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-700">
                            #{{ $ultimaExecucao['id'] }}
                        </span>
                    @endif
                </div>

                @if ($ultimaExecucao)
                    <dl class="mt-4 grid grid-cols-1 gap-3 text-sm text-slate-700 sm:grid-cols-2">
                        <div>
                            <dt class="text-slate-500">Iniciada em</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $ultimaExecucao['iniciada_em'] ?? '-' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-slate-500">Finalizada em</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $ultimaExecucao['finalizada_em'] ?? '-' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-slate-500">Tempo de execução</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $ultimaExecucao['tempo_execucao'] }}</dd>
                        </div>

                        <div>
                            <dt class="text-slate-500">Best fitness</dt>
                            <dd class="mt-1 font-medium text-slate-900">
                                {{ $ultimaExecucao['best_fitness'] !== null ? number_format($ultimaExecucao['best_fitness'], 2, ',', '.') . '%' : '-' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-slate-500">Gerações</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $ultimaExecucao['generations'] ?? '-' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-slate-500">População / ilhas</dt>
                            <dd class="mt-1 font-medium text-slate-900">
                                {{ ($ultimaExecucao['population_size'] ?? '-') . ' / ' . ($ultimaExecucao['island_count'] ?? '-') }}
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="mt-3 text-sm text-slate-600">
                        Este horário ainda não possui execuções registradas do solver.
                    </p>
                @endif
            </div>

            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Execuções recentes</p>
                        <p class="mt-1 text-sm text-slate-600">As 5 execuções mais recentes deste horário</p>
                    </div>

                    <a
                        href="{{ route('algoritmo.center', $horario) }}"
                        wire:navigate
                        class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800"
                    >
                        Abrir solver
                    </a>
                </div>

                @if ($execucoesRecentes !== [])
                    <div class="mt-4 space-y-3">
                        @foreach ($execucoesRecentes as $execucao)
                            @php
                                $statusClasses = match ($execucao['status_raw'] ?? null) {
                                    'running', 'cancel_requested' => 'bg-blue-100 text-blue-700',
                                    'failed' => 'bg-rose-100 text-rose-700',
                                    'finished',
                                    'completed',
                                    'concluida',
                                    'concluida_com_sucesso'
                                        => 'bg-emerald-100 text-emerald-700',
                                    'cancelled' => 'bg-slate-200 text-slate-700',
                                    default => 'bg-slate-200 text-slate-700',
                                };
                            @endphp

                            <a
                                href="{{ route('algoritmo.execution', ['execution' => $execucao['id']]) }}"
                                wire:navigate
                                class="block rounded-xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:bg-slate-50"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-slate-900">Execução
                                                #{{ $execucao['id'] }}</span>
                                            <span
                                                class="{{ $statusClasses }} rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                            >
                                                {{ $execucao['status'] }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-600">
                                            Iniciada em {{ $execucao['iniciada_em'] ?? '-' }}
                                        </p>
                                    </div>

                                    <span class="text-xs font-medium text-slate-500">ver detalhes</span>
                                </div>

                                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm text-slate-700 lg:grid-cols-4">
                                    <div>
                                        <dt class="text-slate-500">Tempo</dt>
                                        <dd class="mt-1 font-medium text-slate-900">{{ $execucao['tempo_execucao'] }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt class="text-slate-500">Best fitness</dt>
                                        <dd class="mt-1 font-medium text-slate-900">
                                            {{ $execucao['best_fitness'] !== null ? number_format($execucao['best_fitness'], 2, ',', '.') . '%' : '-' }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt class="text-slate-500">Gerações</dt>
                                        <dd class="mt-1 font-medium text-slate-900">
                                            {{ $execucao['generations'] ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt class="text-slate-500">População / ilhas</dt>
                                        <dd class="mt-1 font-medium text-slate-900">
                                            {{ ($execucao['population_size'] ?? '-') . ' / ' . ($execucao['island_count'] ?? '-') }}
                                        </dd>
                                    </div>
                                </dl>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="mt-4 text-sm text-slate-600">
                        Nenhuma execução recente encontrada. Use a aba Algoritmo para iniciar a primeira execução.
                    </p>
                @endif
            </div>
        </section>
    </div>
</div>
