<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-lg border p-4 bg-gray-50">
            <p class="text-sm text-gray-500">Status</p>
            <p class="text-lg font-semibold text-gray-800">{{ ucfirst($horario->status) }}</p>
        </div>

        <div class="rounded-lg border p-4 bg-gray-50">
            <p class="text-sm text-gray-500">Alocações</p>
            <p class="text-lg font-semibold text-gray-800">{{ $horario->alocacoes()->count() }}</p>
        </div>

        <div class="rounded-lg border p-4 bg-gray-50">
            <p class="text-sm text-gray-500">Fitness</p>
            <p class="text-lg font-semibold text-gray-800">
                {{ $horario->fitness_score ? number_format($horario->fitness_score, 2) . '%' : '-' }}
            </p>
        </div>
    </div>

    <div class="rounded-lg border p-4">
        <p class="text-sm text-gray-500">Resumo</p>
        <p class="text-gray-700 mt-1">
            Horário de {{ $horario->ano }}/{{ $horario->semestre }} pronto para configuração, gerenciamento de aulas e execução do algoritmo.
        </p>
    </div>
</div>

