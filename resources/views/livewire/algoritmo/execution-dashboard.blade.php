<div data-solver-dashboard>

    <div class="mb-6">

        <h2 class="text-2xl font-bold text-gray-900">
            Solver Execution #{{ $execution->id }}
        </h2>

        <div class="grid grid-cols-4 gap-4 mt-4">

            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Status</p>
                <p class="text-lg font-bold">{{ $executionInfo['status'] }}</p>
            </div>

            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Population</p>
                <p class="text-lg font-bold">{{ $executionInfo['population'] }}</p>
            </div>

            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Islands</p>
                <p class="text-lg font-bold">{{ $executionInfo['islands'] }}</p>
            </div>

            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Best Fitness</p>
                <p class="text-lg font-bold">{{ number_format($executionInfo['bestFitness'], 4) }}</p>
            </div>

        </div>

        @if (in_array($execution->status, ['running', 'cancel_requested'], true))
            <div class="mt-4 flex justify-end">
                <button
                    wire:click="cancelExecution"
                    wire:confirm="Deseja solicitar o cancelamento desta execucao?"
                    class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    Cancelar Execucao
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
            <div class="mt-4 bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                <h3 class="text-lg font-semibold text-green-800">Geracao concluida!</h3>
                <p class="text-green-700 mt-2">O horario foi gerado com sucesso e a melhor solucao foi salva.</p>
                <a href="{{ route('horarios.show', ['horario' => $execution->horario_id]) }}"
                    class="mt-4 inline-block bg-blue-600 text-white font-bold py-2 px-6 rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75 transition-transform transform hover:scale-105"
                    wire:navigate>
                    Visualizar Horario Gerado ->
                </a>
            </div>
        @endif

    </div>

    @livewire(\App\Livewire\Algoritmo\ExecutionStatusPanel::class, ['execution' => $execution], key('execution-status-' . $execution->id))
    @livewire(\App\Livewire\Algoritmo\ExecutionMetricsStream::class, ['executionId' => $execution->id], key('execution-metrics-' . $execution->id))

    <div class="grid grid-cols-2 gap-6">

        <div class="bg-white shadow rounded p-4 col-span-2">
            <h3 class="font-semibold mb-2">Landscape Observation</h3>
            <div class="grid grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-500">Phenomenon</p>
                    <p class="font-semibold" data-landscape-phenomenon>Neutral</p>
                </div>
                <div>
                    <p class="text-gray-500">Confidence</p>
                    <p class="font-semibold" data-landscape-confidence>0.00</p>
                </div>
                <div>
                    <p class="text-gray-500">Depth Score</p>
                    <p class="font-semibold" data-landscape-depth-score>0.00</p>
                </div>
                <div>
                    <p class="text-gray-500">Summary</p>
                    <p class="font-semibold" data-landscape-summary>No observation yet</p>
                </div>
            </div>
        </div>

        <div class="bg-white shadow rounded p-4">
            <h3 class="font-semibold mb-2">Fitness Curve</h3>
            <div class="relative h-72">
                <canvas id="fitnessChart" class="h-full w-full"></canvas>
            </div>
        </div>

        <div class="bg-white shadow rounded p-4">
            <h3 class="font-semibold mb-2">Diversity Curve</h3>
            <div class="relative h-72">
                <canvas id="diversityChart" class="h-full w-full"></canvas>
            </div>
        </div>

        <div class="bg-white shadow rounded p-4">
            <h3 class="font-semibold mb-2">Entropy Curve</h3>
            <div class="relative h-72">
                <canvas id="entropyChart" class="h-full w-full"></canvas>
            </div>
        </div>

        <div class="bg-white shadow rounded p-4">
            <h3 class="font-semibold mb-2">Mutation Rate</h3>
            <div class="relative h-72">
                <canvas id="mutationChart" class="h-full w-full"></canvas>
            </div>
        </div>

        <div class="bg-white shadow rounded p-4 col-span-2">
            <h3 class="font-semibold mb-2">Operator Usage</h3>
            <div class="relative h-80">
                <canvas id="operatorChart" class="h-full w-full"></canvas>
            </div>
        </div>

        <div class="bg-white shadow rounded p-4 col-span-2">
            <h3 class="font-semibold mb-2">Landscape State</h3>
            <div class="relative h-96">
                <canvas id="landscapeChart" class="h-full w-full"></canvas>
            </div>
        </div>

    </div>

    <script>
        window.executionId = @js($execution->id);
        window.solverMetrics = @json($metrics);
    </script>
</div>
