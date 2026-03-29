{{-- resources/views/livewire/horarios/create.blade.php --}}

<div>
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <a href="{{ route('horarios.index') }}" wire:navigate 
               class="text-blue-600 hover:text-blue-700 flex items-center mb-4 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Voltar para listagem
            </a>
            <h2 class="text-2xl font-bold text-gray-900">Novo Horário</h2>
            <p class="text-gray-600 mt-1">Configure um novo horário para geração automática</p>
        </div>

        <!-- Form -->
        <form wire:submit="save" class="space-y-6">

            <!-- Informações Básicas -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                    Informações Básicas
                </h3>

                <!-- Nome -->
                <div class="mb-6">
                    <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">
                        Nome do Horário <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="nome"
                        wire:model="nome"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all @error('nome') border-red-500 @enderror"
                        placeholder="Ex: Horário 2026.1"
                    >
                    @error('nome')
                        <p class="mt-1 text-sm text-red-600 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Ano e Semestre -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="ano" class="block text-sm font-medium text-gray-700 mb-2">
                            Ano Letivo <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="ano"
                            wire:model="ano"
                            min="2020"
                            max="2100"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all @error('ano') border-red-500 @enderror"
                        >
                        @error('ano')
                            <p class="mt-1 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="semestre" class="block text-sm font-medium text-gray-700 mb-2">
                            Semestre <span class="text-red-500">*</span>
                        </label>
                        <select 
                            id="semestre"
                            wire:model="semestre"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all @error('semestre') border-red-500 @enderror"
                        >
                            <option value="1">1º Semestre</option>
                            <option value="2">2º Semestre</option>
                        </select>
                        @error('semestre')
                            <p class="mt-1 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Configurações do Algoritmo Genético -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                    Configurações do Algoritmo Genético
                </h3>

                <div class="space-y-6">
                    <!-- População -->
                    <div>
                        <label for="populacao" class="block text-sm font-medium text-gray-700 mb-2">
                            Tamanho da População
                        </label>
                        <input 
                            type="number" 
                            id="populacao"
                            wire:model="populacao"
                            min="50"
                            max="500"
                            step="10"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            Número de soluções candidatas em cada geração (50-500). Valores maiores aumentam o tempo de processamento.
                        </p>
                    </div>

                    <!-- Gerações -->
                    <div>
                        <label for="geracoes" class="block text-sm font-medium text-gray-700 mb-2">
                            Número de Gerações
                        </label>
                        <input 
                            type="number" 
                            id="geracoes"
                            wire:model="geracoes"
                            min="100"
                            max="2000"
                            step="50"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            Número de iterações do algoritmo (100-2000). Mais gerações podem melhorar a qualidade da solução.
                        </p>
                    </div>

                    <!-- Taxa de Mutação -->
                    <div>
                        <label for="taxa_mutacao" class="block text-sm font-medium text-gray-700 mb-2">
                            Taxa de Mutação
                        </label>
                        <input 
                            type="number" 
                            id="taxa_mutacao"
                            wire:model="taxa_mutacao"
                            min="0.01"
                            max="0.5"
                            step="0.01"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            Probabilidade de alteração aleatória (0.01-0.5). Valores entre 0.05 e 0.15 são recomendados.
                        </p>
                    </div>

                    <!-- Taxa de Crossover -->
                    <div>
                        <label for="taxa_crossover" class="block text-sm font-medium text-gray-700 mb-2">
                            Taxa de Crossover
                        </label>
                        <input 
                            type="number" 
                            id="taxa_crossover"
                            wire:model="taxa_crossover"
                            min="0.5"
                            max="0.95"
                            step="0.05"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            Probabilidade de recombinação de soluções (0.5-0.95). Valores entre 0.6 e 0.8 são recomendados.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex">
                    <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-blue-900">Sobre o Algoritmo Genético</p>
                        <p class="text-xs text-blue-700 mt-1">
                            O algoritmo genético é uma técnica de otimização inspirada na evolução natural. 
                            Ele gera automaticamente horários tentando minimizar conflitos de professores, 
                            salas e respeitando as restrições configuradas.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-3">
                <button 
                    type="button"
                    wire:click="cancel"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    Cancelar
                </button>
                <button 
                    type="submit" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Criar Horário
                </button>
            </div>
        </form>
    </div>
</div>
