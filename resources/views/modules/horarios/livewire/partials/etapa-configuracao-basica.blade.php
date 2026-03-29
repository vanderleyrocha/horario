{{-- resources/views/livewire/horarios/partials/etapa-configuracao-basica.blade.php --}}

<div class="space-y-6">
    <h3 class="text-xl font-semibold text-gray-800 mb-6">Configuração Básica do Horário</h3>

    <form wire:submit.prevent="proximaEtapa"> 
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Nome da Escola --}}
            <div>
                <label for="nome_escola" class="block text-sm font-medium text-gray-700 mb-1">Nome da Escola</label>
                <input type="text" id="nome_escola" wire:model="nome_escola"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Ex: Escola Municipal ABC">
                @error('nome_escola') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Aulas por Dia --}}
            <div>
                <label for="aulas_por_dia" class="block text-sm font-medium text-gray-700 mb-1">Aulas por Dia</label>
                <input type="number" id="aulas_por_dia" wire:model="aulas_por_dia" min="1" max="10"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('aulas_por_dia') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Dias da Semana --}}
            <div>
                <label for="dias_semana" class="block text-sm font-medium text-gray-700 mb-1">Dias da Semana</label>
                <input type="number" id="dias_semana" wire:model="dias_semana" min="1" max="7"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('dias_semana') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Horário de Início --}}
            <div>
                <label for="horario_inicio" class="block text-sm font-medium text-gray-700 mb-1">Horário de Início</label>
                <input type="time" id="horario_inicio" wire:model="horario_inicio"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('horario_inicio') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Horário de Fim --}}
            <div>
                <label for="horario_fim" class="block text-sm font-medium text-gray-700 mb-1">Horário de Fim</label>
                <input type="time" id="horario_fim" wire:model="horario_fim"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('horario_fim') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Duração da Aula (minutos) --}}
            <div>
                <label for="duracao_aula_minutos" class="block text-sm font-medium text-gray-700 mb-1">Duração da Aula (minutos)</label>
                <input type="number" id="duracao_aula_minutos" wire:model="duracao_aula_minutos" min="10" max="120"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('duracao_aula_minutos') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Duração do Intervalo (minutos) --}}
            <div>
                <label for="duracao_intervalo_minutos" class="block text-sm font-medium text-gray-700 mb-1">Duração do Intervalo (minutos)</label>
                <input type="number" id="duracao_intervalo_minutos" wire:model="duracao_intervalo_minutos" min="0" max="60"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('duracao_intervalo_minutos') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Permitir Janelas --}}
            <div class="flex items-center col-span-1 md:col-span-2">
                <input type="checkbox" id="permitir_janelas" wire:model="permitir_janelas"
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="permitir_janelas" class="ml-2 block text-sm text-gray-900">Permitir "janelas" (tempos livres entre aulas do mesmo professor/turma)?</label>
            </div>

            {{-- Agrupar Disciplinas --}}
            <div class="flex items-center col-span-1 md:col-span-2">
                <input type="checkbox" id="agrupar_disciplinas" wire:model="agrupar_disciplinas"
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="agrupar_disciplinas" class="ml-2 block text-sm text-gray-900">Tentar agrupar aulas da mesma disciplina em dias consecutivos?</label>
            </div>

            {{-- Máximo de Aulas Seguidas --}}
            <div>
                <label for="max_aulas_seguidas" class="block text-sm font-medium text-gray-700 mb-1">Máximo de Aulas Seguidas</label>
                <input type="number" id="max_aulas_seguidas" wire:model="max_aulas_seguidas" min="1" max="5"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('max_aulas_seguidas') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
        </div>

        <h4 class="text-lg font-semibold mt-6 mb-2">Intervalos Customizados</h4>
        <p class="text-sm text-gray-600 mb-4">Defina em qual "tempo" (posição da aula) o intervalo ocorre e sua duração.</p>

        @foreach($horarios_intervalos as $index => $horarioIntervalo)
            <div class="flex items-center space-x-2 mb-2">
                <input type="number" wire:model="horarios_intervalos.{{ $index }}" placeholder="Tempo (ex: 2)" class="w-24 rounded-md border-gray-300 shadow-sm">
                <input type="number" wire:model="duracoes_intervalos.{{ $index }}" placeholder="Duração (min)" class="w-24 rounded-md border-gray-300 shadow-sm">
                <button type="button" wire:click="removeIntervalo({{ $index }})" class="bg-red-100 text-red-600 hover:bg-red-200 hover:text-red-900 font-bold py-1 px-2 rounded">Remover</button>
            </div>
            @error("horarios_intervalos.{$index}") <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            @error("duracoes_intervalos.{$index}") <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        @endforeach
        <button type="button" wire:click="addIntervalo" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md mt-2">Adicionar Intervalo</button>

        <div class="mt-6 flex justify-end">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Salvar e Próxima Etapa
            </button>
        </div>
    </form>
</div>
