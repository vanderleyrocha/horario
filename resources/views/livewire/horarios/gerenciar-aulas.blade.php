<div class="p-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Gerenciar Aulas</h2>
            <p class="text-gray-600 mt-1">Configure as aulas que serão distribuídas no horário</p>
        </div>
        <button
            wire:click="abrirModal"
            type="button"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center space-x-2 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span>Adicionar Aula</span>
        </button>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Filtros --}}
    <div class="bg-gray-50 rounded-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- Busca --}}
            <div>
                <input
                    type="text"
                    wire:model.live="busca"
                    placeholder="Buscar por professor, disciplina ou turma..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Filtro Turma --}}
            <div>
                <select
                    wire:model.live="filtroTurma"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas as Turmas</option>
                    @foreach ($turmas as $turma)
                        <option value="{{ $turma->id }}">{{ $turma->nome }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filtro Professor --}}
            <div>
                <select
                    wire:model.live="filtroProfessor"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos os Professores</option>
                    @foreach ($professores as $professor)
                        <option value="{{ $professor->id }}">{{ $professor->nome }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Lista de Aulas --}}
    @if ($aulas->count() > 0)
        <div class="space-y-3">
            @foreach ($aulas as $aula)
                <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            {{-- Título --}}
                            <div class="flex items-center space-x-3 mb-2">
                                <div
                                    class="w-4 h-4 rounded"
                                    style="background-color: {{ $aula->disciplina->cor ?? '#6B7280' }}">
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ $aula->disciplina->nome }}
                                </h3>
                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                    {{ $aula->tipo === 'simples' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $aula->tipo === 'dupla' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $aula->tipo === 'tripla' ? 'bg-pink-100 text-pink-800' : '' }}">
                                    {{ ucfirst($aula->tipo) }}
                                </span>
                            </div>

                            {{-- Informações --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm text-gray-600">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span>{{ $aula->professor->nome }}</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <span>{{ $aula->turma->nome }}</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>{{ $aula->aulas_semana }}x por semana (máx {{ $aula->max_aulas_dia }}/dia)</span>
                                </div>
                            </div>

                            {{-- Tags/Badges --}}
                            @if ($aula->aulas_consecutivas || $aula->dias_preferidos || $aula->observacoes)
                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <div class="flex flex-wrap gap-2">
                                        @if ($aula->aulas_consecutivas)
                                            <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">
                                                Aulas Consecutivas
                                            </span>
                                        @endif
                                        @if ($aula->dias_preferidos)
                                            <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">
                                                Dias Preferidos
                                            </span>
                                        @endif
                                        @if ($aula->observacoes)
                                            <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">
                                                Com Observações
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Ações --}}
                        <div class="flex items-center space-x-2 ml-4">
                            <button
                                wire:click="duplicar({{ $aula->id }})"
                                class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                title="Duplicar"
                                type="button">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </button>
                            <button
                                wire:click="editar({{ $aula->id }})"
                                class="p-2 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                title="Editar"
                                type="button">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button
                                wire:click="excluir({{ $aula->id }})"
                                wire:confirm="Tem certeza que deseja excluir esta aula?"
                                class="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                title="Excluir"
                                type="button">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Total de Carga Horária quando filtro de turma está ativo --}}
        @if($filtroTurma)
            @php
                $totalCargaHoraria = $aulas->sum('aulas_semana');
                $turma = \App\Models\Turma::find($filtroTurma);
            @endphp

            <div class="mt-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Carga Horária Total</p>
                            <p class="text-xs text-gray-500">Turma: {{ $turma->nome ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-4xl font-bold text-blue-600">{{ $totalCargaHoraria }}</p>
                        <p class="text-sm text-gray-600">aulas/semana</p>
                    </div>
                </div>

                {{-- Indicador visual adicional --}}
                @if($horario->configuracaoHorario)
                    @php
                        $temposDisponiveis = $horario->configuracaoHorario->aulas_por_dia * $horario->configuracaoHorario->dias_semana;
                        $percentualOcupacao = ($totalCargaHoraria / $temposDisponiveis) * 100;
                    @endphp

                    <div class="mt-4">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="text-gray-600">Ocupação da grade</span>
                            <span class="font-semibold {{ $percentualOcupacao > 90 ? 'text-red-600' : ($percentualOcupacao > 75 ? 'text-yellow-600' : 'text-green-600') }}">
                                {{ number_format($percentualOcupacao, 1) }}%
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500 {{ $percentualOcupacao > 90 ? 'bg-red-500' : ($percentualOcupacao > 75 ? 'bg-yellow-500' : 'bg-green-500') }}"
                                style="width: {{ min($percentualOcupacao, 100) }}%">
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $totalCargaHoraria }} de {{ $temposDisponiveis }} tempos disponíveis
                        </p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Total de Carga Horária quando filtro de professor está ativo --}}
        @if($filtroProfessor)
            @php
                $totalCargaHoraria = $aulas->sum('aulas_semana');
                $professor = \App\Models\professor::find($filtroProfessor);
            @endphp

            <div class="mt-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Carga Horária Total</p>
                            <p class="text-xs text-gray-500">Professor: {{ $professor->nome ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-4xl font-bold text-blue-600">{{ $totalCargaHoraria }}</p>
                        <p class="text-sm text-gray-600">aulas/semana</p>
                    </div>
                </div>

                {{-- Indicador visual adicional --}}
                @if($horario->configuracaoHorario)
                    @php
                        $temposDisponiveis = $horario->configuracaoHorario->aulas_por_dia * $horario->configuracaoHorario->dias_semana;
                        $percentualOcupacao = ($totalCargaHoraria / $temposDisponiveis) * 100;
                    @endphp

                    <div class="mt-4">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="text-gray-600">Ocupação da grade</span>
                            <span class="font-semibold {{ $percentualOcupacao > 90 ? 'text-red-600' : ($percentualOcupacao > 75 ? 'text-yellow-600' : 'text-green-600') }}">
                                {{ number_format($percentualOcupacao, 1) }}%
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500 {{ $percentualOcupacao > 90 ? 'bg-red-500' : ($percentualOcupacao > 75 ? 'bg-yellow-500' : 'bg-green-500') }}"
                                style="width: {{ min($percentualOcupacao, 100) }}%">
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $totalCargaHoraria }} de {{ $temposDisponiveis }} tempos disponíveis
                        </p>
                    </div>
                @endif
            </div>
        @endif


        {{-- Paginação --}}
        <div class="mt-6">
            {{ $aulas->links() }}
        </div>
    @else
        {{-- Estado Vazio --}}
        <div class="text-center py-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Nenhuma aula configurada</h3>
            <p class="text-gray-600 mb-4">
                @if($filtroTurma || $filtroProfessor || $busca)
                    Nenhuma aula encontrada com os filtros aplicados
                @else
                    Adicione as aulas que serão distribuídas no horário
                @endif
            </p>
            <button
                wire:click="abrirModal"
                type="button"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Adicionar Primeira Aula
            </button>
        </div>
    @endif

    {{-- Modal de Adicionar/Editar --}}
    @if ($modalAberto)
        <div wire:key="modal-{{ $editandoId ? 'edit-'.$editandoId : 'create' }}">
            @include('livewire.horarios.partials.modal-aula')
        </div>
    @endif
</div>