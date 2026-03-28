{{-- resources/views/livewire/horarios/index.blade.php --}}

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Horarios</h2>
            <p class="mt-1 text-gray-600">Gerencie e visualize os horarios gerados</p>
        </div>

        <a
            href="{{ route('horarios.create') }}"
            wire:navigate
            class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
        >
            Novo horario
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        @foreach ($horarios as $horario)
            <div class="rounded-2xl border bg-white p-6 shadow-sm transition hover:shadow-lg">
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">
                            {{ $horario->id . '- ' . $horario->nome }}
                        </h3>
                        <p class="text-sm text-gray-500">
                            {{ $horario->ano }}/{{ $horario->semestre }}
                        </p>
                    </div>

                    <div class="flex flex-col items-end space-y-2">
                        <span
                            class="rounded-full px-2 py-1 text-xs font-semibold
                            {{ $horario->status === 'ativo' ? 'bg-green-100 text-green-700' : '' }}
                            {{ $horario->status === 'rascunho' ? 'bg-gray-100 text-gray-700' : '' }}"
                        >
                            {{ ucfirst($horario->status) }}
                        </span>
                    </div>
                </div>

                <div class="mb-4 grid grid-cols-2 gap-4 border-t border-b py-4 text-sm md:grid-cols-4">
                    <div>
                        <p class="text-gray-500">Alocacoes</p>
                        <p class="font-semibold">{{ $horario->alocacoes_count }}</p>
                    </div>
                    <a
                        href="{{ route('algoritmo.center', $horario) }}"
                        wire:navigate
                        class="rounded-lg border border-slate-200 px-3 py-2 transition hover:border-purple-300 hover:bg-purple-50"
                    >
                        <p class="text-gray-500">Execucoes</p>
                        <p class="font-semibold">{{ $horario->executions_count }}</p>
                        <p class="mt-1 text-xs text-gray-500">
                            Ultima:
                            @if ($horario->lastExecution)
                                @php
                                    $lastExecutionStatus = match ($horario->lastExecution->status) {
                                        'running' => 'em execucao',
                                        'cancel_requested' => 'cancelamento solicitado',
                                        'cancelled' => 'cancelada',
                                        'failed' => 'falhou',
                                        'finished', 'completed', 'concluida', 'concluida_com_sucesso' => 'concluida',
                                        default => $horario->lastExecution->status,
                                    };
                                    $lastExecutionBadgeClasses = match ($horario->lastExecution->status) {
                                        'running' => 'bg-blue-100 text-blue-700',
                                        'cancel_requested' => 'bg-amber-100 text-amber-700',
                                        'cancelled' => 'bg-slate-200 text-slate-700',
                                        'failed' => 'bg-rose-100 text-rose-700',
                                        'finished', 'completed', 'concluida', 'concluida_com_sucesso' => 'bg-emerald-100 text-emerald-700',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $lastExecutionBadgeClasses }}">
                                    {{ $lastExecutionStatus }}
                                </span>
                            @else
                                <span class="font-medium text-gray-700">sem execucao</span>
                            @endif
                        </p>
                    </a>
                    <div>
                        <p class="text-gray-500">Fitness</p>
                        <p class="font-semibold">
                            {{ $horario->fitness_score ? number_format($horario->fitness_score, 2) . '%' : '-' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500">Risco</p>
                        <p class="font-semibold">
                            {{ $horario->indice_risco ?? '-' }}/100
                        </p>
                    </div>
                </div>

                <div class="mb-2 grid grid-cols-4 gap-2">
                    <a
                        href="{{ route('horarios.manage', $horario) }}"
                        wire:navigate
                        class="rounded-lg bg-blue-600 px-3 py-2 text-center text-sm text-white"
                    >
                        Configurar
                    </a>

                    <a
                        href="{{ route('algoritmo.center', $horario) }}"
                        wire:navigate
                        class="rounded-lg bg-purple-600 px-3 py-2 text-center text-sm text-white"
                    >
                        Executar Solver
                    </a>

                    @if ($horario->lastExecution)
                        <a
                            href="{{ route('algoritmo.execution', $horario->lastExecution->id) }}"
                            wire:navigate
                            class="rounded-lg bg-indigo-600 px-3 py-2 text-center text-sm text-white"
                        >
                            Dashboard
                        </a>
                    @else
                        <div class="rounded-lg bg-gray-300 px-3 py-2 text-center text-sm text-gray-600">
                            Dashboard
                        </div>
                    @endif

                    <a
                        href="{{ route('horarios.show', $horario) }}"
                        wire:navigate
                        class="rounded-lg bg-gray-700 px-3 py-2 text-center text-sm text-white"
                    >
                        Visualizar
                    </a>
                </div>

                <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-600">
                </div>
            </div>
        @endforeach
    </div>
</div>
