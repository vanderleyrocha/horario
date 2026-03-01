<div class="bg-white shadow rounded-lg p-6">

    <h2 class="text-2xl font-bold mb-6">
        Histórico de Execuções
    </h2>

    @if ($this->execucoes->isEmpty())
        <p class="text-gray-600">
            Nenhuma execução registrada.
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full border border-gray-200 rounded-lg">

                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">ID</th>
                        <th class="px-4 py-2 text-left">Fitness</th>
                        <th class="px-4 py-2 text-left">Gerações</th>
                        <th class="px-4 py-2 text-left">Tempo (ms)</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Criado em</th>
                        <th class="px-4 py-2 text-left">Ações</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">

                    @foreach ($this->execucoes as $execucao)
                        <tr class="{{ $execucao->ativa ? 'bg-green-50' : '' }}">

                            <td class="px-4 py-2">
                                #{{ $execucao->id }}
                            </td>

                            <td class="px-4 py-2 font-semibold">
                                {{ $execucao->fitness_score ?? '-' }}
                            </td>

                            <td class="px-4 py-2">
                                {{ $execucao->geracoes_executadas ?? '-' }}
                            </td>

                            <td class="px-4 py-2">
                                {{ $execucao->tempo_execucao_ms ? number_format($execucao->tempo_execucao_ms, 0) : '-' }}
                            </td>

                            <td class="px-4 py-2">
                                <span
                                    class="px-2 py-1 rounded text-xs font-medium
                                    {{ $execucao->status === 'concluida' ? 'bg-green-200 text-green-800' : '' }}
                                    {{ $execucao->status === 'falhou' ? 'bg-red-200 text-red-800' : '' }}
                                    {{ $execucao->status === 'em_execucao' ? 'bg-yellow-200 text-yellow-800' : '' }}">
                                    {{ ucfirst($execucao->status) }}
                                </span>
                            </td>

                            <td class="px-4 py-2 text-sm text-gray-600">
                                {{ $execucao->created_at->format('d/m/Y H:i') }}
                            </td>

                            <td class="px-4 py-2 flex gap-2">

                                @if (!$execucao->ativa && $execucao->status === 'concluida')
                                    <button wire:click="ativarExecucao({{ $execucao->id }})" class="px-3 py-1 bg-blue-600 text-white rounded text-xs">
                                        Ativar
                                    </button>
                                @endif

                                @if (!$execucao->ativa)
                                    <button wire:click="excluirExecucao({{ $execucao->id }})" class="px-3 py-1 bg-red-600 text-white rounded text-xs">
                                        Excluir
                                    </button>
                                @else
                                    <span class="text-green-700 text-xs font-bold">
                                        Execução Ativa
                                    </span>
                                @endif

                            </td>

                        </tr>
                    @endforeach

                </tbody>

            </table>
        </div>

    @endif

</div>
