<div class="bg-white shadow rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-6">
        Historico de Execucoes
    </h2>

    @if ($this->execucoes->isEmpty())
        <p class="text-gray-600">
            Nenhuma execucao registrada.
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full border border-gray-200 rounded-lg">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">ID</th>
                        <th class="px-4 py-2 text-left">Melhor Fitness</th>
                        <th class="px-4 py-2 text-left">Geracoes</th>
                        <th class="px-4 py-2 text-left">Tempo (ms)</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Inicio</th>
                        <th class="px-4 py-2 text-left">Acoes</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">
                    @foreach ($this->execucoes as $execucao)
                        @php
                            $status = strtolower((string) $execucao->status);
                        @endphp
                        <tr>
                            <td class="px-4 py-2">
                                #{{ $execucao->id }}
                            </td>

                            <td class="px-4 py-2 font-semibold">
                                {{ $execucao->best_fitness ?? '-' }}
                            </td>

                            <td class="px-4 py-2">
                                {{ $execucao->generations ?? '-' }}
                            </td>

                            <td class="px-4 py-2">
                                {{ $execucao->execution_time_ms ? number_format($execucao->execution_time_ms, 0) : '-' }}
                            </td>

                            <td class="px-4 py-2">
                                <span
                                    class="px-2 py-1 rounded text-xs font-medium
                                    {{ in_array($status, ['completed', 'concluida', 'concluida_com_sucesso'], true) ? 'bg-green-200 text-green-800' : '' }}
                                    {{ in_array($status, ['failed', 'falhou', 'error'], true) ? 'bg-red-200 text-red-800' : '' }}
                                    {{ in_array($status, ['running', 'em_execucao'], true) ? 'bg-yellow-200 text-yellow-800' : '' }}">
                                    {{ ucfirst((string) $execucao->status) }}
                                </span>
                            </td>

                            <td class="px-4 py-2 text-sm text-gray-600">
                                {{ optional($execucao->start_time)->format('d/m/Y H:i') ?? '-' }}
                            </td>

                            <td class="px-4 py-2 flex gap-2">
                                <button wire:click="abrirExecucao({{ $execucao->id }})" class="px-3 py-1 bg-blue-600 text-white rounded text-xs">
                                    Abrir
                                </button>

                                @if (!in_array($status, ['running', 'em_execucao'], true))
                                    <button wire:click="excluirExecucao({{ $execucao->id }})" class="px-3 py-1 bg-red-600 text-white rounded text-xs">
                                        Excluir
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
