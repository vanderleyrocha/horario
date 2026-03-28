<div class="space-y-6" x-data="{
    draggedAllocationId: null,
    isDragging: false,
    handleDrop(el, day, time) {
        if (this.draggedAllocationId) {
            const targetCell = el;

            if (targetCell.children.length === 0 || targetCell.children[0].classList.contains('h-14')) {
                $wire.call('handleDrop', this.draggedAllocationId, day, time);
            } else {
                console.warn('Target cell is not empty.');
            }
        }
        this.draggedAllocationId = null;
        this.isDragging = false;
    },
    handleDropToUnallocated() {
        if (this.draggedAllocationId) {
            $wire.call('moveToUnallocated', this.draggedAllocationId);
        }
        this.draggedAllocationId = null;
        this.isDragging = false;
    }
}">

    <div class="bg-white rounded-lg shadow p-6 border">

        <div class="flex gap-4">

            <select wire:model.live="view" class="border rounded px-3 py-2 text-sm">
                <option value="turmas">Grade por Turma</option>
                <option value="professores">Grade por Professor</option>
            </select>

            <select wire:model.live="entidadeId" class="border rounded px-3 py-2 text-sm">
                @foreach ($this->entidades as $e)
                    <option value="{{ $e->id }}">{{ $e->nome }}</option>
                @endforeach
            </select>

        </div>

    </div>

    <div class="bg-white rounded-lg shadow border p-6">

        <div class="overflow-x-auto">

            <table class="min-w-full border border-gray-200">

                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-xs border">Horário</th>

                        @foreach ($this->diasSemana as $dayKey => $dayLabel)
                            <th class="px-3 py-3 text-xs border">
                                {{ $dayLabel }}
                            </th>
                        @endforeach

                    </tr>
                </thead>

                <tbody>

                    @foreach ($this->timeSlots as $timeKey => $timeLabel)
                        <tr>

                            <td class="px-3 py-2 text-xs border bg-gray-50">
                                {{ $timeLabel }}
                            </td>

                            @foreach ($this->diasSemana as $dayKey => $dayLabel)
                                @php
                                    $cell = $this->grade[$dayKey][$timeKey] ?? null;
                                @endphp

                                <td class="border p-1"
                                    @dragover.prevent="isDragging = true"
                                    @dragleave.prevent="isDragging = false"
                                    @drop="handleDrop($el, '{{ $dayKey }}', '{{ $timeKey }}')">

                                    @if ($cell && $cell['type'] === 'start')
                                        @php
                                            $allocation = $cell['allocation'];
                                            $palette = $this->colorForProfessor($allocation->professor_id);
                                        @endphp

                                        <div id="allocation-{{ $allocation->id }}"
                                             draggable="true"
                                             @dragstart="draggedAllocationId = {{ $allocation->id }}; isDragging = true"
                                             @dragend="draggedAllocationId = null; isDragging = false"
                                             class="p-2 rounded cursor-move {{ $palette['bg'] }} {{ $palette['text'] }} {{ $palette['border'] }}">

                                            <div class="text-xs font-semibold">
                                                {{ $allocation->disciplina->codigo }}
                                            </div>

                                            <div class="text-xs">
                                                {{ $allocation->professor->nome_abreviado }}
                                            </div>

                                            <div class="text-[10px]">
                                                Bloco {{ $cell['span'] }}x
                                            </div>

                                        </div>
                                    @elseif($cell && $cell['type'] === 'continuation')
                                        <div class="h-14 bg-gray-100 border-dashed border"></div>
                                    @else
                                        <div class="h-14"></div>
                                    @endif

                                </td>
                            @endforeach

                        </tr>
                    @endforeach

                </tbody>

            </table>

        </div>

    </div>

    <div class="bg-white rounded-lg shadow border p-6">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Aulas não alocadas</h3>
                <p class="text-xs text-gray-500">
                    Arraste um bloco da grade para esta área para remover a alocação.
                </p>
            </div>

            @if ($this->unallocatedAulas->isNotEmpty())
                <span class="px-2 py-1 text-xs rounded-full bg-amber-100 text-amber-800">
                    {{ $this->unallocatedAulas->sum('faltantes') }} pendente(s)
                </span>
            @endif
        </div>

        <div class="rounded-lg border-2 border-dashed p-4 transition"
             :class="isDragging ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-gray-50'"
             @dragover.prevent="isDragging = true"
             @dragleave.prevent="isDragging = false"
             @drop.prevent="handleDropToUnallocated()">

            @if ($this->unallocatedAulas->isEmpty())
                <div class="text-sm text-gray-600">
                    Nenhuma aula não alocada para o filtro atual.
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                    @foreach ($this->unallocatedAulas as $naoAlocada)
                        <div class="rounded-lg border border-amber-200 bg-white p-3">
                            <div class="text-xs font-semibold text-gray-900">
                                {{ $naoAlocada['disciplina_codigo'] }} - {{ $naoAlocada['disciplina_nome'] }}
                            </div>

                            <div class="text-xs text-gray-600 mt-1">
                                Turma: {{ $naoAlocada['turma'] }}
                            </div>

                            <div class="text-xs text-gray-600">
                                Professor: {{ $naoAlocada['professor'] }}
                            </div>

                            <div class="mt-2 text-xs font-medium text-amber-800">
                                Faltando {{ $naoAlocada['faltantes'] }} de {{ $naoAlocada['aulas_semana'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

</div>
