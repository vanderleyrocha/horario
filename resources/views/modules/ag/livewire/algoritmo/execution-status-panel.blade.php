@php
    $heartbeatMonitor = $this->heartbeatMonitor;
    $executionSummary = $this->executionSummary;
@endphp

<div wire:poll.2s="refreshState" class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-5">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Monitor de execucao</p>
            <h3 class="mt-1 text-lg font-semibold text-slate-900">Andamento do solver</h3>
            <p class="mt-1 text-sm text-slate-600">
                Status atual: <span class="font-medium text-slate-900">{{ $this->translatedStatus }}</span>
                @if ($this->elapsed)
                    <span class="mx-2 text-slate-400">&bull;</span>
                    Tempo decorrido: <span class="font-medium text-slate-900">{{ $this->elapsed }}</span>
                @endif
            </p>
        </div>

        <div class="grid grid-cols-3 gap-3 lg:min-w-[28rem]">
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <p class="text-xs uppercase tracking-wide text-slate-500">Geracao</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $progress['generation'] ?? '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <p class="text-xs uppercase tracking-wide text-slate-500">Metricas gravadas</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $metricsCount }}</p>
            </div>
            <div class="rounded-lg border {{ $heartbeatMonitor['badge_classes'] }} px-3 py-2">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs uppercase tracking-wide">Tempo sem heartbeat</p>
                    <span class="rounded-full border border-current/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">
                        {{ $heartbeatMonitor['status_label'] }}
                    </span>
                </div>
                <p class="mt-1 text-lg font-semibold">{{ $heartbeatMonitor['age_label'] }}</p>
            </div>
        </div>
    </div>

    @if ($heartbeatMonitor['is_warning'])
        <div class="mt-4 rounded-lg border {{ $heartbeatMonitor['panel_classes'] }} p-4 text-sm">
            <p class="font-semibold">Aviso de heartbeat</p>
            <p class="mt-1">{{ $heartbeatMonitor['message'] }}</p>
            @if (! empty($heartbeatMonitor['operation_label']))
                <p class="mt-2 text-xs font-medium uppercase tracking-wide">
                    Operacao atual: {{ $heartbeatMonitor['operation_label'] }}
                </p>
            @endif
        </div>
    @endif

    @if ($executionSummary !== [])
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-950">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-rose-700">Resumo da interrupcao</p>
                    <h4 class="mt-1 text-base font-semibold text-rose-900">{{ $executionSummary['title'] ?? 'Execucao interrompida' }}</h4>
                    <p class="mt-2 text-sm text-rose-800">{{ $executionSummary['user_message'] ?? ($executionSummary['reason'] ?? 'A execucao foi interrompida.') }}</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2 text-xs text-rose-800">
                    <p>Fase: <span class="font-semibold">{{ str_replace('_', ' ', (string) ($executionSummary['phase'] ?? 'desconhecida')) }}</span></p>
                    <p class="mt-1">Etapa: <span class="font-semibold">{{ str_replace('_', ' ', (string) ($executionSummary['stage'] ?? 'desconhecida')) }}</span></p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-4">
                <div class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-rose-600">Tentativa</p>
                    <p class="mt-1 font-semibold text-rose-900">{{ $executionSummary['attempt'] ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-rose-600">Fila</p>
                    <p class="mt-1 font-semibold text-rose-900">{{ $executionSummary['queue_size'] ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-rose-600">Conflitos hard</p>
                    <p class="mt-1 font-semibold text-rose-900">{{ $executionSummary['hard_conflict_allocations'] ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-rose-600">Penalidade hard</p>
                    <p class="mt-1 font-semibold text-rose-900">
                        {{ isset($executionSummary['hard_penalty']) ? number_format((float) $executionSummary['hard_penalty'], 2) : '-' }}
                    </p>
                </div>
            </div>

            <div class="mt-4">
                <p class="font-semibold text-rose-900">Motivo tecnico</p>
                <p class="mt-1 text-sm text-rose-800">{{ $executionSummary['reason'] ?? 'Motivo nao informado.' }}</p>
            </div>

            @if (! empty($executionSummary['suggestions']) && is_array($executionSummary['suggestions']))
                <div class="mt-4">
                    <p class="font-semibold text-rose-900">Como contornar</p>
                    <ul class="mt-2 space-y-2">
                        @foreach ($executionSummary['suggestions'] as $suggestion)
                            <li class="rounded-lg border border-rose-200 bg-white/70 px-3 py-2 text-sm text-rose-800">
                                {{ $suggestion }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    @if (($progress['phase'] ?? null) === 'initial_population')
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-semibold text-amber-900">Criando populacao inicial</p>
                <p class="mt-1 text-sm text-amber-800">
                    Etapa: {{ str_replace('_', ' ', $progress['stage'] ?? 'preparando') }}
                </p>
                @if (isset($progress['current_individual'], $progress['total_individuals']))
                    <p class="mt-1 text-sm text-amber-800">
                        Individuo {{ $progress['current_individual'] }} de {{ $progress['total_individuals'] }}
                    </p>
                @endif
                @if (isset($progress['attempt']))
                    <p class="mt-1 text-sm text-amber-800">
                        Tentativa {{ $progress['attempt'] }}@if(isset($progress['alpha'])) &middot; alpha {{ $progress['alpha'] }}@endif
                    </p>
                @endif
                @if (isset($progress['timestamp']))
                    <p class="mt-2 text-xs text-amber-700">Atualizado em {{ $progress['timestamp'] }}</p>
                @endif
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between text-sm text-slate-600">
                    <span>Alocacoes da tentativa atual</span>
                    <span>{{ $progress['allocations'] ?? 0 }}/{{ $progress['queue_size'] ?? 0 }}</span>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-amber-500" style="width: {{ (($progress['fill_ratio'] ?? $progress['population_fill_ratio'] ?? 0) * 100) }}%"></div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-md bg-slate-50 px-3 py-2">
                        <p class="text-slate-500">Forcadas</p>
                        <p class="font-semibold text-slate-900">{{ $progress['forced_allocations'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-md bg-slate-50 px-3 py-2">
                        <p class="text-slate-500">Conflitos hard</p>
                        <p class="font-semibold text-slate-900">{{ $progress['hard_conflict_allocations'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>
    @elseif (($progress['phase'] ?? null) === 'evolving' || ($progress['phase'] ?? null) === 'evolution')
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
            Evolucao em andamento.
            @if (isset($progress['generation'], $progress['max_generations']))
                Geracao {{ $progress['generation'] }} de {{ $progress['max_generations'] }}.
            @endif
            @if (isset($progress['best_fitness']))
                Melhor fitness atual: {{ number_format((float) $progress['best_fitness'], 4) }}.
            @endif
            @if (! empty($progress['operation_label']))
                <p class="mt-2 text-sm text-emerald-800">
                    Operacao atual: <span class="font-semibold">{{ $progress['operation_label'] }}</span>
                    @if (isset($progress['operation_elapsed_seconds']))
                        <span class="mx-2 text-emerald-500">&middot;</span>
                        Em execucao ha {{ (int) $progress['operation_elapsed_seconds'] }}s
                    @endif
                </p>
            @endif
        </div>
    @elseif ($execution->status === 'failed')
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
            A execucao falhou antes de concluir todas as etapas. Se nao houver metricas suficientes, os graficos podem ficar vazios.
        </div>
    @elseif ($execution->status === 'cancelled')
        <div class="mt-4 rounded-lg border border-slate-200 bg-slate-100 p-4 text-sm text-slate-800">
            A execucao foi cancelada pelo usuario.
        </div>
    @endif
</div>
