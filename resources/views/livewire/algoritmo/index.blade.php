<div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <div class="bg-white shadow sm:rounded-lg p-6">

        <h1 class="text-3xl font-bold mb-8">
            Gerar Horario: {{ $horario->nome }}
        </h1>

        @if ($temErro)
            <div class="p-6 bg-red-50 border-2 border-red-500 rounded-2xl">
                <h2 class="text-2xl font-bold text-red-700 mb-4">
                    Falha na Geracao
                </h2>

                <p class="font-semibold text-red-800 mb-6">
                    {{ $mensagemErro }}
                </p>

                @if (!empty($erroDetalhes['dados']))
                    <livewire:ag.diagnostico-inviabilidade :diagnostico="$erroDetalhes['dados']" :key="'diag-' . $horario->id . '-' . time()" />
                @endif

                <div class="flex gap-4 mt-6">
                    <button wire:click="gerarNovamente" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Gerar Novamente
                    </button>

                    <button wire:click="resetEstado" class="px-5 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        Voltar
                    </button>
                </div>
            </div>
        @endif

        @if (!$emGeracao && !$temErro && !$concluido)
            <div class="p-6 border rounded-lg">
                <h2 class="text-xl font-bold mb-6">
                    Configuracoes do Algoritmo
                </h2>

                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <label class="flex flex-col gap-2 text-sm font-medium text-gray-700">
                        Populacao
                        <input type="number" wire:model="configuracao.populacao" class="border p-2 rounded">
                    </label>

                    <label class="flex flex-col gap-2 text-sm font-medium text-gray-700">
                        Geracoes
                        <input type="number" wire:model="configuracao.geracoes" class="border p-2 rounded">
                    </label>

                    <label class="flex flex-col gap-2 text-sm font-medium text-gray-700">
                        Taxa de Mutacao
                        <input type="number" wire:model="configuracao.taxa_mutacao" step="0.01" class="border p-2 rounded">
                    </label>

                    <label class="flex flex-col gap-2 text-sm font-medium text-gray-700">
                        Taxa de Crossover
                        <input type="number" wire:model="configuracao.taxa_crossover" step="0.01" class="border p-2 rounded">
                    </label>
                </div>

                <button wire:click="iniciarGeracao" class="px-6 py-3 bg-green-600 text-white rounded-lg">
                    Iniciar Geração
                </button>
            </div>
        @endif

        @if ($emGeracao)
            <div wire:poll.2000ms="atualizarStatus" class="p-6 border rounded-lg bg-gray-50" data-ga-dashboard>
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Evolucao do Algoritmo</h2>
                        <p class="text-gray-500">{{ $mensagemStatus }}</p>
                    </div>

                    <button wire:click="cancelarGeracao" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">
                        Interromper Execucao
                    </button>
                </div>

                <div class="grid grid-cols-4 gap-4 mb-6">
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                        <p class="text-xs text-gray-500 uppercase font-semibold">Geracao</p>
                        <p class="text-2xl font-bold text-blue-600">{{ $geracaoAtual }} <span class="text-sm text-gray-400">/ {{ $geracoesTotais }}</span></p>
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

                <div class="w-full bg-gray-200 rounded-full h-4 mb-8">
                    <div class="bg-blue-600 h-4 rounded-full transition-all duration-500 ease-out" style="width: {{ $progressoPercentual }}%"></div>
                </div>

                <div class="grid grid-cols-2 gap-6" wire:ignore>
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Curva de Convergencia (Fitness)</h3>
                        <div class="h-64">
                            <canvas data-chart="fitness"></canvas>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Diversidade Genetica vs Entropia</h3>
                        <div class="h-64">
                            <canvas data-chart="diversity"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($concluido)
            <div class="p-6 bg-green-100 border border-green-400 rounded-lg">
                <h2 class="text-xl font-bold text-green-800 mb-4">
                    Geracao Concluida
                </h2>

                <a href="{{ route('horarios.manage', $horario) }}" class="text-blue-600 underline">
                    Ver Horario
                </a>
            </div>
        @endif

    </div>
</div>
