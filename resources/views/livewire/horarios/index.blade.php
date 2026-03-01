{{-- resources/views/livewire/horarios/index.blade.php --}}

<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Horários</h2>
            <p class="text-gray-600 mt-1">Gerencie e visualize os horários gerados</p>
        </div>

        <a href="{{ route('horarios.create') }}" wire:navigate class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Novo Horário
        </a>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        @foreach ($horarios as $horario)
            <div class="bg-white rounded-2xl shadow-sm border p-6 hover:shadow-lg transition">

                <div class="flex justify-between items-start mb-4">

                    <div>
                        <h3 class="text-lg font-bold text-gray-900">
                            {{ $horario->nome }}
                        </h3>
                        <p class="text-sm text-gray-500">
                            {{ $horario->ano }}/{{ $horario->semestre }}
                        </p>
                    </div>

                    <div class="flex flex-col items-end space-y-2">
                        <span
                            class="px-2 py-1 text-xs font-semibold rounded-full
                        {{ $horario->status === 'ativo' ? 'bg-green-100 text-green-700' : '' }}
                        {{ $horario->status === 'rascunho' ? 'bg-gray-100 text-gray-700' : '' }}">
                            {{ ucfirst($horario->status) }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 text-sm border-t border-b py-4 mb-4">
                    <div>
                        <p class="text-gray-500">Alocações</p>
                        <p class="font-semibold">{{ $horario->alocacoes->count() }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Fitness</p>
                        <p class="font-semibold">
                            {{ $horario->fitness_score ? number_format($horario->fitness_score, 2) . '%' : '-' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500">Risco</p>
                        <p class="font-semibold">
                            {{ $horario->indice_risco ?? '-' }}/100
                        </p>
                    </div>
                </div>

                {{-- AÇÕES RÁPIDAS --}}
                <div class="grid grid-cols-3 gap-2 mb-2">

                    <a href="{{ route('algoritmo.index', $horario) }}" wire:navigate class="px-3 py-2 bg-purple-600 text-white text-sm rounded-lg text-center">
                        Gerar
                    </a>

                    <a href="{{ route('horarios.manage', $horario) }}" wire:navigate class="px-3 py-2 bg-blue-600 text-white text-sm rounded-lg text-center">
                        Abrir
                    </a>

                    <button wire:click="abrirDiagnostico({{ $horario->id }})" class="px-3 py-2 bg-red-600 text-white text-sm rounded-lg">
                        Diagnóstico
                    </button>
                </div>

                {{-- MENU SECUNDÁRIO --}}
                <div class="flex flex-wrap gap-2 text-xs text-gray-600 mt-2">
                    {{-- 
                    <a href="{{ route('horarios.configurar', $horario) }}" wire:navigate class="hover:text-blue-600">Configurar</a>

                    <a href="{{ route('aulas.index', $horario->id) }}" wire:navigate class="hover:text-blue-600">Aulas</a>

                    <a href="{{ route('horarios.configurar', $horario) }}" wire:navigate class="hover:text-blue-600">Restrições</a> --}}

                </div>

            </div>
        @endforeach
    </div>
</div>
