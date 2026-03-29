<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Aulas por turma</h2>
            <p class="mt-1 text-gray-600">
                Selecione uma turma do horario <span class="font-semibold text-gray-800">{{ $horario->nome }}</span> para visualizar e editar suas aulas.
            </p>
        </div>

        <a
            href="{{ route('horarios.manage', $horario) }}"
            wire:navigate
            class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
        >
            Voltar ao horario
        </a>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Turma
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Codigo
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Carga horaria
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Total de aulas
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Status
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                            Acoes
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($turmas as $turma)
                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-green-600">
                                        <span class="text-sm font-medium text-white">
                                            {{ substr($turma->codigo, 0, 2) }}
                                        </span>
                                    </div>

                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $turma->nome }}</div>
                                        <div class="text-xs text-gray-500">Ano {{ $turma->ano }}</div>
                                    </div>
                                </div>
                            </td>

                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                {{ $turma->codigo }}
                            </td>

                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                {{ $turma->ch }}h
                            </td>

                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                {{ $turma->aulas->count() }}
                            </td>

                            <td class="whitespace-nowrap px-6 py-4">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $turma->ativa ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $turma->ativa ? 'Ativa' : 'Inativa' }}
                                </span>
                            </td>

                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <a
                                    href="{{ route('turmas.aulas', $turma) }}"
                                    wire:navigate
                                    class="text-blue-600 transition-colors hover:text-blue-800"
                                >
                                    Gerenciar aulas
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="mt-4 text-gray-500">Nenhuma turma com aulas encontrada para este horario.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
