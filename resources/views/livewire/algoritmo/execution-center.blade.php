<div class="max-w-7xl mx-auto py-10">

    <h1 class="text-3xl font-bold mb-8">
        Solver Center - {{ $horario->nome }}
    </h1>

    <div class="grid grid-cols-2 gap-8">

        <div class="bg-white p-6 rounded shadow">

            <h2 class="text-lg font-semibold mb-4">
                Nova Execucao
            </h2>

            <button wire:click="runSolver" class="px-6 py-3 bg-indigo-600 text-white rounded">
                Executar Solver
            </button>

        </div>

        <div class="bg-white p-6 rounded shadow">

            <h2 class="text-lg font-semibold mb-4">
                Execucoes Recentes
            </h2>

            <table class="w-full text-sm">

                @foreach ($executions as $execution)
                    <tr class="border-b">

                        <td>
                            #{{ $execution->id }}
                        </td>

                        <td>
                            {{ $execution->status }}
                        </td>

                        <td>
                            <div class="flex items-center gap-3">
                                <a href="{{ route('algoritmo.execution', $execution) }}" class="text-indigo-600">
                                    Abrir Dashboard
                                </a>

                                @if (in_array($execution->status, ['running', 'cancel_requested'], true))
                                    <button
                                        wire:click="cancelExecution({{ $execution->id }})"
                                        wire:confirm="Deseja solicitar o cancelamento desta execucao?"
                                        class="rounded bg-red-600 px-2 py-1 text-xs text-white">
                                        Cancelar
                                    </button>
                                @endif
                            </div>
                        </td>

                    </tr>
                @endforeach

            </table>

        </div>

    </div>

</div>
