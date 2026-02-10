{{-- resources/views/livewire/dashboard.blade.php --}}

<div>
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        @foreach($this->stats as $stat)
            <a href="{{ route($stat['route']) }}" wire:navigate 
               class="bg-white rounded-lg shadow-sm p-6 border border-gray-200 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm text-gray-600 mb-1">{{ $stat['label'] }}</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="text-xs text-{{ $stat['color'] }}-600 mt-2">
                            {{ $stat['ativos'] }} ativo(s)
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-{{ $stat['color'] }}-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-{{ $stat['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stat['icon'] }}"/>
                        </svg>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    <!-- Informações de Carga Horária -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg shadow-sm p-6 border border-blue-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-700 mb-1">Carga Horária Total</p>
                    <p class="text-3xl font-bold text-blue-900">{{ $this->cargaHorariaInfo['total'] }}h</p>
                    <p class="text-xs text-blue-600 mt-2">Por semana</p>
                </div>
                <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg shadow-sm p-6 border border-green-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-green-700 mb-1">Média por Professor</p>
                    <p class="text-3xl font-bold text-green-900">{{ $this->cargaHorariaInfo['media_professor'] }}h</p>
                    <p class="text-xs text-green-600 mt-2">{{ $this->cargaHorariaInfo['total_professores'] }} professores</p>
                </div>
                <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg shadow-sm p-6 border border-purple-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-purple-700 mb-1">Aulas por Turma</p>
                    <p class="text-3xl font-bold text-purple-900">
                        {{ $this->cargaHorariaInfo['total_turmas'] > 0 ? round($this->cargaHorariaInfo['total'] / $this->cargaHorariaInfo['total_turmas'], 1) : 0 }}h
                    </p>
                    <p class="text-xs text-purple-600 mt-2">{{ $this->cargaHorariaInfo['total_turmas'] }} turmas</p>
                </div>
                <svg class="w-12 h-12 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Ações Rápidas</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="{{ route('professores.create') }}" wire:navigate 
               class="flex items-center p-4 border-2 border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <div>
                    <p class="font-medium text-gray-900">Novo Professor</p>
                    <p class="text-sm text-gray-600">Cadastrar professor</p>
                </div>
            </a>

            <a href="{{ route('turmas.create') }}" wire:navigate 
               class="flex items-center p-4 border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 transition-all">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <div>
                    <p class="font-medium text-gray-900">Nova Turma</p>
                    <p class="text-sm text-gray-600">Criar turma</p>
                </div>
            </a>

            <a href="{{ route('horarios.create') }}" wire:navigate 
               class="flex items-center p-4 border-2 border-gray-200 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-all">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-medium text-gray-900">Novo Horário</p>
                    <p class="text-sm text-gray-600">Criar e gerar</p>
                </div>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Activity -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Atividades Recentes</h3>

            @if(count($this->recentActivities) > 0)
                <div class="space-y-4">
                    @foreach($this->recentActivities as $activity)
                        <div class="flex items-start">
                            <div class="w-2 h-2 bg-{{ $activity['color'] }}-600 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-900">{{ $activity['message'] }}</p>
                                <p class="text-xs text-gray-500">{{ $activity['time'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 text-center py-8">Nenhuma atividade recente</p>
            @endif
        </div>

        <!-- Status dos Horários -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Status dos Horários</h3>

            @php
                $horariosAtivo = \App\Models\Horario::where('status', 'ativo')->count();
                $horariosConcluido = \App\Models\Horario::where('status', 'concluido')->count();
                $horariosGeracao = \App\Models\Horario::where('status', 'em_geracao')->count();
                $horariosRascunho = \App\Models\Horario::where('status', 'rascunho')->count();
                $total = $horariosAtivo + $horariosConcluido + $horariosGeracao + $horariosRascunho;
            @endphp

            @if($total > 0)
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-sm text-gray-600">Ativo</span>
                            <span class="text-sm font-medium text-green-600">{{ $horariosAtivo }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: {{ $total > 0 ? ($horariosAtivo / $total * 100) : 0 }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-sm text-gray-600">Concluído</span>
                            <span class="text-sm font-medium text-blue-600">{{ $horariosConcluido }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $total > 0 ? ($horariosConcluido / $total * 100) : 0 }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-sm text-gray-600">Em Geração</span>
                            <span class="text-sm font-medium text-yellow-600">{{ $horariosGeracao }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-yellow-600 h-2 rounded-full" style="width: {{ $total > 0 ? ($horariosGeracao / $total * 100) : 0 }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-sm text-gray-600">Rascunho</span>
                            <span class="text-sm font-medium text-gray-600">{{ $horariosRascunho }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-gray-600 h-2 rounded-full" style="width: {{ $total > 0 ? ($horariosRascunho / $total * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-gray-500 text-center py-8">Nenhum horário cadastrado</p>
            @endif
        </div>
    </div>
</div>
