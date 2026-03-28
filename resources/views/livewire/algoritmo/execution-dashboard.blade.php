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

        <div class="col-span-2 overflow-hidden rounded-2xl border border-slate-200 bg-linear-to-br from-slate-50 via-white to-emerald-50 shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Search Response Readiness</h3>
                        <p class="text-sm text-slate-600" data-sr-readiness-headline>
                            No readiness evidence collected yet
                        </p>
                    </div>
                    <div class="inline-flex items-center rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold tracking-wide text-white" data-sr-readiness-status>
                        idle
                    </div>
                </div>
            </div>

            <div class="grid gap-4 px-6 py-5 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Evidence</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900" data-sr-evidence-count>0</p>
                    <p class="mt-1 text-sm text-slate-600">
                        Pending audits:
                        <span class="font-semibold text-slate-900" data-sr-pending-audits>0</span>
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Gate Status</p>
                    <p class="mt-3 text-base font-semibold text-slate-900" data-sr-gate-status>Diagnostic only</p>
                    <p class="mt-1 text-sm text-slate-600" data-sr-gate-candidate>No candidate yet</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Best Outcome</p>
                    <p class="mt-3 text-base font-semibold text-slate-900" data-sr-best-outcome>Not enough evidence</p>
                    <p class="mt-1 text-sm text-slate-600" data-sr-best-progress>Progress not available</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Latest Outcome</p>
                    <p class="mt-3 text-base font-semibold text-slate-900" data-sr-latest-outcome>No resolved outcome yet</p>
                    <p class="mt-1 text-sm text-slate-600" data-sr-latest-outcome-detail>Waiting for first horizon to expire</p>
                </div>
            </div>

            <div class="grid gap-4 border-t border-slate-200 px-6 py-5 lg:grid-cols-[1.1fr_0.9fr]">
                <div class="rounded-xl border border-slate-200 bg-white/90 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h4 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">Policies</h4>
                        <span class="text-xs text-slate-500">Shadow-mode effectiveness</span>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                                <tr>
                                    <th class="pb-2 pr-4 font-medium">Policy</th>
                                    <th class="pb-2 pr-4 font-medium">Resolved</th>
                                    <th class="pb-2 pr-4 font-medium">Success</th>
                                    <th class="pb-2 pr-4 font-medium">Approach</th>
                                    <th class="pb-2 font-medium">Progress</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100" data-sr-policy-rows>
                                <tr>
                                    <td colspan="5" class="py-4 text-sm text-slate-500">No policies evaluated yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white/90 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h4 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">Blocking Reasons</h4>
                        <span class="text-xs text-slate-500">Before real activation</span>
                    </div>
                    <div class="mt-4 space-y-2" data-sr-blocking-reasons>
                        <p class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-500">
                            No blocking reasons yet.
                        </p>
                    </div>
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
