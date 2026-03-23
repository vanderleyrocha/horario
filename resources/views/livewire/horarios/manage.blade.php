<div>

    {{-- HEADER --}}
    <div class="mb-6">

        <a href="{{ route('horarios.index') }}" wire:navigate class="text-blue-600 mb-4 inline-flex items-center">
            ← Voltar
        </a>

        <div class="bg-white rounded-xl shadow border p-6">

            <div class="flex justify-between items-center">

                <div>
                    <h2 class="text-2xl font-bold text-gray-900">
                        {{ $horario->nome }}
                    </h2>
                    <p class="text-gray-600">
                        {{ $horario->ano }}/{{ $horario->semestre }}
                    </p>
                </div>

                <div class="text-sm text-gray-500">
                    Status: <strong>{{ ucfirst($horario->status) }}</strong>
                </div>

            </div>

        </div>
    </div>


    {{-- TABS --}}
    <div class="bg-white rounded-xl shadow border">

        <div class="border-b flex space-x-2 p-2">

            @php
                $tabs = [
                    'overview' => 'Visão Geral',
                    'config' => 'Configuração',
                    'aulas' => 'Aulas',
                    'restricoes' => 'Restrições',
                    'algoritmo' => 'Algoritmo',
                    'diagnostico' => 'Diagnóstico',
                ];
            @endphp

            @foreach ($this->tabs() as $key => $label)
                <button wire:click="setTab('{{ $key }}')"
                    class="px-4 py-2 rounded-lg text-sm font-medium
                    {{ $tab === $key ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    {{ $label }}
                </button>
            @endforeach

        </div>


        {{-- CONTEÚDO DINÂMICO --}}
        <div class="p-6">

            @switch($tab)
                @case('overview')
                    @include('livewire.horarios.tabs.overview')
                @break

                @case('config')
                    @livewire(\App\Modules\Horarios\UI\Livewire\Configurar::class, ['horario' => $horario])
                @break

                @case('aulas')
                    @livewire(\App\Modules\Horarios\UI\Livewire\GerenciarAulas::class, ['horario' => $horario])
                @break

                @case('restricoes')
                    @livewire(\App\Modules\Horarios\UI\Livewire\GerenciarRestricoes::class, ['horario' => $horario])
                @break

                @case('algoritmo')
                    @livewire(\App\Livewire\Algoritmo\ExecutionCenter::class, ['horario' => $horario], key('algoritmo-center-' . $horario->id))
                @break

                @case('diagnostico')
                    @livewire(\App\Livewire\Ag\DiagnosticoInviabilidade::class, [
                        'diagnostico' => $this->diagnosticoData(),
                    ])
                @break
            @endswitch

        </div>

    </div>

</div>
