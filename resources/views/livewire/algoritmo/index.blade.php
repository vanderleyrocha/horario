<div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <div class="bg-white shadow sm:rounded-lg p-6">

        <h1 class="text-3xl font-bold mb-8">
            Gerar Horário: {{ $horario->nome }}
        </h1>

        {{-- ========================================================= --}}
        {{-- ERRO --}}
        {{-- ========================================================= --}}
        @if ($temErro)

            <div class="p-6 bg-red-50 border-2 border-red-500 rounded-2xl">

                <h2 class="text-2xl font-bold text-red-700 mb-4">
                    🚫 Falha na Geração
                </h2>

                <p class="font-semibold text-red-800 mb-6">
                    {{ $mensagemErro }}
                </p>

                {{-- Painel visual estruturado --}}
                @if (!empty($erroDetalhes['dados']))
                    <livewire:ag.diagnostico-inviabilidade :diagnostico="$erroDetalhes['dados']" :key="'diag-' . $horario->id . '-' . time()" />
                @endif

                <div class="flex gap-4 mt-6">

                    <button wire:click="gerarNovamente" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        🔄 Gerar Novamente
                    </button>

                    <button wire:click="resetEstado" class="px-5 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        Voltar
                    </button>

                </div>

            </div>

        @endif

        {{-- ========================================================= --}}
        {{-- CONFIGURAÇÃO --}}
        {{-- ========================================================= --}}
        @if (!$emGeracao && !$temErro && !$concluido)
            <div class="p-6 border rounded-lg">

                <h2 class="text-xl font-bold mb-6">
                    Configurações do Algoritmo
                </h2>

                <div class="grid md:grid-cols-2 gap-6 mb-6">

                    <input type="number" wire:model="configuracao.populacao" class="border p-2 rounded">
                    <input type="number" wire:model="configuracao.geracoes" class="border p-2 rounded">
                    <input type="number" wire:model="configuracao.taxa_mutacao" step="0.01" class="border p-2 rounded">
                    <input type="number" wire:model="configuracao.taxa_crossover" step="0.01" class="border p-2 rounded">

                </div>

                <button wire:click="iniciarGeracao" class="px-6 py-3 bg-green-600 text-white rounded-lg">
                    Iniciar Geração
                </button>

            </div>
        @endif

        {{-- ========================================================= --}}
        {{-- PAINEL CIENTÍFICO (EM GERAÇÃO) --}}
        {{-- ========================================================= --}}
        @if ($emGeracao)
            <div wire:poll.2000ms="atualizarStatus" class="p-6 border rounded-lg bg-gray-50">

                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Evolução do Algoritmo</h2>
                        <p class="text-gray-500">{{ $mensagemStatus }}</p>
                    </div>
                    <button wire:click="cancelarGeracao" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">
                        Interromper Execução
                    </button>
                </div>

                {{-- Cards de KPIs em Tempo Real --}}
                <div class="grid grid-cols-4 gap-4 mb-6">
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                        <p class="text-xs text-gray-500 uppercase font-semibold">Geração</p>
                        <p class="text-2xl font-bold text-blue-600">{{ $geracaoAtual }} <span class="text-sm text-gray-400">/ {{ $configuracao['geracoes'] ?? 500 }}</span></p>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                        <p class="text-xs text-gray-500 uppercase font-semibold">Melhor Fitness</p>
                        <p class="text-2xl font-bold text-green-600">{{ number_format($melhorFitnessAtual, 4) }}</p>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                        <p class="text-xs text-gray-500 uppercase font-semibold">Entropia Estrutural</p>
                        <p class="text-2xl font-bold text-purple-600">{{ number_format($entropiaAtual, 4) }}</p>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                        <p class="text-xs text-gray-500 uppercase font-semibold">Estado do ALNS</p>
                        <p class="text-xl font-bold text-orange-500 mt-1">{{ ucfirst($estadoLandscape) }}</p>
                    </div>
                </div>

                {{-- Barra de Progresso --}}
                <div class="w-full bg-gray-200 rounded-full h-4 mb-8">
                    <div class="bg-blue-600 h-4 rounded-full transition-all duration-500 ease-out" style="width: {{ $progressoPercentual }}%"></div>
                </div>

                {{-- Gráficos Chart.js --}}
                <div class="grid grid-cols-2 gap-6" wire:ignore>
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Curva de Convergência (Fitness)</h3>
                        <canvas id="fitnessChart" height="200"></canvas>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Diversidade Genética vs Entropia</h3>
                        <canvas id="diversityChart" height="200"></canvas>
                    </div>
                </div>

            </div>

            {{-- Script para inicializar e atualizar os gráficos --}}
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                document.addEventListener('livewire:initialized', () => {
                    // Inicializa Gráfico de Fitness
                    const ctxFit = document.getElementById('fitnessChart');
                    const fitnessChart = new Chart(ctxFit, {
                        type: 'line',
                        data: {
                            labels: [],
                            datasets: [{
                                    label: 'Best Fitness',
                                    data: [],
                                    borderColor: '#16a34a',
                                    tension: 0.1,
                                    borderWidth: 2,
                                    pointRadius: 0
                                },
                                {
                                    label: 'Avg Fitness',
                                    data: [],
                                    borderColor: '#9ca3af',
                                    borderDash: [5, 5],
                                    tension: 0.1,
                                    borderWidth: 1,
                                    pointRadius: 0
                                }
                            ]
                        },
                        options: {
                            animation: false,
                            scales: {
                                x: {
                                    display: false
                                }
                            }
                        }
                    });

                    // Inicializa Gráfico de Diversidade/Entropia
                    const ctxDiv = document.getElementById('diversityChart');
                    const diversityChart = new Chart(ctxDiv, {
                        type: 'line',
                        data: {
                            labels: [],
                            datasets: [{
                                    label: 'Entropia',
                                    data: [],
                                    borderColor: '#9333ea',
                                    tension: 0.3,
                                    borderWidth: 2,
                                    pointRadius: 0
                                },
                                {
                                    label: 'Diversidade (Hamming)',
                                    data: [],
                                    borderColor: '#f97316',
                                    tension: 0.3,
                                    borderWidth: 2,
                                    pointRadius: 0
                                }
                            ]
                        },
                        options: {
                            animation: false,
                            scales: {
                                x: {
                                    display: false
                                }
                            }
                        }
                    });

                    // Escuta o evento disparado pelo PHP e injeta os dados nas linhas
                    Livewire.on('metrics-updated', (event) => {
                        // O Livewire 3 pode empacotar os dados de formas diferentes.
                        // Esta linha garante que pegamos o payload real, não importa a estrutura.
                        let payload = event[0] || event;
                        if (payload.data) payload = payload.data;

                        // Atualiza gráfico de Fitness
                        fitnessChart.data.labels.push(payload.generation);
                        fitnessChart.data.datasets[0].data.push(payload.bestFitness);
                        fitnessChart.data.datasets[1].data.push(payload.avgFitness);
                        fitnessChart.update();

                        // Atualiza gráfico de Diversidade
                        diversityChart.data.labels.push(payload.generation);
                        diversityChart.data.datasets[0].data.push(payload.entropy);
                        diversityChart.data.datasets[1].data.push(payload.diversity);
                        diversityChart.update();
                    });
                    // Limpa os gráficos quando uma nova geração começar (se o usuário clicar em "Gerar" de novo)
                    Livewire.on('startPolling', () => {
                        fitnessChart.data.labels = [];
                        fitnessChart.data.datasets[0].data = [];
                        fitnessChart.data.datasets[1].data = [];
                        fitnessChart.update();

                        diversityChart.data.labels = [];
                        diversityChart.data.datasets[0].data = [];
                        diversityChart.data.datasets[1].data = [];
                        diversityChart.update();
                    });
                });
            </script>
        @endif

        {{-- ========================================================= --}}
        {{-- CONCLUÍDO --}}
        {{-- ========================================================= --}}
        @if ($concluido)
            <div class="p-6 bg-green-100 border border-green-400 rounded-lg">

                <h2 class="text-xl font-bold text-green-800 mb-4">
                    ✅ Geração Concluída
                </h2>

                <a href="{{ route('horarios.show', $horario) }}" class="text-blue-600 underline">
                    Ver Horário
                </a>

            </div>
        @endif

    </div>
</div>
