<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Constraints customizadas</h2>
            <p class="mt-1 text-sm text-gray-600">Gerencie regras explícitas para sincronismo, exclusão mútua e
                posicionamento temporal.</p>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            O tipo da regra fica travado na edição. Para trocar o tipo, crie uma nova constraint.
        </div>
    </div>

    @if (session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            {{ session('error') }}
        </div>
    @endif

    @if ($disciplineOptions === [] && $turmaOptions === [])
        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-700">
            <p class="text-lg font-semibold">Cadastre aulas antes de definir constraints</p>
            <p class="mt-2 text-sm text-slate-600">As regras customizadas operam sobre aulas do horário. Depois de
                cadastrar as aulas, o formulário ficará disponível aqui.</p>
        </div>
    @else
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">
                                {{ $editingId ? 'Editar constraint' : 'Nova constraint' }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-600">Preencha os campos gerais e depois o payload
                                específico do tipo selecionado.</p>
                        </div>

                        @if ($editingId)
                            <button
                                type="button"
                                wire:click="cancelEdit"
                                class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancelar edição
                            </button>
                        @endif
                    </div>
                </div>

                <form
                    wire:submit="save"
                    class="space-y-6 px-6 py-6"
                >
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-slate-700">Nome da constraint</label>
                            <input
                                wire:model.blur="name"
                                type="text"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200"
                                placeholder="Ex: Sincronismo dos laboratórios"
                            />
                            @error('name')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-slate-700">Descrição interna</label>
                            <textarea
                                wire:model.blur="description"
                                rows="2"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200"
                                placeholder="Observação opcional para a equipe."
                            ></textarea>
                            @error('description')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Tipo</label>
                            <select
                                wire:model.live="type"
                                @disabled($editingId !== null)
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 disabled:cursor-not-allowed disabled:bg-slate-100"
                            >
                                <option value="SYNC_SAME_TIMESLOT">Sincronismo no mesmo horário</option>
                                <option value="MUTUAL_EXCLUSION">Exclusão mútua</option>
                                <option value="TIME_PLACEMENT">Posicionamento temporal</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Nível</label>
                            <select
                                wire:model.live="level"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200"
                            >
                                <option value="HARD">Obrigatória</option>
                                <option value="SOFT">Preferencial</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Peso</label>
                            <input
                                wire:model.live="weight"
                                type="number"
                                min="1"
                                max="100"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200"
                            />
                            @error('weight')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <input
                                wire:model.live="isActive"
                                id="constraint-active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                            />
                            <label
                                for="constraint-active"
                                class="text-sm font-medium text-slate-700"
                            >Salvar constraint como ativa</label>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Payload específico</h4>
                        <p class="mt-2 text-sm text-slate-600">As disciplinas definem o grupo principal da regra. As
                            turmas atuam como filtro opcional do grupo esquerdo ou do grupo alvo.</p>

                        @if ($type === 'SYNC_SAME_TIMESLOT')
                            <div class="mt-4 space-y-6">
                                <div class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                                    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-4">
                                        <h5 class="text-sm font-semibold uppercase tracking-wide text-sky-900">Grupo
                                            esquerdo</h5>
                                        <p class="mt-1 text-xs text-slate-600">Escolha uma ou mais disciplinas. Se
                                            quiser restringir o sincronismo a turmas específicas, aplique o filtro
                                            abaixo.</p>
                                        <div class="mt-4 space-y-4">
                                            <div>
                                                <div class="mb-2 flex items-center justify-between gap-3">
                                                    <label
                                                        class="block text-sm font-medium text-slate-700">Disciplinas</label>
                                                    <label
                                                        class="flex items-center gap-2 text-xs font-medium text-slate-600"
                                                    >
                                                        <input
                                                            wire:model.live="leftAllDisciplines"
                                                            type="checkbox"
                                                            class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                                        />
                                                        Selecionar todas
                                                    </label>
                                                </div>
                                                <select
                                                    wire:model.live="leftDisciplineIds"
                                                    multiple
                                                    size="6"
                                                    @disabled($leftAllDisciplines)
                                                    class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 disabled:bg-slate-100"
                                                >
                                                    @foreach ($disciplineOptions as $option)
                                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('leftDisciplineIds')
                                                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div>
                                                <div class="mb-2 flex items-center justify-between gap-3">
                                                    <label class="block text-sm font-medium text-slate-700">Filtro de
                                                        turmas</label>
                                                    <label
                                                        class="flex items-center gap-2 text-xs font-medium text-slate-600"
                                                    >
                                                        <input
                                                            wire:model.live="leftAllTurmas"
                                                            type="checkbox"
                                                            class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                                        />
                                                        Todas as turmas
                                                    </label>
                                                </div>
                                                <select
                                                    wire:model.live="leftTurmaIds"
                                                    multiple
                                                    size="6"
                                                    @disabled($leftAllTurmas)
                                                    class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 disabled:bg-slate-100"
                                                >
                                                    @foreach ($turmaOptions as $option)
                                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                        <h5 class="text-sm font-semibold uppercase tracking-wide text-slate-900">Grupo
                                            direito opcional</h5>
                                        <p class="mt-1 text-xs text-slate-600">Se vazio, o sincronismo acontece dentro
                                            do grupo esquerdo. Se preenchido, o grupo esquerdo sincroniza com essas
                                            disciplinas.</p>
                                        <div class="mt-4">
                                            <div class="mb-2 flex items-center justify-between gap-3">
                                                <label
                                                    class="block text-sm font-medium text-slate-700">Disciplinas</label>
                                                <label
                                                    class="flex items-center gap-2 text-xs font-medium text-slate-600"
                                                >
                                                    <input
                                                        wire:model.live="rightAllDisciplines"
                                                        type="checkbox"
                                                        class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                                    />
                                                    Selecionar todas
                                                </label>
                                            </div>
                                            <select
                                                wire:model.live="rightDisciplineIds"
                                                multiple
                                                size="6"
                                                @disabled($rightAllDisciplines)
                                                class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 disabled:bg-slate-100"
                                            >
                                                @foreach ($disciplineOptions as $option)
                                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-slate-700">Modo de
                                        ocorrência</label>
                                    <select
                                        wire:model.live="occurrenceMode"
                                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200"
                                    >
                                        <option value="ALL">Todas as ocorrências</option>
                                        <option value="AT_LEAST_ONE">Ao menos uma coincidência</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-slate-700">Modo de
                                        correspondência</label>
                                    <select
                                        wire:model.live="matchMode"
                                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200"
                                    >
                                        <option value="ALL_TO_ALL">Todos com todos</option>
                                        <option value="FIRST_WITH_FIRST">Primeiro com primeiro</option>
                                    </select>
                                </div>
                            </div>
                        @elseif ($type === 'MUTUAL_EXCLUSION')
                            <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                                <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-4">
                                    <h5 class="text-sm font-semibold uppercase tracking-wide text-sky-900">Grupo
                                        esquerdo</h5>
                                    <p class="mt-1 text-xs text-slate-600">Selecione duas ou mais disciplinas para
                                        impedir coincidência entre elas, ou combine com um grupo direito para bloquear
                                        coincidência entre conjuntos.</p>
                                    <div class="mt-4 space-y-4">
                                        <div>
                                            <div class="mb-2 flex items-center justify-between gap-3">
                                                <label
                                                    class="block text-sm font-medium text-slate-700">Disciplinas</label>
                                                <label
                                                    class="flex items-center gap-2 text-xs font-medium text-slate-600"
                                                >
                                                    <input
                                                        wire:model.live="leftAllDisciplines"
                                                        type="checkbox"
                                                        class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                                    />
                                                    Selecionar todas
                                                </label>
                                            </div>
                                            <select
                                                wire:model.live="leftDisciplineIds"
                                                multiple
                                                size="6"
                                                @disabled($leftAllDisciplines)
                                                class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 disabled:bg-slate-100"
                                            >
                                                @foreach ($disciplineOptions as $option)
                                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('leftDisciplineIds')
                                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <div class="mb-2 flex items-center justify-between gap-3">
                                                <label class="block text-sm font-medium text-slate-700">Filtro de
                                                    turmas</label>
                                                <label
                                                    class="flex items-center gap-2 text-xs font-medium text-slate-600"
                                                >
                                                    <input
                                                        wire:model.live="leftAllTurmas"
                                                        type="checkbox"
                                                        class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                                    />
                                                    Todas as turmas
                                                </label>
                                            </div>
                                            <select
                                                wire:model.live="leftTurmaIds"
                                                multiple
                                                size="6"
                                                @disabled($leftAllTurmas)
                                                class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 disabled:bg-slate-100"
                                            >
                                                @foreach ($turmaOptions as $option)
                                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                    <h5 class="text-sm font-semibold uppercase tracking-wide text-slate-900">Grupo
                                        direito opcional</h5>
                                    <p class="mt-1 text-xs text-slate-600">Se preenchido, as disciplinas do grupo
                                        esquerdo não poderão coincidir com estas disciplinas. Se vazio, a exclusão é
                                        interna ao grupo esquerdo.</p>
                                    <div class="mt-4">
                                        <div class="mb-2 flex items-center justify-between gap-3">
                                            <label class="block text-sm font-medium text-slate-700">Disciplinas</label>
                                            <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                                                <input
                                                    wire:model.live="rightAllDisciplines"
                                                    type="checkbox"
                                                    class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                                />
                                                Selecionar todas
                                            </label>
                                        </div>
                                        <select
                                            wire:model.live="rightDisciplineIds"
                                            multiple
                                            size="6"
                                            @disabled($rightAllDisciplines)
                                            class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 disabled:bg-slate-100"
                                        >
                                            @foreach ($disciplineOptions as $option)
                                                <option value="{{ $option['value'] }}">{{ $option['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @elseif ($type === 'TIME_PLACEMENT')
                            <div class="mt-4 grid gap-4 xl:grid-cols-2">
                                <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-4">
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <h5 class="text-sm font-semibold uppercase tracking-wide text-sky-900">
                                            Disciplinas do grupo alvo</h5>
                                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                                            <input
                                                wire:model.live="targetAllDisciplines"
                                                type="checkbox"
                                                class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                            />
                                            Selecionar todas
                                        </label>
                                    </div>
                                    <select
                                        wire:model.live="targetDisciplineIds"
                                        multiple
                                        size="6"
                                        @disabled($targetAllDisciplines)
                                        class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 disabled:bg-slate-100"
                                    >
                                        @foreach ($disciplineOptions as $option)
                                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('targetDisciplineIds')
                                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="rounded-2xl border border-violet-200 bg-violet-50 px-4 py-4">
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <h5 class="text-sm font-semibold uppercase tracking-wide text-violet-900">
                                            Filtro de turmas</h5>
                                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                                            <input
                                                wire:model.live="targetAllTurmas"
                                                type="checkbox"
                                                class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                            />
                                            Selecionar todas
                                        </label>
                                    </div>
                                    <select
                                        wire:model.live="targetTurmaIds"
                                        multiple
                                        size="6"
                                        @disabled($targetAllTurmas)
                                        class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 disabled:bg-slate-100"
                                    >
                                        @foreach ($turmaOptions as $option)
                                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-slate-700">Modo temporal</label>
                                    <select
                                        wire:model.live="timePlacementMode"
                                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200"
                                    >
                                        <option value="REQUIRED">Obrigar</option>
                                        <option value="PREFERRED">Preferir</option>
                                        <option value="FORBIDDEN">Proibir</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-slate-700">Dias</label>
                                    <select
                                        wire:model.live="allowedDays"
                                        multiple
                                        size="5"
                                        class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200"
                                    >
                                        @foreach ($dayOptions as $option)
                                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-slate-700">Tempos</label>
                                    <select
                                        wire:model.live="allowedPeriods"
                                        multiple
                                        size="5"
                                        class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200"
                                    >
                                        @foreach ($periodOptions as $option)
                                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                @error('allowedDays')
                                    <p class="text-sm text-rose-600 lg:col-span-3">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        @if ($editingId)
                            <button
                                type="button"
                                wire:click="cancelEdit"
                                class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >Cancelar</button>
                        @endif

                        <button
                            type="submit"
                            class="rounded-xl bg-sky-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-sky-700"
                        >
                            {{ $editingId ? 'Salvar alterações' : 'Criar constraint' }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h3 class="text-lg font-semibold text-slate-900">Preview textual</h3>
                        <p class="mt-1 text-sm text-slate-600">A descrição abaixo usa o humanizer da camada de domínio.
                        </p>
                    </div>

                    <div class="px-6 py-5">
                        @if (!$preview)
                            <p class="text-sm text-slate-500">Preencha o nome e os campos mínimos do payload para
                                visualizar o resumo da regra.</p>
                        @elseif (!empty($preview['error']))
                            <div
                                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                {{ $preview['error'] }}
                            </div>
                        @else
                            <div class="space-y-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-4">
                                <div
                                    class="flex flex-wrap gap-2 text-xs font-medium uppercase tracking-wide text-sky-900">
                                    <span
                                        class="rounded-full bg-white px-3 py-1">{{ $preview['summary']['type_label'] }}</span>
                                    <span
                                        class="rounded-full bg-white px-3 py-1">{{ $preview['summary']['level_label'] }}</span>
                                    <span
                                        class="rounded-full bg-white px-3 py-1">{{ $preview['summary']['status_label'] }}</span>
                                </div>
                                <p class="text-sm font-semibold text-slate-900">{{ $preview['summary']['name'] }}</p>
                                <p class="text-sm leading-6 text-slate-700">{{ $preview['description'] }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h3 class="text-lg font-semibold text-slate-900">Constraints cadastradas</h3>
                        <p class="mt-1 text-sm text-slate-600">Listagem com tipo, nível, status e descrição amigável
                            para revisão rápida.</p>
                    </div>

                    <div class="divide-y divide-slate-200">
                        @forelse ($constraintRows as $row)
                            <div
                                wire:key="constraint-row-{{ $row['id'] }}"
                                class="space-y-4 px-6 py-5"
                            >
                                <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                                    <div class="space-y-2">
                                        <div
                                            class="flex flex-wrap gap-2 text-xs font-medium uppercase tracking-wide text-slate-600">
                                            <span
                                                class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ $row['summary']['type_label'] }}</span>
                                            <span
                                                class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ $row['summary']['level_label'] }}</span>
                                            <span
                                                class="{{ $row['is_active'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }} rounded-full px-3 py-1"
                                            >{{ $row['summary']['status_label'] }}</span>
                                        </div>
                                        <div>
                                            <p class="text-base font-semibold text-slate-900">
                                                {{ $row['summary']['name'] }}</p>
                                            <p class="mt-1 text-sm leading-6 text-slate-600">
                                                {{ $row['summary']['description'] }}</p>
                                            @if ($row['user_description'])
                                                <p class="mt-2 text-xs text-slate-500">Observação:
                                                    {{ $row['user_description'] }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            wire:click="edit({{ $row['id'] }})"
                                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                        >Editar</button>
                                        <button
                                            type="button"
                                            wire:click="toggleStatus({{ $row['id'] }})"
                                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            {{ $row['is_active'] ? 'Desativar' : 'Ativar' }}
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="delete({{ $row['id'] }})"
                                            wire:confirm="Tem certeza que deseja excluir esta constraint?"
                                            class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50"
                                        >Excluir</button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-12 text-center">
                                <p class="text-base font-semibold text-slate-900">Nenhuma constraint cadastrada</p>
                                <p class="mt-2 text-sm text-slate-600">Use o formulário ao lado para criar a primeira
                                    regra customizada deste horário.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
