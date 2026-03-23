<div class="max-w-7xl mx-auto py-10">

    <h1 class="text-3xl font-bold mb-8">
        Solver Center — {{ $horario->nome }}
    </h1>

    <div class="grid grid-cols-2 gap-8">

        <div class="bg-white p-6 rounded shadow">

            <h2 class="text-lg font-semibold mb-4">
                Nova Execução
            </h2>

            <button wire:click="runSolver" class="px-6 py-3 bg-indigo-600 text-white rounded">

                Executar Solver

            </button>

        </div>


        <div class="bg-white p-6 rounded shadow">

            <h2 class="text-lg font-semibold mb-4">
                Execuções Recentes
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

                            <a href="{{ route('algoritmo.execution', $execution) }}" class="text-indigo-600">

                                Abrir Dashboard

                            </a>

                        </td>

                    </tr>
                @endforeach

            </table>

        </div>

    </div>

</div>
