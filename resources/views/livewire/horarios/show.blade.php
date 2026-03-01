{{-- resources/views/livewire/horarios/show.blade.php --}}

<div>

    <a href="{{ route('horarios.index') }}" wire:navigate class="text-blue-600 mb-4 inline-flex items-center">
        ← Voltar
    </a>

    {{-- HEADER PRINCIPAL --}}
    <div class="bg-white rounded-xl shadow border p-6 mb-6">

        <div class="flex justify-between items-center">

            <div>
                <h2 class="text-2xl font-bold text-gray-900">
                    {{ $horario->nome }}
                </h2>

                <p class="text-gray-600">
                    {{ $horario->ano }}/{{ $horario->semestre }}
                </p>
            </div>

            <div class="flex space-x-3">

                <a href="{{ route('algoritmo.index', $horario) }}" wire:navigate class="px-4 py-2 bg-purple-600 text-white rounded-lg">
                    Algoritmo
                </a>

                <a href="{{ route('horarios.configurar', $horario) }}" wire:navigate class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg">
                    Configurar
                </a>

            </div>
        </div>

        {{-- MENU DE GESTÃO --}}
        <div class="mt-6 border-t pt-4 flex flex-wrap gap-4 text-sm">

            <a href="{{ route('aulas.index', $horario->id) }}" wire:navigate class="px-3 py-2 bg-blue-50 text-blue-700 rounded-lg">
                📚 Gerenciar Aulas
            </a>

            <a href="{{ route('horarios.configurar', $horario) }}" wire:navigate class="px-3 py-2 bg-yellow-50 text-yellow-700 rounded-lg">
                ⛔ Restrições
            </a>

            <button wire:click="generateSchedule" class="px-3 py-2 bg-purple-50 text-purple-700 rounded-lg">
                🧬 Gerar Horário
            </button>

            <button wire:click="exportPdf" class="px-3 py-2 bg-green-50 text-green-700 rounded-lg">
                📄 Exportar PDF
            </button>

        </div>

    </div>

    {{-- HISTÓRICO --}}
    <div class="mb-6">
        <livewire:algoritmo.historico-execucoes :horario="$horario" />
    </div>

    {{-- RESTANTE DO SEU CÓDIGO DE GRADE CONTINUA AQUI --}}
    {{-- (mantive intacta a lógica da grade e estatísticas) --}}

</div>
