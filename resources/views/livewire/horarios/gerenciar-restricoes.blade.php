{{-- resources/views/livewire/horarios/gerenciar-restricoes.blade.php --}}
<div class="p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Restrições de Tempo</h2>
        <p class="text-gray-600 mt-1">Defina bloqueios e preferências de horário</p>
    </div>

    {{-- Seleção de Entidade --}}
    <div class="bg-white rounded-lg border border-gray-200 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Tipo de Entidade --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Tipo de Entidade
                </label>
                <div class="flex space-x-2">
                    <button 
                        wire:click="$set('tipoEntidade', 'professor')"
                        class="flex-1 px-4 py-3 rounded-lg border-2 transition-all
                            {{ $tipoEntidade === 'professor' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 hover:border-gray-400' }}">
                        <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span class="text-sm font-medium">Professor</span>
                    </button>
                    <button 
                        wire:click="$set('tipoEntidade', 'turma')"
                        class="flex-1 px-4 py-3 rounded-lg border-2 transition-all
                            {{ $tipoEntidade === 'turma' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 hover:border-gray-400' }}">
                        <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="text-sm font-medium">Turma</span>
                    </button>
                    <button 
                        wire:click="$set('tipoEntidade', 'disciplina')"
                        class="flex-1 px-4 py-3 rounded-lg border-2 transition-all
                            {{ $tipoEntidade === 'disciplina' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 hover:border-gray-400' }}">
                        <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <span class="text-sm font-medium">Disciplina</span>
                    </button>
                </div>
            </div>

            {{-- Seleção Específica --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Selecionar {{ ucfirst($tipoEntidade) }}
                </label>
                <select 
                    wire:model.live="entidadeSelecionada"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Escolha um {{ $tipoEntidade }}...</option>
                    @foreach($entidades as $entidade)
                        <option value="{{ $entidade->id }}">{{ $entidade->nome }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Ações em Massa --}}
        @if($entidadeSelecionada)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Ações Rápidas:</span>
                    <div class="flex space-x-2">
                        <button 
                            wire:click="aplicarBloqueioMassa('livre')"
                            class="px-3 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">
                            Liberar Tudo
                        </button>
                        <button 
                            wire:click="aplicarBloqueioMassa('preferencial')"
                            class="px-3 py-1 text-xs font-medium text-yellow-700 bg-yellow-50 border border-yellow-300 rounded hover:bg-yellow-100">
                            Marcar Tudo Preferencial
                        </button>
                        <button 
                            wire:click="aplicarBloqueioMassa('bloqueado')"
                            class="px-3 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-300 rounded hover:bg-red-100">
                            Bloquear Tudo
                        </button>
                        <button 
                            wire:click="limparRestricoes"
                            wire:confirm="Tem certeza que deseja remover todas as restrições?"
                            class="px-3 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">
                            Limpar
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Grade de Restrições --}}
    @if($entidadeSelecionada)
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            {{-- Legenda --}}
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Clique nos horários para alternar status:</span>
                    <div class="flex items-center space-x-4 text-xs">
                        <div class="flex items-center space-x-1">
                            <div class="w-4 h-4 bg-green-100 border border-green-300 rounded"></div>
                            <span class="text-gray-600">Livre</span>
                        </div>
                        <div class="flex items-center space-x-1">
                            <div class="w-4 h-4 bg-yellow-100 border border-yellow-300 rounded"></div>
                            <span class="text-gray-600">Preferencial</span>
                        </div>
                        <div class="flex items-center space-x-1">
                            <div class="w-4 h-4 bg-red-100 border border-red-300 rounded"></div>
                            <span class="text-gray-600">Bloqueado</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabela --}}
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tempo
                            </th>
                            @foreach($diasSemana as $dia)
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ $this->getDiaNome($dia) }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($tempos as $tempo)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ $tempo }}º tempo
                                </td>
                                @foreach($diasSemana as $dia)
                                    @php
                                        $key = "{$dia}_{$tempo}";
                                        $status = $restricoes[$key] ?? 'livre';
                                        $bgClass = match($status) {
                                            'livre' => 'bg-green-100 border-green-300 hover:bg-green-200',
                                            'preferencial' => 'bg-yellow-100 border-yellow-300 hover:bg-yellow-200',
                                            'bloqueado' => 'bg-red-100 border-red-300 hover:bg-red-200',
                                            default => 'bg-gray-50 border-gray-300',
                                        };
                                    @endphp
                                    <td class="px-2 py-2">
                                        <button 
                                            wire:click="alterarStatus({{ $dia }}, {{ $tempo }})"
                                            @contextmenu.prevent="$wire.abrirModalEdicao({{ $dia }}, {{ $tempo }})"
                                            class="w-full h-12 rounded-lg border-2 transition-all {{ $bgClass }}"
                                            title="Clique para alternar | Clique direito para editar">
                                        </button>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="text-center py-12 bg-gray-50 rounded-lg">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Selecione uma Entidade</h3>
            <p class="text-gray-600">Escolha um professor, turma ou disciplina para definir restrições</p>
        </div>
    @endif

    {{-- Modal de Edição Detalhada --}}
    @if($modalEdicao)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('modalEdicao', false)"></div>

                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Editar Restrição - {{ $this->getDiaNome($edicaoDia) }} / {{ $edicaoTempo }}º tempo
                    </h3>

                    <div class="space-y-4">
                        {{-- Status --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select 
                                wire:model="edicaoStatus"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="livre">Livre</option>
                                <option value="preferencial">Preferencial (evitar)</option>
                                <option value="bloqueado">Bloqueado</option>
                            </select>
                        </div>

                        {{-- Peso --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Peso (1-10)
                            </label>
                            <input 
                                type="range" 
                                wire:model.live="edicaoPeso"
                                min="1" 
                                max="10"
                                class="w-full">
                            <div class="text-center text-sm text-gray-600">{{ $edicaoPeso }}</div>
                        </div>

                        {{-- Motivo --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Motivo</label>
                            <textarea 
                                wire:model="edicaoMotivo"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="Ex: Reunião pedagógica, horário de almoço, etc."></textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end space-x-3">
                        <button 
                            wire:click="$set('modalEdicao', false)"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button 
                            wire:click="salvarEdicao"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                            Salvar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
