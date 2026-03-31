<div
    class="space-y-6"
    x-data="{
        draggedKind: null,
        draggedAllocationId: null,
        draggedAulaId: null,
        isDragging: false,
        startAllocationDrag(id) {
            this.draggedKind = 'allocation';
            this.draggedAllocationId = id;
            this.draggedAulaId = null;
            this.isDragging = true;
        },
        startAulaDrag(id) {
            this.draggedKind = 'aula';
            this.draggedAulaId = id;
            this.draggedAllocationId = null;
            this.isDragging = true;
        },
        resetDrag() {
            this.draggedKind = null;
            this.draggedAllocationId = null;
            this.draggedAulaId = null;
            this.isDragging = false;
        },
        canDropInCell(el) {
            if (el.children.length === 0) {
                return true;
            }

            return Array.from(el.children).every((child) => child.classList.contains('h-14'));
        },
        handleDrop(el, day, time) {
            if (this.draggedKind === 'allocation' && this.draggedAllocationId) {
                if (this.canDropInCell(el)) {
                    $wire.call('handleDrop', this.draggedAllocationId, day, time);
                }
            }

            if (this.draggedKind === 'aula' && this.draggedAulaId) {
                if (this.canDropInCell(el)) {
                    $wire.call('allocateUnallocatedAula', this.draggedAulaId, day, time);
                }
            }

            this.resetDrag();
        },
        handleDropToUnallocated() {
            if (this.draggedKind === 'allocation' && this.draggedAllocationId) {
                $wire.call('moveToUnallocated', this.draggedAllocationId);
            }

            this.resetDrag();
        }
    }"
