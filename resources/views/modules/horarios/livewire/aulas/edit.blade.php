{{-- resources/views/livewire/aulas/edit.blade.php --}}
<div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
    <div class="bg-white shadow sm:rounded-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Editar Aula</h2>

        <form wire:submit.prevent="salvarAula" class="space-y-6">
            {{-- Professor --}}
            <div>
                <label for="professor_id" class="block text-sm font-medium text-gray-700 mb-1">Professor</label>
                <select id="professor_id" wire:model="professor_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Selecione um professor</option>
                    @foreach($professores as $professor)
                        <option value="{{ $professor->id }}">{{ $professor->nome }}</option>
                    @endforeach
                </select>
                @error('professor_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Disciplina --}}
            <div>
                <label for="disciplina_id" class="block text-sm font-medium text-gray-700 mb-1">Disciplina</label>
                <select id="disciplina_id" wire:model="disciplina_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Selecione uma disciplina</option>
                    @foreach($disciplinas as $disciplina)
                        <option value="{{ $disciplina->id }}">{{ $disciplina->nome }}</option>
                    @endforeach
                </select>
                @error('disciplina_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Turma --}}
            <div>
                <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
                <select id="turma_id" wire:model="turma_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Selecione uma turma</option>
                    @foreach($turmas as $turma)
                        <option value="{{ $turma->id }}">{{ $turma->nome }}</option>
                    @endforeach
                </select>
                @error('turma_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Aulas por Semana --}}
            <div>
                <label for="aulas_semana" class="block text-sm font-medium text-gray-700 mb-1">Aulas por Semana</label>
                <input type="number" id="aulas_semana" wire:model="aulas_semana" min="1" max="10"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('aulas_semana') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Tipo de Aula --}}
            <div>
                <label for="tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Aula</label>
                <select id="tipo" wire:model="tipo"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="simples">Simples (1 tempo)</option>
                    <option value="dupla">Dupla (2 tempos consecutivos)</option>
                    <option value="tripla">Tripla (3 tempos consecutivos)</option>
                </select>
                @error('tipo') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Aulas Consecutivas --}}
            <div class="flex items-center">
                <input type="checkbox" id="aulas_consecutivas" wire:model="aulas_consecutivas"
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="aulas_consecutivas" class="ml-2 block text-sm text-gray-900">Aulas devem ser consecutivas?</label>
                @error('aulas_consecutivas') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Máximo de Aulas por Dia --}}
            <div>
                <label for="max_aulas_dia" class="block text-sm font-medium text-gray-700 mb-1">Máximo de Aulas por Dia</label>
                <input type="number" id="max_aulas_dia" wire:model="max_aulas_dia" min="1" max="5"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('max_aulas_dia') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Mínimo Intervalo de Dias --}}
            <div>
                <label for="min_intervalo_dias" class="block text-sm font-medium text-gray-700 mb-1">Mínimo Intervalo de Dias (entre aulas da mesma disciplina)</label>
                <input type="number" id="min_intervalo_dias" wire:model="min_intervalo_dias" min="0" max="6"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('min_intervalo_dias') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Preferência de Período --}}
            <div>
                <label for="preferencia_periodo" class="block text-sm font-medium text-gray-700 mb-1">Preferência de Período</label>
                <select id="preferencia_periodo" wire:model="preferencia_periodo"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="qualquer">Qualquer</option>
                    <option value="manha">Manhã</option>
                    <option value="tarde">Tarde</option>
                    <option value="noite">Noite</option>
                </select>
                @error('preferencia_periodo') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Dias Preferidos --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dias Preferidos</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                    @foreach($diasDaSemanaOpcoes as $value => $label)
                        <div class="flex items-center">
                            <input type="checkbox" id="dia_preferido_{{ $value }}" value="{{ $value }}" wire:model="dias_preferidos"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="dia_preferido_{{ $value }}" class="ml-2 block text-sm text-gray-900">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
                @error('dias_preferidos') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                @error('dias_preferidos.*') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Tempos Preferidos --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tempos Preferidos</label>
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2">
                    @foreach($temposDeAulaOpcoes as $value => $label)
                        <div class="flex items-center">
                            <input type="checkbox" id="tempo_preferido_{{ $value }}" value="{{ $value }}" wire:model="tempos_preferidos"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="tempo_preferido_{{ $value }}" class="ml-2 block text-sm text-gray-900">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
                @error('tempos_preferidos') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                @error('tempos_preferidos.*') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- Observações --}}
            <div>
                <label for="observacoes" class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                <textarea id="observacoes" wire:model="observacoes" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Notas adicionais sobre esta aula..."></textarea>
                @error('observacoes') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <a href="{{ route('horarios.configurar', ['horario' => $aula->horario_id, 'etapa' => 2]) }}"
                   class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Cancelar
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>
