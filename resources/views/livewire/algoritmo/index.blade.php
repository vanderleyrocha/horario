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
        {{-- EXECUÇÃO --}}
        {{-- ========================================================= --}}
        @if ($emGeracao)
            <div wire:poll.2000ms="atualizarStatus" class="p-6 border rounded-lg">

                <h2 class="text-xl font-bold mb-4">
                    Gerando Horário...
                </h2>

                <p class="mb-4">{{ $mensagemStatus }}</p>

                <div class="w-full bg-gray-200 rounded-full h-6">
                    <div class="bg-blue-600 h-6 rounded-full text-white text-sm flex items-center justify-center transition-all duration-300" style="width: {{ $progressoPercentual }}%">
                        {{ $progressoPercentual }}%
                    </div>
                </div>

                <button wire:click="cancelarGeracao" class="mt-6 px-4 py-2 bg-red-600 text-white rounded-lg">
                    Cancelar
                </button>

            </div>
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
