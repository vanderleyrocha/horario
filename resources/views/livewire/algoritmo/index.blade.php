{{-- resources/views/livewire/algoritmo/index.blade.php --}}
<div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <div class="bg-white shadow sm:rounded-lg p-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Gerar Horário: {{ $horario->nome }}</h1>

        @if (!$emGeracao)
            {{-- Formulário de Configuração (quando não está gerando) --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Configurações do Algoritmo Genético</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    {{-- Tamanho da População --}}
                    <div wire:key="populacao-field">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tamanho da População
                        </label>
                        <input type="number" wire:model="configuracao.populacao" min="10"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-500">Número de indivíduos em cada geração.</p>
                        @error('configuracao.populacao')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Número de Gerações --}}
                    <div wire:key="geracoes-field">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Número de Gerações
                        </label>
                        <input type="number" wire:model="configuracao.geracoes" min="1"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-500">Total de ciclos de evolução do algoritmo.</p>
                        @error('configuracao.geracoes')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Taxa de Mutação --}}
                    <div wire:key="taxa_mutacao-field">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Taxa de Mutação
                        </label>
                        <input type="number" wire:model="configuracao.taxa_mutacao" min="0" max="1" step="0.01"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-500">Probabilidade de mutação de um gene (0.0-1.0)</p>
                        @error('configuracao.taxa_mutacao')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Taxa de Crossover --}}
                    <div wire:key="taxa_crossover-field">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Taxa de Crossover
                        </label>
                        <input type="number" wire:model="configuracao.taxa_crossover" min="0" max="1" step="0.01"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-500">Probabilidade de cruzamento (0.0-1.0)</p>
                        @error('configuracao.taxa_crossover')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Info Box --}}
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd" />
                        </svg>
                        <div class="flex-1">
                            <h4 class="text-sm font-semibold text-blue-900 mb-1">Dicas para Configuração</h4>
                            <ul class="text-sm text-blue-800 space-y-1">
                                <li>• População maior = mais diversidade, mas mais lento</li>
                                <li>• Mais gerações = melhor resultado, mas mais tempo</li>
                                <li>• Taxa de mutação alta = mais exploração</li>
                                <li>• Taxa de crossover alta = mais recombinação</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Botão Iniciar --}}
                <div class="mt-6 flex justify-end">
                    <button wire:click="iniciarGeracao" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold flex items-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Iniciar Geração</span>
                    </button>
                </div>
            </div>
        @else
            {{-- Progresso da Geração --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" wire:poll.2000ms="atualizarStatus"> {{-- ✅ wire:poll para atualização automática --}}
                <h2 class="text-xl font-bold text-gray-900 mb-6">Gerando Horário...</h2>

                {{-- Barra de Progresso --}}
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">
                            Geração {{ $geracaoAtual }} de {{ $totalGeracoes }}
                        </span>
                        <span class="text-sm font-semibold text-blue-600">
                            {{ number_format($progresso, 1) }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-blue-500 to-green-500 rounded-full transition-all duration-500" style="width: {{ $progresso }}%">
                        </div>
                    </div>
                </div>

                {{-- Estatísticas e Mensagem de Status --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm text-gray-600 mb-1">Melhor Fitness</p>
                        <p class="text-2xl font-bold text-gray-900">
                            {{ number_format($melhorFitness, 2) }}
                        </p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 col-span-1 md:col-span-1"> {{-- Ajustado para ocupar 1 coluna --}}
                        <p class="text-sm text-gray-600 mb-1">Status Atual</p>
                        <p class="text-lg font-semibold text-blue-600">
                            {{ $mensagemStatus }} {{-- ✅ ADICIONADO --}}
                        </p>
                    </div>
                </div>

                {{-- Animação --}}
                <div class="flex justify-center mb-6">
                    <div class="relative">
                        <svg class="animate-spin h-16 w-16 text-blue-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>

                {{-- Mensagens de Conclusão/Erro --}}
                @if ($statusGeracao === 'concluido')
                    <div class="mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md">
                        <p class="font-bold">Geração Concluída!</p>
                        <p>O horário foi gerado com sucesso.</p>
                        <a href="{{ route('horarios.show', $horario) }}" class="text-blue-600 hover:underline mt-2 block">Ver Horário Gerado</a>
                    </div>
                @elseif ($statusGeracao === 'erro')
                    <div class="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-md">
                        <p class="font-bold">Erro na Geração</p>
                        <p>{{ $mensagemStatus }}</p> {{-- Exibe a mensagem de erro do cache --}}
                        <button wire:click="iniciarGeracao" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded-md">Tentar Novamente</button>
                    </div>
                @endif

                {{-- Botão Cancelar --}}
                <div class="flex justify-center mt-6">
                    <button wire:click="cancelarGeracao" wire:confirm="Tem certeza que deseja cancelar a geração?" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Cancelar Geração
                    </button>
                </div>
            </div>
        @endif
        
        {{-- Informações do Horário --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Informações do Horário</h3>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Aulas Configuradas</p>
                    <p class="text-lg font-bold text-gray-900">{{ $horario->aulas->count() }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Turmas</p>
                    <p class="text-lg font-bold text-gray-900">{{ $horario->aulas->unique('turma_id')->count() }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Professores</p>
                    <p class="text-lg font-bold text-gray-900">{{ $horario->aulas->unique('professor_id')->count() }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Restrições</p>
                    <p class="text-lg font-bold text-gray-900">{{ $horario->restricoes->count() }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

@script
    <script>
        $wire.on('geracaoConcluida', () => {
            setTimeout(() => {
                $wire.visualizarResultado();
            }, 2000);
        });
    </script>
@endscript
