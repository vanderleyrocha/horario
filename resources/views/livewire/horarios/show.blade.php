<div class="space-y-6">

    <div class="bg-white rounded-lg shadow p-6 border">

        <div class="flex gap-4">

            <select wire:model.live="view" class="border rounded px-3 py-2 text-sm">
                <option value="turmas">Grade por Turma</option>
                <option value="professores">Grade por Professor</option>
            </select>

            <select wire:model.live="entidadeId" class="border rounded px-3 py-2 text-sm">
                @foreach ($view === 'professores' ? \App\Models\Professor::ativo()->get() : \App\Models\Turma::ativa()->get() as $e)
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

                                <td class="border p-1">

                                    @if ($cell && $cell['type'] === 'start')
                                        @php
                                            $allocation = $cell['allocation'];
                                            $palette = $this->colorForProfessor($allocation->professor_id);
                                        @endphp

                                        <div class="p-2 rounded {{ $palette['bg'] }} {{ $palette['text'] }} {{ $palette['border'] }}">

                                            <div class="text-xs font-semibold">
                                                {{ $allocation->disciplina->nome }}
                                            </div>

                                            <div class="text-xs">
                                                {{ $allocation->professor->nome }}
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

</div>