>
    <style>
        .schedule-main,
        .matrix-compact {
            --time-col-width: 6rem;
            --lesson-col-width: 12.5rem;
            --teacher-col-width: 12rem;
        }

        .schedule-main table {
            table-layout: fixed;
            width: max-content;
            border-collapse: collapse;
        }

        .schedule-main .time-col {
            width: var(--time-col-width);
            min-width: var(--time-col-width);
            max-width: var(--time-col-width);
        }

        .schedule-main .lesson-col {
            width: var(--lesson-col-width);
            min-width: var(--lesson-col-width);
            max-width: var(--lesson-col-width);
        }

        .schedule-main .lesson-card {
            min-height: 56px;
            width: 100%;
            overflow: hidden;
        }

        .matrix-compact {
            --matrix-col-width: 7.5rem;
            --matrix-header-row-height: 2.5rem;
        }

        .matrix-compact .matrix-scroll {
            overflow-x: scroll;
            overflow-y: auto;
            max-height: 70vh;
            scrollbar-gutter: stable both-edges;
        }

        .matrix-compact table {
            table-layout: fixed;
            width: max-content;
            border-collapse: separate;
            border-spacing: 0;
        }

        .matrix-compact .teacher-col {
            width: var(--teacher-col-width);
            min-width: var(--teacher-col-width);
            max-width: var(--teacher-col-width);
        }

        .matrix-compact .matrix-col {
            width: var(--matrix-col-width);
            min-width: var(--matrix-col-width);
            max-width: var(--matrix-col-width);
        }

        .matrix-compact .matrix-cell {
            padding: 0.375rem !important;
            min-height: 56px;
            overflow: hidden;
            text-align: left;
            vertical-align: top;
            background: white;
        }

        .matrix-compact .matrix-card {
            min-height: 56px;
            width: 100%;
            overflow: hidden;
            border-radius: 0.375rem;
            padding: 0.5rem;
            border: 1px solid transparent;
        }

        .matrix-compact .sticky-left {
            position: sticky;
            left: 0;
            z-index: 30;
            background: white;
        }

        .matrix-compact .sticky-top-row-1 {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgb(249 250 251);
        }

        .matrix-compact .sticky-top-row-2 {
            position: sticky;
            top: var(--matrix-header-row-height);
            z-index: 40;
            background: rgb(249 250 251);
        }

        .matrix-compact .sticky-corner-top {
            z-index: 60;
        }

        .matrix-compact .sticky-corner-bottom {
            z-index: 55;
        }
    </style>

    @if (session()->has('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6 border">
        <div class="flex gap-4">
            <select wire:model.live="view" class="border rounded px-3 py-2 text-sm">
                <option value="turmas">Grade por Turma</option>
                <option value="professores">Grade por Professor</option>
                <option value="matriz-professores">Matriz Professor x Turma</option>
            </select>

            @if ($this->requiresEntitySelection)
                <select wire:model.live="entidadeId" class="border rounded px-3 py-2 text-sm">
                    @foreach ($this->entidades as $e)
                        <option wire:key="entidade-{{ $view }}-{{ $e->id }}" value="{{ $e->id }}">{{ $e->nome }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>

    @if ($this->requiresEntitySelection)
        <div class="bg-white rounded-lg shadow border p-6 schedule-main">
            <div class="overflow-x-auto">
                <table class="border border-gray-200 bg-white">
                    <colgroup>
                        <col class="time-col">
                        @foreach ($this->diasSemana as $dayKey => $dayLabel)
                            <col class="lesson-col">
                        @endforeach
                    </colgroup>

                    <thead class="bg-gray-50">
                        <tr>
                            <th class="time-col px-3 py-2 text-xs border whitespace-nowrap">Horário</th>
                            @foreach ($this->diasSemana as $dayKey => $dayLabel)
                                <th class="lesson-col px-3 py-2 text-xs border text-center leading-tight" wire:key="day-header-{{ $dayKey }}">
                                    {{ $dayLabel }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($this->timeSlots as $timeKey => $timeLabel)
                            <tr wire:key="time-row-{{ $timeKey }}">
                                <td class="time-col px-3 py-2 text-xs border bg-gray-50 whitespace-nowrap">
                                    {{ $timeLabel }}
                                </td>

                                @foreach ($this->diasSemana as $dayKey => $dayLabel)
                                    @php
                                        $cell = $this->grade[$dayKey][$timeKey] ?? null;
                                    @endphp

                                    <td
                                        class="lesson-col border p-1.5 align-top"
                                        wire:key="cell-{{ $dayKey }}-{{ $timeKey }}"
                                        @dragover.prevent="isDragging = true"
                                        @dragleave.prevent="isDragging = false"
                                        @drop.prevent="handleDrop($el, '{{ $dayKey }}', '{{ $timeKey }}')"
                                    >
                                        @if ($cell && $cell['type'] === 'start')
                                            @php
                                                $allocation = $cell['allocation'];
                                                $palette = $this->colorForProfessor($allocation->professor_id);
                                            @endphp

                                            <div
                                                id="allocation-{{ $allocation->id }}"
                                                draggable="true"
                                                @dragstart="startAllocationDrag({{ $allocation->id }})"
                                                @dragend="resetDrag()"
                                                class="lesson-card rounded p-2 cursor-move border {{ $palette['bg'] }} {{ $palette['text'] }} {{ $palette['border'] }}"
                                            >
                                                <div class="text-xs font-semibold leading-tight truncate">
                                                    {{ $allocation->disciplina->codigo }}
                                                </div>

                                                <div class="text-xs leading-tight truncate mt-0.5">
                                                    {{ $allocation->professor->nome_abreviado }}
                                                </div>

                                                @if ($view === 'professores')
                                                    <div class="text-[11px] leading-tight truncate mt-0.5">
                                                        {{ $allocation->turma->codigo ?? $allocation->turma->nome }}
                                                    </div>
                                                @endif

                                            </div>
                                        @elseif($cell && $cell['type'] === 'continuation')
                                            <div class="h-14 bg-gray-100 border border-dashed rounded"></div>
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
                        Arraste uma aula deste quadro para a grade para alocar manualmente, ou arraste um bloco da grade
                        para cá para remover a alocação.
                    </p>
                </div>

                @if ($this->unallocatedAulas->isNotEmpty())
                    <span class="px-2 py-1 text-xs rounded-full bg-amber-100 text-amber-800">
                        {{ $this->unallocatedAulas->sum('faltantes') }} pendente(s)
                    </span>
                @endif
            </div>

            <div
                class="rounded-lg border-2 border-dashed p-4 transition"
                :class="draggedKind === 'allocation' && isDragging ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-gray-50'"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="handleDropToUnallocated()"
            >
                @if ($this->unallocatedAulas->isEmpty())
                    <div class="text-sm text-gray-600">
                        Nenhuma aula não alocada para o filtro atual.
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        @foreach ($this->unallocatedAulas as $naoAlocada)
                            <div
                                wire:key="unallocated-{{ $naoAlocada['aula_id'] }}"
                                draggable="true"
                                @dragstart="startAulaDrag({{ $naoAlocada['aula_id'] }})"
                                @dragend="resetDrag()"
                                class="rounded-lg border border-amber-200 bg-white p-3 cursor-grab"
                            >
                                <div class="text-xs font-semibold text-gray-900">
                                    {{ $naoAlocada['disciplina_codigo'] }} - {{ $naoAlocada['disciplina_nome'] }}
                                </div>

                                <div class="text-xs text-gray-600 mt-1">
                                    Turma: {{ $naoAlocada['turma_abreviada'] }}
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
    @else
        <div class="bg-white rounded-lg shadow border p-6 matrix-compact">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Matriz geral por professor, dia e tempo</h3>
                    <p class="text-xs text-gray-500">
                        A primeira coluna mostra o professor. Em seguida, cada dia da semana ocupa um bloco de colunas,
                        e cada coluna representa um tempo. Em cada célula aparece a aula correspondente, com a turma.
                    </p>
                </div>
            </div>

            @if ($this->matrixProfessores->isEmpty())
                <div class="text-sm text-gray-600">
                    Nao ha dados suficientes para montar a matriz consolidada.
                </div>
            @else
                <div class="matrix-scroll rounded-lg border border-gray-200">
                    <table class="bg-white">
                        <colgroup>
                            <col class="teacher-col">
                            @foreach ($this->diasSemana as $dayKey => $dayLabel)
                                @foreach ($this->temposNumericos as $tempo)
                                    <col class="matrix-col">
                                @endforeach
                            @endforeach
                        </colgroup>

                        <thead class="bg-gray-50">
                            <tr>
                                <th rowspan="2" class="teacher-col px-2 py-2 text-xs border bg-gray-50 align-middle sticky-left sticky-top-row-1 sticky-corner-top">
                                    Professor
                                </th>

                                @foreach ($this->diasSemana as $dayKey => $dayLabel)
                                    <th
                                        colspan="{{ count($this->temposNumericos) }}"
                                        class="px-2 py-2 text-xs border text-center bg-gray-50 sticky-top-row-1"
                                        wire:key="matrix-day-group-{{ $dayKey }}"
                                    >
                                        {{ $dayLabel }}
                                    </th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach ($this->diasSemana as $dayKey => $dayLabel)
                                    @foreach ($this->temposNumericos as $tempo)
                                        <th
                                            class="matrix-col px-2 py-2 text-[10px] border text-center bg-gray-50 sticky-top-row-2"
                                            wire:key="matrix-tempo-header-{{ $dayKey }}-{{ $tempo }}"
                                        >
                                            {{ $tempo }}º tempo
                                        </th>
                                    @endforeach
                                @endforeach
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($this->matrixProfessores as $professor)
                                <tr wire:key="matrix-professor-row-{{ $professor->id }}">
                                    <td class="teacher-col border px-2 py-1 align-top bg-gray-50 sticky-left">
                                        <div class="text-[11px] font-semibold text-gray-900 leading-tight truncate" title="{{ $professor->nome }}">
                                            {{ $professor->nome_abreviado ?? $professor->nome }}
                                        </div>
                                    </td>

                                    @foreach ($this->diasSemana as $dayKey => $dayLabel)
                                        @foreach ($this->temposNumericos as $tempo)
                                            @php
                                                $entry = $this->professorTempoMatrix[$professor->id][$dayKey][$tempo] ?? null;
                                                $tooltip = $entry
                                                    ? (($entry['disciplina_codigo'] ?? '---') . ' | ' . ($entry['turma_codigo'] ?? 'Turma') . ' | ' . ($entry['horario'] ?? ''))
                                                    : 'Sem alocação';
                                            @endphp

                                            <td
                                                class="matrix-col matrix-cell border"
                                                wire:key="matrix-cell-{{ $professor->id }}-{{ $dayKey }}-{{ $tempo }}"
                                                title="{{ $tooltip }}"
                                            >
                                                @if ($entry)
                                                    @php
                                                        $palette = $this->colorForDisciplina($entry['disciplina_id'] ?? null);
                                                    @endphp

                                                    <div class="matrix-card {{ $palette['bg'] }} {{ $palette['text'] }} {{ $palette['border'] }}">
                                                        <div class="text-xs font-semibold leading-tight truncate">
                                                            {{ $entry['disciplina_codigo'] ?? '---' }}
                                                        </div>

                                                        <div class="text-[11px] leading-tight truncate mt-0.5">
                                                            {{ $entry['turma_codigo'] ?? 'Turma' }}
                                                        </div>

                                                        <div class="text-[10px] leading-tight truncate mt-1 opacity-80">
                                                            {{ $entry['horario'] ?? '' }}
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="h-14"></div>
                                                @endif
                                            </td>
                                        @endforeach
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
