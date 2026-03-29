<div data-solver-dashboard>

    <div class="mb-6">

        <h2 class="text-2xl font-bold text-gray-900">
            Execucao do Solver #{{ $execution->id }}
        </h2>

        <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="w-full max-w-md">
                <label for="execution-selector" class="text-sm font-medium text-slate-700">Trocar execucao</label>
                <select
                    id="execution-selector"
                    wire:model.live="selectedExecutionId"
                    class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    @foreach ($executionOptions as $option)
                        <option value="{{ $option['id'] }}" wire:key="execution-option-{{ $option['id'] }}">
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4 xl:grid-cols-6">

            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Status</p>
                <p class="text-lg font-bold">{{ $executionInfo['status'] }}</p>
            </div>

            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Populacao</p>
                <p class="text-lg font-bold">{{ $executionInfo['population'] }}</p>
            </div>

            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Ilhas</p>
                <p class="text-lg font-bold">{{ $executionInfo['islands'] }}</p>
            </div>

            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Melhor fitness</p>
                <p class="text-lg font-bold">{{ number_format((float) ($executionInfo['bestFitness'] ?? 0), 4) }}</p>
            </div>

            <div class="bg-white shadow rounded p-4" data-dashboard-last-heartbeat-card>
                <p class="text-sm text-gray-500">Ultimo heartbeat</p>
                <p class="text-lg font-bold" data-dashboard-last-heartbeat>Sem heartbeat ainda</p>
                <p class="mt-1 text-xs text-slate-500" data-dashboard-heartbeat-status>Aguardando primeiro sinal</p>
            </div>

            <div class="bg-white shadow rounded p-4" data-dashboard-heartbeat-delay-card>
                <p class="text-sm text-gray-500">Atraso atual</p>
                <p class="text-lg font-bold" data-dashboard-heartbeat-delay>--</p>
                <p class="mt-1 text-xs text-slate-500" data-dashboard-heartbeat-note>O contador atualiza sozinho entre os heartbeats.</p>
                <div class="mt-3 hidden" data-dashboard-log-link-wrapper>
                    <a
                        href="{{ route('algoritmo.execution.logs', ['execution' => $execution->id]) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center rounded-md border border-current/20 px-3 py-1 text-xs font-semibold transition hover:opacity-80"
                        data-dashboard-log-link>
                        Abrir logs desta execucao
                    </a>
                </div>
            </div>

        </div>

        @if (in_array($execution->status, ['running', 'cancel_requested'], true))
            <div class="mt-4 flex justify-end">
                <button
                    wire:click="cancelExecution"
                    wire:confirm="Deseja solicitar o cancelamento desta execucao?"
                    class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    Cancelar execucao
                </button>
            </div>
        @endif

        @if (session('success'))
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {{ session('success') }}
            </div>
        @endif

        @if ($execution->status === 'cancel_requested')
            <div class="mt-4 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900">
                Cancelamento solicitado. O solver sera interrompido assim que atingir um ponto seguro.
            </div>
        @endif

        @if ($execution->status === 'cancelled')
            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800">
                Execucao cancelada pelo usuario.
            </div>
        @endif

        @if (in_array($execution->status, ['finished', 'completed', 'concluida', 'concluida_com_sucesso'], true))
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-6 text-center">
                <h3 class="text-lg font-semibold text-green-800">Geracao concluida!</h3>
                <p class="mt-2 text-green-700">O horario foi gerado com sucesso e a melhor solucao foi salva.</p>
                <a href="{{ route('horarios.show', ['horario' => $execution->horario_id]) }}"
                    class="mt-4 inline-block rounded-lg bg-blue-600 px-6 py-2 font-bold text-white shadow-md transition-transform hover:scale-105 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75"
                    wire:navigate>
                    Visualizar horario gerado ->
                </a>
            </div>
        @endif

    </div>

    @livewire(\App\Modules\AG\UI\Livewire\ExecutionStatusPanel::class, ['execution' => $execution], key('execution-status-' . $execution->id))
    @livewire(\App\Modules\AG\UI\Livewire\ExecutionMetricsStream::class, ['executionId' => $execution->id, 'horarioId' => $execution->horario_id], key('execution-metrics-' . $execution->id))

    <div class="grid grid-cols-2 gap-6">

        <div class="col-span-2 rounded bg-white p-4 shadow">
            <h3 class="mb-2 font-semibold">Populacao inicial</h3>
            <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2 xl:grid-cols-[0.9fr_0.55fr_0.65fr_2.9fr]">
                <div>
                    <p class="text-gray-500">Etapa</p>
                    <p class="font-semibold" data-initial-stage>Aguardando</p>
                </div>
                <div>
                    <p class="text-gray-500">Tentativa</p>
                    <p class="font-semibold" data-initial-attempt>-</p>
                </div>
                <div>
                    <p class="text-gray-500">Preenchimento</p>
                    <p class="font-semibold" data-initial-fill-ratio>0%</p>
                </div>
                <div class="min-w-0 md:col-span-2 xl:col-span-1 xl:overflow-x-auto">
                    <p class="text-gray-500">Resumo operacional</p>
                    <p
                        class="font-semibold leading-6 text-slate-800 md:text-[13px] xl:whitespace-nowrap"
                        data-initial-summary
                        title="Aguardando progresso da populacao inicial"
                    >
                        Aguardando progresso da populacao inicial
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2" data-initial-risk-badges>
                        <span
                            class="hidden rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700"
                            data-initial-hard-badge
                        ></span>
                        <span
                            class="hidden rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700"
                            data-initial-invalid-badge
                        ></span>
                        <span
                            class="hidden rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700"
                            data-initial-penalty-badge
                        ></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-2 hidden rounded-xl border border-rose-200 bg-rose-50 p-5 shadow" data-terminal-summary-panel>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-rose-700">Encerramento da execucao</p>
                    <h3 class="mt-1 text-lg font-semibold text-rose-900" data-terminal-summary-title>Execucao interrompida</h3>
                    <p class="mt-2 text-sm text-rose-800" data-terminal-summary-message>
                        O dashboard ainda nao recebeu um resumo terminal desta execucao.
                    </p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2 text-xs text-rose-800">
                    <p>Status: <span class="font-semibold" data-terminal-summary-status>desconhecido</span></p>
                    <p class="mt-1">Fase: <span class="font-semibold" data-terminal-summary-phase>desconhecida</span></p>
                    <p class="mt-1">Etapa: <span class="font-semibold" data-terminal-summary-stage>desconhecida</span></p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-4">
                <div class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-rose-600">Tentativa</p>
                    <p class="mt-1 font-semibold text-rose-900" data-terminal-summary-attempt>-</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-rose-600">Fila</p>
                    <p class="mt-1 font-semibold text-rose-900" data-terminal-summary-queue-size>-</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-rose-600">Conflitos hard</p>
                    <p class="mt-1 font-semibold text-rose-900" data-terminal-summary-hard-conflicts>-</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-rose-600">Penalidade hard</p>
                    <p class="mt-1 font-semibold text-rose-900" data-terminal-summary-hard-penalty>-</p>
                </div>
            </div>

            <div class="mt-4">
                <p class="font-semibold text-rose-900">Motivo tecnico</p>
                <p class="mt-1 text-sm text-rose-800" data-terminal-summary-reason>
                    Motivo tecnico indisponivel.
                </p>
            </div>

            <div class="mt-4">
                <p class="font-semibold text-rose-900">Sugestoes</p>
                <div class="mt-2 space-y-2" data-terminal-summary-suggestions>
                    <p class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2 text-sm text-rose-800">
                        Nenhuma sugestao disponivel.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-span-2 rounded-xl border border-slate-200 bg-white p-5 shadow" data-initial-bottlenecks-panel>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Gargalos da populacao inicial</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">Diagnostico da construcao inicial</h3>
                    <p class="mt-2 text-sm text-slate-600" data-initial-bottlenecks-headline>
                        Aguardando dados para identificar os gargalos da populacao inicial.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3 text-xs lg:min-w-[20rem]">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-wide text-slate-500">Fail-fast</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900" data-bottleneck-fail-fast-count>0</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-wide text-slate-500">Rejeicoes do gate</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900" data-bottleneck-gate-rejections>0</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-wide text-slate-500">Tentativa mais lenta</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900" data-bottleneck-slowest-attempt>--</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-wide text-slate-500">Pico de conflitos hard</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900" data-bottleneck-peak-hard-conflicts>0</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Principais gargalos</p>
                    <div class="mt-2 space-y-2" data-bottleneck-list>
                        <p class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-500">
                            Ainda nao ha gargalos consolidados para esta execucao.
                        </p>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-900">Sugestoes de melhoria</p>
                    <div class="mt-2 space-y-2" data-bottleneck-suggestions>
                        <p class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-500">
                            As sugestoes aparecerao quando a execucao registrar tentativas suficientes.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-2 rounded bg-white p-4 shadow">
            <h3 class="mb-2 font-semibold">Observacao do landscape</h3>
            <div class="grid grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-500">Fenomeno</p>
                    <p class="font-semibold" data-landscape-phenomenon>Neutro</p>
                </div>
                <div>
                    <p class="text-gray-500">Confianca</p>
                    <p class="font-semibold" data-landscape-confidence>0.00</p>
                </div>
                <div>
                    <p class="text-gray-500">Profundidade</p>
                    <p class="font-semibold" data-landscape-depth-score>0.00</p>
                </div>
                <div>
                    <p class="text-gray-500">Resumo</p>
                    <p class="font-semibold" data-landscape-summary>Nenhuma observacao ainda</p>
                </div>
            </div>
        </div>

        <div class="col-span-2 overflow-hidden rounded-2xl border border-slate-200 bg-linear-to-br from-slate-50 via-white to-emerald-50 shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Prontidao da resposta de busca</h3>
                        <p class="text-sm text-slate-600" data-sr-readiness-headline>
                            Nenhuma evidencia de prontidao coletada ainda
                        </p>
                    </div>
                    <div class="inline-flex items-center rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold tracking-wide text-white" data-sr-readiness-status>
                        ocioso
                    </div>
                </div>
            </div>

            <div class="grid gap-4 px-6 py-5 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Evidencia</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900" data-sr-evidence-count>0</p>
                    <p class="mt-1 text-sm text-slate-600">
                        Auditorias pendentes:
                        <span class="font-semibold text-slate-900" data-sr-pending-audits>0</span>
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Status do gate</p>
                    <p class="mt-3 text-base font-semibold text-slate-900" data-sr-gate-status>Somente diagnostico</p>
                    <p class="mt-1 text-sm text-slate-600" data-sr-gate-candidate>Nenhuma candidata ainda</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Melhor resultado</p>
                    <p class="mt-3 text-base font-semibold text-slate-900" data-sr-best-outcome>Evidencia insuficiente</p>
                    <p class="mt-1 text-sm text-slate-600" data-sr-best-progress>Progresso indisponivel</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Ultimo resultado</p>
                    <p class="mt-3 text-base font-semibold text-slate-900" data-sr-latest-outcome>Nenhum resultado resolvido ainda</p>
                    <p class="mt-1 text-sm text-slate-600" data-sr-latest-outcome-detail>Aguardando o primeiro horizonte expirar</p>
                </div>
            </div>

            <div class="grid gap-4 border-t border-slate-200 px-6 py-5 lg:grid-cols-[1.1fr_0.9fr]">
                <div class="rounded-xl border border-slate-200 bg-white/90 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h4 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">Politicas</h4>
                        <span class="text-xs text-slate-500">Efetividade em shadow mode</span>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                                <tr>
                                    <th class="pb-2 pr-4 font-medium">Politica</th>
                                    <th class="pb-2 pr-4 font-medium">Resolvidas</th>
                                    <th class="pb-2 pr-4 font-medium">Sucesso</th>
                                    <th class="pb-2 pr-4 font-medium">Aproximacao</th>
                                    <th class="pb-2 font-medium">Progresso</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100" data-sr-policy-rows>
                                <tr>
                                    <td colspan="5" class="py-4 text-sm text-slate-500">Nenhuma politica avaliada ainda.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white/90 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h4 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">Motivos de bloqueio</h4>
                        <span class="text-xs text-slate-500">Antes da ativacao real</span>
                    </div>
                    <div class="mt-4 space-y-2" data-sr-blocking-reasons>
                        <p class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-500">
                            Nenhum motivo de bloqueio ainda.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded bg-white p-4 shadow">
            <h3 class="mb-2 font-semibold">Curva de fitness</h3>
            <div class="relative h-72">
                <canvas id="fitnessChart" class="h-full w-full"></canvas>
            </div>
        </div>

        <div class="rounded bg-white p-4 shadow">
            <h3 class="mb-2 font-semibold">Curva de diversidade</h3>
            <div class="relative h-72">
                <canvas id="diversityChart" class="h-full w-full"></canvas>
            </div>
        </div>

        <div class="rounded bg-white p-4 shadow">
            <h3 class="mb-2 font-semibold">Curva de entropia</h3>
            <div class="relative h-72">
                <canvas id="entropyChart" class="h-full w-full"></canvas>
            </div>
        </div>

        <div class="rounded bg-white p-4 shadow">
            <h3 class="mb-2 font-semibold">Taxa de mutacao</h3>
            <div class="relative h-72">
                <canvas id="mutationChart" class="h-full w-full"></canvas>
            </div>
        </div>

        <div class="col-span-2 rounded bg-white p-4 shadow">
            <h3 class="mb-2 font-semibold">Uso de operadores</h3>
            <div class="relative h-80">
                <canvas id="operatorChart" class="h-full w-full"></canvas>
            </div>
        </div>

        <div class="col-span-2 rounded bg-white p-4 shadow">
            <h3 class="mb-2 font-semibold">Estado do landscape</h3>
            <div class="relative h-96">
                <canvas id="landscapeChart" class="h-full w-full"></canvas>
            </div>
        </div>

    </div>

    <script>
        window.executionId = @js($execution->id);
        window.solverMetrics = @json($metrics);
        window.executionStatusContext = @json($statusContext);
    </script>
</div>
