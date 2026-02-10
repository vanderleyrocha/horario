{{-- resources/views/livewire/horarios/partials/etapa-configuracao-algoritmo-genetico.blade.php --}}
<div class="space-y-6">
    <h3 class="text-xl font-semibold text-gray-800 mb-6">Configuração do Algoritmo Genético</h3>

    <form wire:submit.prevent="proximaEtapa">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
            <div>
                <label for="elitism_count" class="block text-sm font-medium text-gray-700 mb-1">Contagem de Elitismo</label>
                <input type="number" id="elitism_count" wire:model="elitism_count"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Ex: 10 (número de melhores indivíduos a serem preservados)" min="0">
                @error('elitism_count') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Número de melhores indivíduos da população que são passados diretamente para a próxima geração.</p>
            </div>
            <div>
                <label for="target_fitness" class="block text-sm font-medium text-gray-700 mb-1">Fitness Alvo (%)</label>
                <input type="number" step="0.1" id="target_fitness" wire:model="target_fitness"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Ex: 95.0 (percentual de fitness para parar)" min="0" max="100">
                @error('target_fitness') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">O algoritmo pode parar se o fitness do melhor indivíduo atingir este valor.</p>
            </div>
            <div>
                <label for="max_generations_without_improvement" class="block text-sm font-medium text-gray-700 mb-1">Máx. Gerações Sem Melhoria</label>
                <input type="number" id="max_generations_without_improvement" wire:model="max_generations_without_improvement"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Ex: 50 (número de gerações sem melhora para parar)" min="0">
                @error('max_generations_without_improvement') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">O algoritmo pode parar se não houver melhoria no fitness por este número de gerações.</p>
            </div>
        </div>

        <div class="mt-6 flex justify-between">
            <button type="button" wire:click="etapaAnterior" class="px-6 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                << Anterior
            </button>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Salvar e Próxima Etapa
            </button>
        </div>
    </form>
</div>
