{{-- resources/views/livewire/horarios/configurar.blade.php --}}
<div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <div class="bg-white shadow sm:rounded-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Configurar Horário: {{ $horario->nome }}</h2>

        {{-- Navegação por Etapas --}}
        <div class="mb-8">
            <nav class="flex space-x-4" aria-label="Progress">
                @for ($i = 1; $i <= $totalEtapas; $i++)
                    <button wire:click="irParaEtapa({{ $i }})"
                        class="px-4 py-2 text-sm font-medium rounded-md
                                {{ $etapaAtual === $i ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-50' }}">
                        Etapa {{ $i }}
                    </button>
                @endfor
            </nav>
        </div>

        {{-- Conteúdo das Etapas --}}
        <div>
            {{-- Etapa 1: Configuração Básica --}}
            @if ($etapaAtual === 1)
                @include('livewire.horarios.partials.etapa-configuracao-basica')
            @endif

            {{-- Etapa 2: Gerenciar Aulas --}}
            @if ($etapaAtual === 2)
                @livewire('horarios.gerenciar-aulas', ['horario' => $horario])
            @endif

            {{-- Etapa 3: Gerenciar Restrições --}}
            @if ($etapaAtual === 3)
                @livewire('horarios.gerenciar-restricoes', ['horario' => $horario])
            @endif

            {{-- ✅ NOVA ETAPA 4: Configuração do Algoritmo Genético --}}
            @if ($etapaAtual === 4)
                @include('livewire.horarios.partials.etapa-configuracao-algoritmo-genetico')
            @endif

            {{-- Etapa 5: Resumo e Geração (antiga Etapa 4) --}}
            @if ($etapaAtual === 5)
                @livewire('horarios.resumo-configuracao', ['horario' => $horario])
            @endif
        </div>

        @if ($etapaAtual != 1 && $etapaAtual != 4)
            <div class="mt-6 flex justify-between">
                <button type="button" wire:click="etapaAnterior"
                    class="px-6 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    << Anterior 
                </button>
                @if ($etapaAtual != 5)
                    <button type="button" wire:click="proximaEtapa"
                        class="px-6 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        Próxima >>
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>
