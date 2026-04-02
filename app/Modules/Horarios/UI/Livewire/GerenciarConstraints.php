<?php

declare(strict_types=1);

namespace App\Modules\Horarios\UI\Livewire;

use App\Models\Horario;
use App\Modules\Horarios\Application\CreateScheduleConstraintAction;
use App\Modules\Horarios\Application\DeleteScheduleConstraintAction;
use App\Modules\Horarios\Application\ListScheduleConstraintsAction;
use App\Modules\Horarios\Application\ToggleScheduleConstraintStatusAction;
use App\Modules\Horarios\Application\UpdateScheduleConstraintAction;
use App\Modules\Horarios\Domain\Constraints\DTO\CreateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\DTO\UpdateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\Entities\MutualExclusionConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\ScheduleConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\SyncSameTimeslotConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\TimePlacementConstraint;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncMatchMode;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncOccurrenceMode;
use App\Modules\Horarios\Domain\Constraints\Enums\TimePlacementMode;
use App\Modules\Horarios\Domain\Constraints\Exceptions\InvalidScheduleConstraintException;
use App\Modules\Horarios\Domain\Constraints\Exceptions\ScheduleConstraintNotFoundException;
use App\Modules\Horarios\Domain\Constraints\Factories\ScheduleConstraintFactory;
use App\Modules\Horarios\Domain\Constraints\ScheduleConstraintHumanizer;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

final class GerenciarConstraints extends Component
{
    public Horario $horario;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $type = 'SYNC_SAME_TIMESLOT';

    public string $level = 'SOFT';

    public int $weight = 1;

    public bool $isActive = true;

    public array $leftDisciplineIds = [];

    public bool $leftAllDisciplines = false;

    public array $rightDisciplineIds = [];

    public bool $rightAllDisciplines = false;

    public array $leftTurmaIds = [];

    public bool $leftAllTurmas = false;

    public string $occurrenceMode = 'ALL';

    public string $matchMode = 'ALL_TO_ALL';

    public array $targetDisciplineIds = [];

    public bool $targetAllDisciplines = false;

    public array $targetTurmaIds = [];

    public bool $targetAllTurmas = false;

    public string $timePlacementMode = 'REQUIRED';

    public array $allowedDays = [];

    public array $allowedPeriods = [];

    public function mount(Horario $horario): void
    {
        $this->horario = $horario->loadMissing('configuracaoHorario');
        $this->resetForm();
    }

    public function render()
    {
        return view('modules.horarios.livewire.gerenciar-constraints', [
            'constraintRows' => $this->constraintRows,
            'disciplineOptions' => $this->disciplineOptions,
            'turmaOptions' => $this->turmaOptions,
            'dayOptions' => $this->dayOptions,
            'periodOptions' => $this->periodOptions,
            'preview' => $this->preview,
        ]);
    }

    public function updatedType(): void
    {
        if ($this->editingId !== null) {
            return;
        }

        $this->resetTypeSpecificFields();
    }

