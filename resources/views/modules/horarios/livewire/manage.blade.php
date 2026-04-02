<div>

    {{-- HEADER --}}
    <div class="mb-6">

        <a
            href="{{ route('horarios.index') }}"
            wire:navigate
            class="mb-4 inline-flex items-center text-blue-600"
        >
            ← Voltar
        </a>

        <div class="rounded-xl border bg-white p-6 shadow">

            <div class="flex items-center justify-between">

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
    <div class="rounded-xl border bg-white shadow">

        <div class="flex space-x-2 border-b p-2">

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
                <button
                    wire:click="setTab('{{ $key }}')"
                    class="{{ $tab === $key ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }} rounded-lg px-4 py-2 text-sm font-medium"
                >
                    {{ $label }}
                </button>
            @endforeach

        </div>


        {{-- CONTEÚDO DINÂMICO --}}
        <div class="p-6">

            @switch($tab)
                @case('overview')
                    @include('modules.horarios.livewire.tabs.overview')
                @break

                @case('constraints')
                    @livewire(\App\Modules\Horarios\UI\Livewire\GerenciarConstraints::class, ['horario' => $horario], key('horarios-constraints-' . $horario->id))
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
                    @livewire(\App\Modules\AG\UI\Livewire\ExecutionCenter::class, ['horario' => $horario], key('algoritmo-center-' . $horario->id))
                @break

                @case('diagnostico')
                    @livewire(\App\Modules\AG\UI\Livewire\DiagnosticoInviabilidade::class, [
                        'diagnostico' => $this->diagnosticoData(),
                    ])
                @break
            @endswitch

        </div>

    </div>

</div>
