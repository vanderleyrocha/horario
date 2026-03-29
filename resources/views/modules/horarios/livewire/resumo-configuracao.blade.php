{{-- resources/views/livewire/horarios/resumo-configuracao.blade.php --}}
<div class="p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Resumo da Configuração</h2>
        <p class="text-gray-600 mt-1">Revise todas as configurações antes de gerar o horário</p>
    </div>

    {{-- Status Geral (ex.: total de aulas, tempos necessários, taxa de ocupação) --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total de Aulas</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $estatisticas['total_aulas'] }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
                        </path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Outros cards de estatísticas (ajustar conforme necessário) --}}
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Tempos Necessários</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $estatisticas['total_tempos_necessarios'] }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Taxa de Ocupação</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $estatisticas['taxa_ocupacao'] }}%</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M18 14v4m0 0l3-3m-3 3l-3-3M4 12h2.5M10 12h2.5M16 12h2.5"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Entidades Únicas</p>
                    <p class="text-2xl font-bold text-gray-900">
                        P: {{ $estatisticas['professores'] }} | D: {{ $estatisticas['disciplinas'] }} | T: {{ $estatisticas['turmas'] }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h-1a4 4 0 01-4-4V8a4 4 0 014-4h1a4 4 0 014 4v8a4 4 0 01-4 4zM7 20h-1a4 4 0 01-4-4V8a4 4 0 014-4h1a4 4 0 014 4v8a4 4 0 01-4 4z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Avisos de ocupação alta --}}
    @if ($estatisticas['taxa_ocupacao'] > 90)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-red-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-12a1 1 0 10-2 0v4a1 1 0 102 0V6zm0 8a1 1 0 10-2 0h2z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-sm text-red-800">
                    A taxa de ocupação está muito alta ({{ $estatisticas['taxa_ocupacao'] }}%). Isso pode dificultar a geração de um horário viável. Considere ajustar as aulas ou a configuração
                    básica.
                </p>
            </div>
        </div>
    @elseif($estatisticas['taxa_ocupacao'] > 75)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd"
                        d="M8.257 3.099c.765-1.3 2.647-1.3 3.412 0l7.535 12.982c.765 1.3-.192 2.92-1.706 2.92H2.428c-1.514 0-2.471-1.62-1.706-2.92L8.257 3.099zM10 13a1 1 0 100-2 1 1 0 000 2zm0-6a1 1 0 100-2 1 1 0 000 2z"
                        clip-rule="evenodd"></path>
                </svg>
                <p class="text-sm text-yellow-800">
                    A taxa de ocupação está alta ({{ $estatisticas['taxa_ocupacao'] }}%). Pode ser um desafio gerar um horário otimizado.
                </p>
            </div>
        </div>
    @endif

    {{-- Cards de Revisão (Configuração Básica, Restrições, Aulas por Turma) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Configuração Básica --}}
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">Configuração Básica</h3>
                <button wire:click="voltarParaEdicao(1)" class="text-xs text-blue-600 hover:text-blue-700">
                    Editar
                </button>
            </div>
            <div class="p-4 space-y-3">
                {{-- ✅ CORRIGIDO: Proteção contra $horario->configuracaoHorario ser null --}}
                @if ($horario->configuracaoHorario)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Escola:</span>
                        <span class="font-medium text-gray-900">
                            {{ $horario->configuracaoHorario->nome_escola ?: 'Não informado' }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Aulas por dia:</span>
                        <span class="font-medium text-gray-900">
                            {{ $horario->configuracaoHorario->aulas_por_dia }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Dias da semana:</span>
                        <span class="font-medium text-gray-900">
                            {{ $horario->configuracaoHorario->dias_semana }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Horário de início:</span>
                        <span class="font-medium text-gray-900">
                            {{ $horario->configuracaoHorario->horario_inicio }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Horário de fim:</span>
                        <span class="font-medium text-gray-900">
                            {{ $horario->configuracaoHorario->horario_fim }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Duração da aula:</span>
                        <span class="font-medium text-gray-900">
                            {{ $horario->configuracaoHorario->duracao_aula_minutos }} min
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Duração do intervalo:</span>
                        <span class="font-medium text-gray-900">
                            {{ $horario->configuracaoHorario->duracao_intervalo_minutos }} min
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Permitir janelas:</span>
                        <span class="font-medium text-gray-900">
                            {{ $horario->configuracaoHorario->permitir_janelas ? 'Sim' : 'Não' }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Agrupar disciplinas:</span>
                        <span class="font-medium text-gray-900">
                            {{ $horario->configuracaoHorario->agrupar_disciplinas ? 'Sim' : 'Não' }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Máx. aulas seguidas:</span>
                        <span class="font-medium text-gray-900">
                            {{ $horario->configuracaoHorario->max_aulas_seguidas }}
                        </span>
                    </div>
                @else
                    <p class="text-sm text-red-600">⚠️ Configuração básica não definida</p>
                @endif
            </div>
        </div>

        {{-- Restrições de Tempo --}}
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">Restrições de Tempo</h3>
                <button wire:click="voltarParaEdicao(3)" class="text-xs text-blue-600 hover:text-blue-700">
                    Editar
                </button>
            </div>
            <div class="p-4">
                @if (array_sum($restricoes) > 0)
                    <div class="space-y-3">
                        @if (isset($restricoes['bloqueado']) && $restricoes['bloqueado'] > 0)
                            <div class="flex items-center space-x-2 text-sm text-gray-700">
                                <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <span>{{ $restricoes['bloqueado'] }} tempos bloqueados</span>
                            </div>
                        @endif
                        @if (isset($restricoes['preferencial']) && $restricoes['preferencial'] > 0)
                            <div class="flex items-center space-x-2 text-sm text-gray-700">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <span>{{ $restricoes['preferencial'] }} tempos preferenciais</span>
                            </div>
                        @endif
                        @if (isset($restricoes['evitar']) && $restricoes['evitar'] > 0)
                            <div class="flex items-center space-x-2 text-sm text-gray-700">
                                <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.3 2.647-1.3 3.412 0l7.535 12.982c.765 1.3-.192 2.92-1.706 2.92H2.428c-1.514 0-2.471-1.62-1.706-2.92L8.257 3.099zM10 13a1 1 0 100-2 1 1 0 000 2zm0-6a1 1 0 100-2 1 1 0 000 2z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <span>{{ $restricoes['evitar'] }} tempos a evitar</span>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-gray-600">Nenhuma restrição de tempo configurada.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Aulas Configuradas por Turma --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden mb-6">
        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Aulas Configuradas por Turma</h3>
            <button wire:click="voltarParaEdicao(2)" class="text-xs text-blue-600 hover:text-blue-700">
                Editar
            </button>
        </div>
        <div class="p-4">
            @if ($aulasPorTurma->count() > 0)
                <div class="space-y-6">
                    @php
                        $aulasPorDia = $horario->configuracaoHorario->aulas_por_dia ?? 5; // Padrão se não configurado
                        $totalDias = $horario->configuracaoHorario->dias_semana ?? 5;
                    @endphp

                    @foreach ($aulasPorTurma as $dados)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h4 class="font-semibold text-gray-900">{{ $dados['turma']->nome }}</h4>
                                    <p class="text-sm text-gray-600">
                                        {{ $dados['total_aulas'] }} aulas configuradas • {{ $dados['total_tempos'] }} tempos necessários
                                    </p>
                                </div>
                                <span
                                    class="px-2 py-1 text-xs font-medium rounded-full
                                    {{ $dados['turma']->turno === 'matutino' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $dados['turma']->turno === 'vespertino' ? 'bg-orange-100 text-orange-800' : '' }}
                                    {{ $dados['turma']->turno === 'noturno' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                    {{ $dados['turma']->turno === 'integral' ? 'bg-indigo-100 text-indigo-800' : '' }}">
                                    {{ ucfirst($dados['turma']->turno) }}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2 mb-4">
                                @foreach ($dados['disciplinas'] as $disciplina)
                                    <span class="px-2 py-1 text-xs font-medium rounded border"
                                        style="background-color: {{ $disciplina->cor }}22; border-color: {{ $disciplina->cor }}; color: {{ $disciplina->cor }}">
                                        {{ $disciplina->codigo }}
                                    </span>
                                @endforeach
                            </div>

                            {{-- ✅ ATUALIZADO: Quadro de Horários Detalhado para a Turma --}}
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                                                Tempo
                                            </th>
                                            @for ($dia = 1; $dia <= $totalDias; $dia++)
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    {{ $diasDaSemana[$dia]['display'] }}
                                                </th>
                                            @endfor
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @for ($tempo = 1; $tempo <= $aulasPorDia; $tempo++)
                                            <tr>
                                                <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-700 border-r border-gray-100">
                                                    {{ $tempo }}º Tempo
                                                </td>
                                                @for ($dia = 1; $dia <= $totalDias; $dia++)
                                                    @php
                                                        $diaString = $diasDaSemana[$dia]['string'];
                                                        $alocacao = $dados['horario_detalhado'][$diaString][$tempo] ?? null;
                                                    @endphp
                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-800 text-center">
                                                        @if ($alocacao && $alocacao->aula && $alocacao->aula->disciplina)
                                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                                                style="background-color: {{ $alocacao->aula->disciplina->cor }}22; color: {{ $alocacao->aula->disciplina->cor }};">
                                                                {{ $alocacao->aula->disciplina->codigo }}
                                                            </span>
                                                        @else
                                                            <span class="text-gray-400">-</span>
                                                        @endif
                                                    </td>
                                                @endfor
                                            </tr>
                                        @endfor
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    <h4 class="text-lg font-semibold text-gray-900 mb-2">Nenhuma aula configurada</h4>
                    <p class="text-gray-600 mb-4">Adicione aulas para gerar o horário</p>
                    <button wire:click="voltarParaEdicao(2)" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Configurar Aulas
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Botões finais (Iniciar Geração / Salvar e Sair) --}}
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200 p-6">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Pronto para Gerar o Horário?</h3>
                {{-- ✅ CORRIGIDO: Proteção contra $prontoParaGerar ser null --}}
                @if ($prontoParaGerar)
                    <p class="text-sm text-gray-700 mb-4">
                        Todas as configurações estão prontas. O algoritmo genético irá processar as informações…
                    </p>
                    <div class="flex items-center space-x-3">
                        <button wire:click="iniciarGeracao" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-12a1 1 0 10-2 0v4a1 1 0 102 0V6zm0 8a1 1 0 10-2 0h2z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Iniciar Geração do Horário</span>
                        </button>
                        <a href="{{ route('horarios.index') }}" wire:navigate class="px-4 py-3 bg-white text-gray-700 rounded-lg hover:bg-gray-50 border border-gray-300">
                            Salvar e Sair
                        </a>
                    </div>
                @else
                    <div class="bg-white border border-red-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <svg class="w-5 h-5 text-red-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-12a1 1 0 10-2 0v4a1 1 0 102 0V6zm0 8a1 1 0 10-2 0h2z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="flex-1">
                                <h4 class="text-sm font-semibold text-red-900 mb-2">Configuração Incompleta</h4>
                                <ul class="text-sm text-red-800 space-y-1">
                                    {{-- ✅ CORRIGIDO: Proteção contra $horario->configuracaoHorario ser null --}}
                                    @if (!$horario->configuracaoHorario)
                                        <li>• Configure os dados básicos do horário</li>
                                    @endif
                                    @if ($estatisticas['total_aulas'] === 0)
                                        <li>• Adicione pelo menos uma aula</li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