    public function save(): void
    {
        $this->resetErrorBag();

        if ($this->disciplineOptions === [] && $this->turmaOptions === []) {
            session()->flash('error', 'Cadastre ao menos uma aula antes de criar constraints customizadas.');

            return;
        }

        $this->validate($this->rules(), $this->messages());

        if ($this->type === ConstraintType::TIME_PLACEMENT->value && $this->normalizedAllowedDays() === [] && $this->normalizedAllowedPeriods() === []) {
            $this->addError('allowedDays', 'Selecione ao menos um dia ou um tempo para a janela temporal.');

            return;
        }

        if (in_array($this->type, [ConstraintType::SYNC_SAME_TIMESLOT->value, ConstraintType::MUTUAL_EXCLUSION->value], true)
            && $this->selectedDisciplineIds($this->leftDisciplineIds, $this->leftAllDisciplines) === []) {
            $this->addError('leftDisciplineIds', 'Selecione ao menos uma disciplina no grupo esquerdo.');

            return;
        }

        if ($this->type === ConstraintType::TIME_PLACEMENT->value
            && $this->selectedDisciplineIds($this->targetDisciplineIds, $this->targetAllDisciplines) === []) {
            $this->addError('targetDisciplineIds', 'Selecione ao menos uma disciplina no grupo alvo.');

            return;
        }

        try {
            if ($this->editingId === null) {
                app(CreateScheduleConstraintAction::class)->execute($this->makeCreateInput());
                session()->flash('success', 'Constraint criada com sucesso.');
            } else {
                app(UpdateScheduleConstraintAction::class)->execute($this->makeUpdateInput());
                session()->flash('success', 'Constraint atualizada com sucesso.');
            }

            $this->resetForm();
        } catch (InvalidScheduleConstraintException|ScheduleConstraintNotFoundException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function edit(int $constraintId): void
    {
        $constraint = $this->findConstraint($constraintId);

        if ($constraint === null) {
            session()->flash('error', 'Constraint não encontrada no horário atual.');

            return;
        }

        $this->editingId = $constraint->id();
        $this->name = $constraint->name();
        $this->description = (string) ($constraint->description() ?? '');
        $this->type = $constraint->type()->value;
        $this->level = $constraint->level()->value;
        $this->weight = $constraint->weight();
        $this->isActive = $constraint->isActive();
        $this->resetTypeSpecificFields();

        try {
            match (true) {
                $constraint instanceof SyncSameTimeslotConstraint => $this->fillSyncForm($constraint),
                $constraint instanceof MutualExclusionConstraint => $this->fillMutualExclusionForm($constraint),
                $constraint instanceof TimePlacementConstraint => $this->fillTimePlacementForm($constraint),
                default => null,
            };
        } catch (InvalidArgumentException $exception) {
            $this->resetForm();
            session()->flash('error', $exception->getMessage());
        }
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function toggleStatus(int $constraintId): void
    {
        try {
            $updated = app(ToggleScheduleConstraintStatusAction::class)->execute($constraintId, $this->horario->id);

            if ($this->editingId === $updated->id()) {
                $this->isActive = $updated->isActive();
            }

            session()->flash('success', $updated->isActive() ? 'Constraint ativada.' : 'Constraint desativada.');
        } catch (ScheduleConstraintNotFoundException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function delete(int $constraintId): void
    {
        $deleted = app(DeleteScheduleConstraintAction::class)->execute($constraintId, $this->horario->id);

        if (! $deleted) {
            session()->flash('error', 'Não foi possível remover a constraint selecionada.');

            return;
        }

        if ($this->editingId === $constraintId) {
            $this->resetForm();
        }

        session()->flash('success', 'Constraint removida com sucesso.');
    }

    public function getConstraintRowsProperty(): array
    {
        $humanizer = $this->humanizer();

        return array_map(function (ScheduleConstraint $constraint) use ($humanizer): array {
            return [
                'id' => $constraint->id(),
                'type' => $constraint->type()->value,
                'level' => $constraint->level()->value,
                'is_active' => $constraint->isActive(),
                'summary' => $humanizer->summarize($constraint),
                'user_description' => $constraint->description(),
            ];
        }, app(ListScheduleConstraintsAction::class)->execute($this->horario->id));
    }

    public function getDisciplineOptionsProperty(): array
    {
        return $this->horario->aulas()
            ->with('disciplina:id,nome')
            ->get()
            ->filter(fn ($aula): bool => $aula->disciplina !== null)
            ->unique('disciplina_id')
            ->sortBy(fn ($aula): string => (string) $aula->disciplina?->nome)
            ->values()
            ->map(fn ($aula): array => [
                'value' => (int) $aula->disciplina_id,
                'label' => (string) $aula->disciplina?->nome,
            ])
            ->all();
    }

    public function getTurmaOptionsProperty(): array
    {
        return $this->horario->aulas()
            ->with('turma:id,nome')
            ->get()
            ->filter(fn ($aula): bool => $aula->turma !== null)
            ->unique('turma_id')
            ->sortBy(fn ($aula): string => (string) $aula->turma?->nome)
            ->values()
            ->map(fn ($aula): array => [
                'value' => (int) $aula->turma_id,
                'label' => (string) $aula->turma?->nome,
            ])
            ->all();
    }

    public function getDayOptionsProperty(): array
    {
        $configuracao = $this->horario->configuracaoHorario;

        if ($configuracao) {
            return collect($configuracao->getDiasLetivos())
                ->values()
                ->map(fn (string $label, int $index): array => [
                    'value' => $index + 1,
                    'label' => $label,
                ])
                ->all();
        }

        return [
            ['value' => 1, 'label' => 'Segunda'],
            ['value' => 2, 'label' => 'Terça'],
            ['value' => 3, 'label' => 'Quarta'],
            ['value' => 4, 'label' => 'Quinta'],
            ['value' => 5, 'label' => 'Sexta'],
        ];
    }

    public function getPeriodOptionsProperty(): array
    {
        $configuracao = $this->horario->configuracaoHorario;
        $totalTempos = $configuracao?->getTotalTempos() ?? 5;

        return collect(range(1, $totalTempos))
            ->map(function (int $tempo) use ($configuracao): array {
                $label = sprintf('%dº tempo', $tempo);

                if (! $configuracao) {
                    return ['value' => $tempo, 'label' => $label];
                }

                return [
                    'value' => $tempo,
                    'label' => sprintf(
                        '%s · %s às %s',
                        $label,
                        $configuracao->getHorarioTempo($tempo),
                        $configuracao->getTemposFim($tempo),
                    ),
                ];
            })
            ->all();
    }

    public function getPreviewProperty(): ?array
    {
        if (trim($this->name) === '') {
            return null;
        }

        try {
            $constraint = app(ScheduleConstraintFactory::class)->fromCreateInput($this->makeCreateInput());
            $humanizer = $this->humanizer();

            return [
                'summary' => $humanizer->summarize($constraint),
                'description' => $humanizer->describe($constraint),
            ];
        } catch (\Throwable $exception) {
            return [
                'summary' => null,
                'description' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function rules(): array
    {
        $typeValues = array_column(ConstraintType::cases(), 'value');
        $levelValues = array_column(ConstraintLevel::cases(), 'value');
        $syncOccurrenceValues = array_column(SyncOccurrenceMode::cases(), 'value');
        $syncMatchValues = array_column(SyncMatchMode::cases(), 'value');
        $timePlacementValues = array_column(TimePlacementMode::cases(), 'value');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in($typeValues)],
            'level' => ['required', Rule::in($levelValues)],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
            'isActive' => ['boolean'],
        ];

        return match ($this->type) {
            ConstraintType::SYNC_SAME_TIMESLOT->value => $rules + [
                'leftDisciplineIds' => ['array'],
                'rightDisciplineIds' => ['array'],
                'leftTurmaIds' => ['array'],
                'occurrenceMode' => ['required', Rule::in($syncOccurrenceValues)],
                'matchMode' => ['required', Rule::in($syncMatchValues)],
            ],
            ConstraintType::MUTUAL_EXCLUSION->value => $rules + [
                'leftDisciplineIds' => ['array'],
                'rightDisciplineIds' => ['array'],
                'leftTurmaIds' => ['array'],
            ],
            ConstraintType::TIME_PLACEMENT->value => $rules + [
                'targetDisciplineIds' => ['array'],
                'targetTurmaIds' => ['array'],
                'timePlacementMode' => ['required', Rule::in($timePlacementValues)],
                'allowedDays' => ['array'],
                'allowedDays.*' => ['integer'],
                'allowedPeriods' => ['array'],
                'allowedPeriods.*' => ['integer'],
            ],
            default => $rules,
        };
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Informe um nome para a constraint.',
            'weight.min' => 'O peso deve ser maior ou igual a 1.',
        ];
    }

    private function makeCreateInput(): CreateScheduleConstraintInput
    {
        return new CreateScheduleConstraintInput(
            horarioId: $this->horario->id,
            name: trim($this->name),
            description: $this->blankToNull($this->description),
            type: ConstraintType::from($this->type),
            level: ConstraintLevel::from($this->level),
            weight: $this->weight,
            isActive: $this->isActive,
            payload: $this->payloadFromForm(),
        );
    }

    private function makeUpdateInput(): UpdateScheduleConstraintInput
    {
        return new UpdateScheduleConstraintInput(
            id: $this->editingId,
            horarioId: $this->horario->id,
            name: trim($this->name),
            description: $this->blankToNull($this->description),
            level: ConstraintLevel::from($this->level),
            weight: $this->weight,
            isActive: $this->isActive,
            payload: $this->payloadFromForm(),
        );
    }

    private function payloadFromForm(): array
    {
        $rightLessonIds = $this->resolvedRightLessonIds();

        return match ($this->type) {
            ConstraintType::SYNC_SAME_TIMESLOT->value => [
                'left_group' => ['lesson_ids' => $this->resolvedLeftLessonIds()],
                'right_group' => $rightLessonIds === [] ? null : ['lesson_ids' => $rightLessonIds],
                'occurrence_mode' => $this->occurrenceMode,
                'match_mode' => $this->matchMode,
            ],
            ConstraintType::MUTUAL_EXCLUSION->value => [
                'left_group' => ['lesson_ids' => $this->resolvedLeftLessonIds()],
                'right_group' => $rightLessonIds === [] ? null : ['lesson_ids' => $rightLessonIds],
            ],
            ConstraintType::TIME_PLACEMENT->value => [
                'target_group' => ['lesson_ids' => $this->resolvedTargetLessonIds()],
                'mode' => $this->timePlacementMode,
                'allowed_days' => $this->normalizedAllowedDays(),
                'allowed_periods' => $this->normalizedAllowedPeriods(),
            ],
        };
    }

    private function fillSyncForm(SyncSameTimeslotConstraint $constraint): void
    {
        $this->hydrateLeftSelection($constraint->leftGroup()->lessonIds());

        if ($constraint->rightGroup() !== null) {
            $this->hydrateRightSelection($constraint->rightGroup()->lessonIds());
        }

        $this->occurrenceMode = $constraint->occurrenceMode()->value;
        $this->matchMode = $constraint->matchMode()->value;
    }

    private function fillMutualExclusionForm(MutualExclusionConstraint $constraint): void
    {
        $this->hydrateLeftSelection($constraint->leftGroup()->lessonIds());

        if ($constraint->rightGroup() !== null) {
            $this->hydrateRightSelection($constraint->rightGroup()->lessonIds());
        }
    }

    private function fillTimePlacementForm(TimePlacementConstraint $constraint): void
    {
        $this->hydrateTargetSelection($constraint->targetGroup()->lessonIds());
        $this->timePlacementMode = $constraint->mode()->value;
        $this->allowedDays = $constraint->window()->days();
        $this->allowedPeriods = $constraint->window()->periods();
    }

    private function findConstraint(int $constraintId): ?ScheduleConstraint
    {
        foreach (app(ListScheduleConstraintsAction::class)->execute($this->horario->id) as $constraint) {
            if ($constraint->id() === $constraintId) {
                return $constraint;
            }
        }

        return null;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->type = ConstraintType::SYNC_SAME_TIMESLOT->value;
        $this->level = ConstraintLevel::SOFT->value;
        $this->weight = 1;
        $this->isActive = true;
        $this->resetTypeSpecificFields();
    }

    private function resetTypeSpecificFields(): void
    {
        $this->leftDisciplineIds = [];
        $this->leftAllDisciplines = false;
        $this->rightDisciplineIds = [];
        $this->rightAllDisciplines = false;
        $this->leftTurmaIds = [];
        $this->leftAllTurmas = false;
        $this->occurrenceMode = SyncOccurrenceMode::ALL->value;
        $this->matchMode = SyncMatchMode::ALL_TO_ALL->value;
        $this->targetDisciplineIds = [];
        $this->targetAllDisciplines = false;
        $this->targetTurmaIds = [];
        $this->targetAllTurmas = false;
        $this->timePlacementMode = TimePlacementMode::REQUIRED->value;
        $this->allowedDays = [];
        $this->allowedPeriods = [];
    }

    private function humanizer(): ScheduleConstraintHumanizer
    {
        return new ScheduleConstraintHumanizer();
    }

    private function blankToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function resolvedLeftLessonIds(): array
    {
        return $this->lessonIdsForDisciplines(
            $this->selectedDisciplineIds($this->leftDisciplineIds, $this->leftAllDisciplines),
            $this->selectedTurmaIds($this->leftTurmaIds, $this->leftAllTurmas),
        );
    }

    private function resolvedRightLessonIds(): array
    {
        $disciplineIds = $this->selectedDisciplineIds($this->rightDisciplineIds, $this->rightAllDisciplines);

        if ($disciplineIds === []) {
            return [];
        }

        return $this->lessonIdsForDisciplines($disciplineIds);
    }

    private function resolvedTargetLessonIds(): array
    {
        return $this->lessonIdsForDisciplines(
            $this->selectedDisciplineIds($this->targetDisciplineIds, $this->targetAllDisciplines),
            $this->selectedTurmaIds($this->targetTurmaIds, $this->targetAllTurmas),
        );
    }

    private function selectedDisciplineIds(array $selectedIds, bool $selectAll): array
    {
        return $selectAll
            ? $this->normalizeIntList(array_column($this->disciplineOptions, 'value'))
            : $this->normalizeIntList($selectedIds);
    }

    private function selectedTurmaIds(array $selectedIds, bool $selectAll): array
    {
        return $selectAll
            ? $this->normalizeIntList(array_column($this->turmaOptions, 'value'))
            : $this->normalizeIntList($selectedIds);
    }

    private function hydrateLeftSelection(array $lessonIds): void
    {
        $selection = $this->inferDisciplineAndOptionalTurmasFromLessonIds($lessonIds);
        $this->leftDisciplineIds = $selection['discipline_ids'];
        $this->leftAllDisciplines = $selection['all_disciplines'];
        $this->leftTurmaIds = $selection['turma_ids'];
        $this->leftAllTurmas = $selection['all_turmas'];
    }

    private function hydrateRightSelection(array $lessonIds): void
    {
        $selection = $this->inferDisciplineOnlyFromLessonIds($lessonIds);
        $this->rightDisciplineIds = $selection['discipline_ids'];
        $this->rightAllDisciplines = $selection['all_disciplines'];
    }

    private function hydrateTargetSelection(array $lessonIds): void
    {
        $selection = $this->inferDisciplineAndOptionalTurmasFromLessonIds($lessonIds);
        $this->targetDisciplineIds = $selection['discipline_ids'];
        $this->targetAllDisciplines = $selection['all_disciplines'];
        $this->targetTurmaIds = $selection['turma_ids'];
        $this->targetAllTurmas = $selection['all_turmas'];
    }

    private function inferDisciplineAndOptionalTurmasFromLessonIds(array $lessonIds): array
    {
        $lessonIds = $this->normalizeIntList($lessonIds);

        if ($lessonIds === []) {
            throw new InvalidArgumentException('A constraint salva nao possui aulas suficientes para reidratacao.');
        }

        $aulas = $this->horario->aulas()
            ->whereIn('id', $lessonIds)
            ->get(['id', 'disciplina_id', 'turma_id']);

        if ($aulas->count() !== count($lessonIds) || $aulas->contains(fn ($aula): bool => $aula->disciplina_id === null)) {
            throw new InvalidArgumentException('Nao foi possivel inferir disciplinas da constraint salva.');
        }

        $disciplineIds = $aulas->pluck('disciplina_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $candidateTurmaIds = $aulas->pluck('turma_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $allDisciplineIds = array_column($this->disciplineOptions, 'value');
        $allTurmaIds = array_column($this->turmaOptions, 'value');

        foreach ([[], $candidateTurmaIds] as $turmaIds) {
            if (! $this->sameIntSet($this->lessonIdsForDisciplines($disciplineIds, $turmaIds), $lessonIds)) {
                continue;
            }

            return [
                'discipline_ids' => $this->sameIntSet($disciplineIds, $allDisciplineIds) ? [] : $disciplineIds,
                'all_disciplines' => $this->sameIntSet($disciplineIds, $allDisciplineIds),
                'turma_ids' => $turmaIds !== [] && $this->sameIntSet($turmaIds, $allTurmaIds) ? [] : $turmaIds,
                'all_turmas' => $turmaIds !== [] && $this->sameIntSet($turmaIds, $allTurmaIds),
            ];
        }

        throw new InvalidArgumentException('Nao foi possivel reidratar a selecao de disciplinas e turmas da constraint salva.');
    }

    private function inferDisciplineOnlyFromLessonIds(array $lessonIds): array
    {
        $lessonIds = $this->normalizeIntList($lessonIds);

        if ($lessonIds === []) {
            throw new InvalidArgumentException('A constraint salva nao possui grupo direito valido.');
        }

        $aulas = $this->horario->aulas()
            ->whereIn('id', $lessonIds)
            ->get(['id', 'disciplina_id']);

        if ($aulas->count() !== count($lessonIds) || $aulas->contains(fn ($aula): bool => $aula->disciplina_id === null)) {
            throw new InvalidArgumentException('Nao foi possivel inferir o grupo direito da constraint salva.');
        }

        $disciplineIds = $aulas->pluck('disciplina_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $allDisciplineIds = array_column($this->disciplineOptions, 'value');

        if (! $this->sameIntSet($this->lessonIdsForDisciplines($disciplineIds), $lessonIds)) {
            throw new InvalidArgumentException('Nao foi possivel mapear o grupo direito apenas por disciplinas.');
        }

        return [
            'discipline_ids' => $this->sameIntSet($disciplineIds, $allDisciplineIds) ? [] : $disciplineIds,
            'all_disciplines' => $this->sameIntSet($disciplineIds, $allDisciplineIds),
        ];
    }

    private function lessonIdsForDisciplines(array $disciplineIds, array $turmaIds = []): array
    {
        $query = $this->horario->aulas()
            ->whereIn('disciplina_id', $this->normalizeIntList($disciplineIds));

        if ($turmaIds !== []) {
            $query->whereIn('turma_id', $this->normalizeIntList($turmaIds));
        }

        return $query
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function normalizedAllowedDays(): array
    {
        return $this->normalizeIntList($this->allowedDays);
    }

    private function normalizedAllowedPeriods(): array
    {
        return $this->normalizeIntList($this->allowedPeriods);
    }

    private function normalizeIntList(array $values): array
    {
        return array_values(array_unique(array_map(static fn ($value): int => (int) $value, $values)));
    }

    private function sameIntSet(array $left, array $right): bool
    {
        $left = $this->normalizeIntList($left);
        $right = $this->normalizeIntList($right);
        sort($left);
        sort($right);

        return $left === $right;
    }
}
