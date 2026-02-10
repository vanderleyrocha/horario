{{-- resources/views/livewire/horarios/partials/modal-aula.blade.php --}}
<div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" wire:ignore.self>
    {{-- OVERLAY (Fundo Escuro) --}}
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
         wire:click="fecharModal"></div>

    {{-- PAINEL (Caixa Branca) --}}
    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-4xl overflow-hidden transform transition-all flex flex-col max-h-[90vh]"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

        {{-- CABEÇALHO --}}
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center shrink-0">
            <h3 class="text-lg font-bold text-gray-900">
                {{ $this->editandoId ? 'Editar Aula' : 'Adicionar Nova Aula' }}
            </h3>
            <button wire:click="fecharModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- CORPO --}}
        <div class="p-6 overflow-y-auto">
            <form wire:submit="salvar">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {{-- Turma --}}
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
                        <select wire:model="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Selecione uma Turma...</option>
                            @foreach($this->turmas as $turma)
                                <option value="{{ $turma->id }}">{{ $turma->nome }} - {{ $turma->turno }}</option>
                            @endforeach
                        </select>
                        @error('turma_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- Disciplina --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Disciplina</label>
                        <select wire:model="disciplina_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Selecione a Disciplina...</option>
                            @foreach($this->disciplinas as $disciplina)
                                <option value="{{ $disciplina->id }}">{{ $disciplina->nome }}</option>
                            @endforeach
                        </select>
                        @error('disciplina_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- Professor --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Professor</label>
                        <select wire:model="professor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Selecione o Professor...</option>
                            @foreach($this->professores as $professor)
                                <option value="{{ $professor->id }}">{{ $professor->nome }}</option>
                            @endforeach
                        </select>
                        @error('professor_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- Aulas por Semana --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Aulas por Semana</label>
                        <input wire:model="aulas_semana" type="number" min="1" max="10"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @error('aulas_semana') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- Tipo de Aula --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Aula</label>
                        <select wire:model="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="simples">Simples (1 aula)</option>
                            <option value="dupla">Dobradinha (2 seguidas)</option>
                            <option value="tripla">Bloco Fixo</option>
                        </select>
                        @error('tipo') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- Máximo de Aulas por Dia --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Máximo de Aulas por Dia</label>
                        <input wire:model="max_aulas_dia" type="number" min="1" max="5"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @error('max_aulas_dia') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- Preferência de Período --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preferência de Período</label>
                        <select wire:model="preferencia_periodo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="qualquer">Qualquer período</option>
                            <option value="manha">Manhã</option>
                            <option value="tarde">Tarde</option>
                        </select>
                        @error('preferencia_periodo') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- Aulas Consecutivas --}}
                    <div class="col-span-1 md:col-span-2 flex items-center">
                        <input wire:model="aulas_consecutivas" type="checkbox" id="aulas_consecutivas"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="aulas_consecutivas" class="ml-2 block text-sm text-gray-900">Permitir aulas consecutivas?</label>
                    </div>

                    {{-- Intervalo Mínimo de Dias --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Intervalo Mínimo de Dias</label>
                        <input wire:model="min_intervalo_dias" type="number" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @error('min_intervalo_dias') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- Dias Preferidos --}}
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Dias Preferidos (opcional)</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['1' => 'Segunda', '2' => 'Terça', '3' => 'Quarta', '4' => 'Quinta', '5' => 'Sexta', '6' => 'Sábado'] as $num => $dia)
                                <label class="flex items-center space-x-2 px-3 py-2 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50
                                            {{ in_array($num, $dias_preferidos) ? 'bg-blue-50 border-blue-500' : '' }}">
                                    <input type="checkbox" wire:model="dias_preferidos" value="{{ $num }}"
                                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="text-sm text-gray-700">{{ $dia }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Se não selecionar nenhum, permite qualquer dia</p>
                    </div>

                    {{-- Tempos Preferidos --}}
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tempos Preferidos (opcional)</label>
                        <div class="flex flex-wrap gap-2">
                            @for($i = 1; $i <= ($horario->configuracaoHorario->aulas_por_dia ?? 5); $i++)
                                <label class="flex items-center space-x-2 px-3 py-2 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50
                                            {{ in_array($i, $tempos_preferidos) ? 'bg-blue-50 border-blue-500' : '' }}">
                                    <input type="checkbox" wire:model="tempos_preferidos" value="{{ $i }}"
                                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="text-sm text-gray-700">{{ $i }}º tempo</span>
                                </label>
                            @endfor
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Se não selecionar nenhum, permite qualquer tempo</p>
                    </div>

                    {{-- Observações --}}
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                        <textarea wire:model="observacoes" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Informações adicionais..."></textarea>
                        @error('observacoes') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                </div>
            </form>
        </div>

        {{-- RODAPÉ --}}
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex items-center justify-end space-x-3 shrink-0">
            <button wire:click="fecharModal" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button wire:click="salvar" type="button" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 shadow-sm transition-colors">
                {{ $this->editandoId ? 'Atualizar Aula' : 'Salvar Aula' }}
            </button>
        </div>
    </div>
</div>
