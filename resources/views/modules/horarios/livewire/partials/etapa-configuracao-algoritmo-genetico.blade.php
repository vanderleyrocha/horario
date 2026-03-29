{{-- resources/views/livewire/horarios/partials/etapa-configuracao-algoritmo-genetico.blade.php --}}
<div class="space-y-6">
    <h3 class="text-xl font-semibold text-gray-800 mb-6">Configuracao do Algoritmo Genetico</h3>

    <form wire:submit.prevent="proximaEtapa">
        <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            Essas configuracoes sao aplicadas diretamente ao solver antes da execucao.
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 mb-4">
            <div>
                <label for="populacao" class="block text-sm font-medium text-gray-700 mb-1">Tamanho da Populacao</label>
                <input type="number" id="populacao" wire:model.live="populacao"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       min="20" max="1000" step="10">
                @error('populacao') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Numero de individuos avaliados por geracao.</p>
            </div>

            <div>
                <label for="geracoes" class="block text-sm font-medium text-gray-700 mb-1">Numero de Geracoes</label>
                <input type="number" id="geracoes" wire:model.live="geracoes"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       min="10" max="5000" step="10">
                @error('geracoes') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Quantidade maxima de iteracoes do algoritmo.</p>
            </div>

            <div>
                <label for="taxa_mutacao" class="block text-sm font-medium text-gray-700 mb-1">Taxa de Mutacao</label>
                <input type="number" step="0.01" id="taxa_mutacao" wire:model.live="taxa_mutacao"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       min="0" max="1">
                @error('taxa_mutacao') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Taxa base usada no controle adaptativo de mutacao.</p>
            </div>

            <div>
                <label for="taxa_crossover" class="block text-sm font-medium text-gray-700 mb-1">Taxa de Crossover</label>
                <input type="number" step="0.01" id="taxa_crossover" wire:model.live="taxa_crossover"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       min="0" max="1">
                @error('taxa_crossover') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Probabilidade de recombinacao entre individuos.</p>
            </div>

            <div>
                <label for="taxa_elitismo" class="block text-sm font-medium text-gray-700 mb-1">Taxa de Elitismo</label>
                <input type="number" step="0.01" id="taxa_elitismo" wire:model.live="taxa_elitismo"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       min="0" max="1">
                @error('taxa_elitismo') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Percentual da populacao preservado na proxima geracao.</p>
            </div>

            <div>
                <label for="elitism_count" class="block text-sm font-medium text-gray-700 mb-1">Contagem de Elitismo</label>
                <input type="number" id="elitism_count" wire:model="elitism_count"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-600 focus:ring-0 focus:border-gray-200"
                       readonly>
                @error('elitism_count') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Calculado automaticamente a partir de populacao x taxa de elitismo.</p>
            </div>

            <div>
                <label for="taxa_mutacao_min" class="block text-sm font-medium text-gray-700 mb-1">Taxa de Mutacao Minima</label>
                <input type="number" step="0.01" id="taxa_mutacao_min" wire:model.live="taxa_mutacao_min"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       min="0" max="1">
                @error('taxa_mutacao_min') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Limite inferior da adaptacao da mutacao.</p>
            </div>

            <div>
                <label for="taxa_mutacao_max" class="block text-sm font-medium text-gray-700 mb-1">Taxa de Mutacao Maxima</label>
                <input type="number" step="0.01" id="taxa_mutacao_max" wire:model.live="taxa_mutacao_max"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       min="0" max="1">
                @error('taxa_mutacao_max') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Limite superior da adaptacao da mutacao.</p>
            </div>

            <div>
                <label for="limite_estagnacao" class="block text-sm font-medium text-gray-700 mb-1">Limite de Estagnacao</label>
                <input type="number" id="limite_estagnacao" wire:model.live="limite_estagnacao"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       min="0" max="1000">
                @error('limite_estagnacao') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Parametro de apoio para detectar falta de progresso.</p>
            </div>

            <div>
                <label for="target_fitness" class="block text-sm font-medium text-gray-700 mb-1">Fitness Alvo (%)</label>
                <input type="number" step="0.1" id="target_fitness" wire:model.live="target_fitness"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       min="0" max="100">
                @error('target_fitness') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">A execucao pode encerrar quando atingir esse nivel de fitness.</p>
            </div>

            <div>
                <label for="max_generations_without_improvement" class="block text-sm font-medium text-gray-700 mb-1">Max. Geracoes Sem Melhoria</label>
                <input type="number" id="max_generations_without_improvement" wire:model.live="max_generations_without_improvement"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       min="0">
                @error('max_generations_without_improvement') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">A execucao pode parar se esse limite for alcancado sem melhora do fitness.</p>
            </div>
        </div>

        <div class="mt-6 flex justify-between">
            <button type="button" wire:click="etapaAnterior" class="px-6 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                &lt;&lt; Anterior
            </button>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Salvar e Proxima Etapa
            </button>
        </div>
    </form>
</div>
