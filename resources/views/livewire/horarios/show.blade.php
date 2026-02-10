{{-- resources/views/livewire/horarios/show.blade.php --}}

<div>
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('horarios.index') }}" wire:navigate 
           class="text-blue-600 hover:text-blue-700 flex items-center mb-4 transition-colors">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Voltar para listagem
        </a>

        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $horario->nome }}</h2>
                <p class="text-gray-600 mt-1">
                    {{ $horario->ano }}/{{ $horario->semestre }} • 
                    <span class="px-2 py-1 text-xs font-medium rounded-full
                        {{ $horario->status === 'ativo' ? 'bg-green-100 text-green-800' : '' }}
                        {{ $horario->status === 'concluido' ? 'bg-blue-100 text-blue-800' : '' }}
                        {{ $horario->status === 'em_geracao' ? 'bg-yellow-100 text-yellow-800' : '' }}
                        {{ $horario->status === 'rascunho' ? 'bg-gray-100 text-gray-800' : '' }}
                    ">
                        {{ ucfirst(str_replace('_', ' ', $horario->status)) }}
                    </span>
                </p>
            </div>

            <div class="flex items-center space-x-3">
                @if($horario->alocacoes->count() === 0)
                    <button 
                        wire:click="generateSchedule"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Gerar Horário
                    </button>
                @else
                    <button 
                        wire:click="exportPdf"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors flex items-center"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Exportar PDF
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if(session()->has('info'))
        <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ session('info') }}
        </div>
    @endif

    @if($horario->alocacoes->count() === 0)
        <!-- Empty State -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Horário ainda não foi gerado</h3>
            <p class="text-gray-600 mb-6">
                Este horário está vazio. Clique no botão abaixo para iniciar a geração automática usando o algoritmo genético.
            </p>
            <button 
                wire:click="generateSchedule"
                class="inline-flex items-center px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Gerar Horário Automaticamente
            </button>
        </div>
    @else
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <p class="text-sm text-gray-600">Total de Alocações</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $horario->alocacoes->count() }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <p class="text-sm text-gray-600">Turmas</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $horario->alocacoes->pluck('turma_id')->unique()->count() }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <p class="text-sm text-gray-600">Professores</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $horario->alocacoes->pluck('professor_id')->unique()->count() }}</p>
            </div>
            @if($horario->fitness_score)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <p class="text-sm text-gray-600">Fitness Score</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($horario->fitness_score, 2) }}%</p>
                </div>
            @endif
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex-1 mr-4">
                    <label for="turma" class="block text-sm font-medium text-gray-700 mb-2">
                        Selecione uma Turma
                    </label>
                    <select 
                        id="turma"
                        wire:model.live="turmaId"
                        class="w-full max-w-md border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        @foreach($this->turmas as $turma)
                            <option value="{{ $turma->id }}">{{ $turma->nome }} ({{ $turma->codigo }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Grade de Horários -->
        @if($turmaId)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                                    Horário
                                </th>
                                @foreach($this->diasSemana as $dia)
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ $dia }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($this->horarios as $hora => $label)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs font-medium text-gray-700 bg-gray-50">
                                        {{ $label }}
                                    </td>
                                    @foreach(array_keys($this->diasSemana) as $dia)
                                        <td class="px-2 py-2 text-center border-l border-gray-200">
                                            @if(isset($this->grade[$dia][$hora]))
                                                @php
                                                    $alocacao = $this->grade[$dia][$hora];
                                                @endphp
                                                <div class="rounded-lg p-2 text-xs" 
                                                     style="background-color: {{ $alocacao->disciplina->cor }}20; border-left: 3px solid {{ $alocacao->disciplina->cor }};">
                                                    <div class="font-semibold" style="color: {{ $alocacao->disciplina->cor }};">
                                                        {{ $alocacao->disciplina->codigo }}
                                                    </div>
                                                    <div class="text-gray-600 text-xs mt-1">
                                                        {{ Str::limit($alocacao->professor->nome, 20) }}
                                                    </div>
                                                </div>
                                            @else
                                                <div class="text-gray-300">-</div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Legenda -->
            <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Legenda de Disciplinas</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @php
                        $disciplinas = $horario->alocacoes->pluck('disciplina')->unique('id');
                    @endphp
                    @foreach($disciplinas as $disciplina)
                        <div class="flex items-center">
                            <div class="w-4 h-4 rounded mr-2" style="background-color: {{ $disciplina->cor }};"></div>
                            <span class="text-xs text-gray-700">{{ $disciplina->codigo }} - {{ $disciplina->nome }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
