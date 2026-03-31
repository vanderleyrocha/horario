<?php

namespace App\Modules\Horarios\UI\Livewire;

use App\Models\Alocacao;
use App\Models\Aula;
use App\Models\Horario;
use App\Models\Professor;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Visualização de Horários'])]
class Show extends Component
{
    public Horario $horario;

    public ?int $entidadeId = null;

    public string $view = 'turmas';

    public bool $manualMode = true;

    protected $queryString = [
        'entidadeId' => ['except' => null],
        'view' => ['except' => 'turmas'],
    ];

    private const PROFESSOR_PALETTE = [
        ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'border' => 'border-blue-300'],
        ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'border' => 'border-green-300'],
        ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'border' => 'border-purple-300'],
        ['bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'border' => 'border-orange-300'],
        ['bg' => 'bg-pink-100', 'text' => 'text-pink-800', 'border' => 'border-pink-300'],
        ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-800', 'border' => 'border-cyan-300'],
        ['bg' => 'bg-amber-100', 'text' => 'text-amber-800', 'border' => 'border-amber-300'],
        ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-800', 'border' => 'border-indigo-300'],
    ];

    private const DISCIPLINA_PALETTE = [
        ['bg' => 'bg-rose-100', 'text' => 'text-rose-900', 'border' => 'border-rose-300'],
        ['bg' => 'bg-sky-100', 'text' => 'text-sky-900', 'border' => 'border-sky-300'],
        ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-900', 'border' => 'border-emerald-300'],
        ['bg' => 'bg-amber-100', 'text' => 'text-amber-900', 'border' => 'border-amber-300'],
        ['bg' => 'bg-violet-100', 'text' => 'text-violet-900', 'border' => 'border-violet-300'],
        ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-900', 'border' => 'border-cyan-300'],
        ['bg' => 'bg-lime-100', 'text' => 'text-lime-900', 'border' => 'border-lime-300'],
        ['bg' => 'bg-fuchsia-100', 'text' => 'text-fuchsia-900', 'border' => 'border-fuchsia-300'],
    ];

    public function mount(Horario $horario)
    {
        $this->horario = $horario;

        if (! $this->entidadeId || ! $this->entidades->pluck('id')->contains((int) $this->entidadeId)) {
            $this->entidadeId = $this->resolveDefaultEntidadeId();
        }
    }

    public function getExecutionIdProperty(): ?int
    {
        return $this->horario->lastExecution?->id;
    }

    public function getAllocationsProperty(): Collection
    {
        $executionId = $this->executionId;

        if (! $executionId) {
            return collect();
        }

        return Alocacao::query()
            ->where('horario_id', $this->horario->id)
            ->where('execution_id', $executionId)
            ->with([
                'disciplina:id,codigo',
                'professor:id,nome,nome_abreviado',
                'turma:id,nome,codigo',
            ])
            ->orderBy('dia_semana')
            ->orderBy('tempo')
            ->get();
    }

    public function getRequiresEntitySelectionProperty(): bool
    {
        return in_array($this->view, ['turmas', 'professores'], true);
    }

    public function getProfessorColorsProperty(): array
    {
        $ids = $this->allocations
            ->pluck('professor_id')
            ->unique()
            ->values();

        $colors = [];

        foreach ($ids as $index => $id) {
            $colors[$id] = self::PROFESSOR_PALETTE[$index % count(self::PROFESSOR_PALETTE)];
        }

        return $colors;
    }

    public function colorForProfessor(?int $professorId): array
    {
        return $this->professorColors[$professorId]
            ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'border' => 'border-gray-300'];
    }

    public function getDisciplinaColorsProperty(): array
    {
        $ids = $this->allocations
            ->pluck('disciplina_id')
            ->unique()
            ->values();

        $colors = [];

        foreach ($ids as $index => $id) {
            $colors[$id] = self::DISCIPLINA_PALETTE[$index % count(self::DISCIPLINA_PALETTE)];
        }

        return $colors;
    }

    public function colorForDisciplina(?int $disciplinaId): array
    {
        return $this->disciplinaColors[$disciplinaId]
            ?? ['bg' => 'bg-slate-100', 'text' => 'text-slate-900', 'border' => 'border-slate-300'];
    }

    public function updatedView()
    {
        $this->refreshDerivedState();

        $this->entidadeId = $this->requiresEntitySelection
            ? $this->resolveDefaultEntidadeId()
            : null;
    }

    public function updatedEntidadeId($value): void
    {
        if (! $this->requiresEntitySelection) {
            $this->entidadeId = null;

            return;
        }

        if (! $this->entidades->pluck('id')->contains((int) $value)) {
            $this->entidadeId = $this->resolveDefaultEntidadeId();
        }
    }

    public function getEntidadesProperty(): Collection
    {
        if (! $this->requiresEntitySelection) {
            return collect();
        }

        if ($this->view === 'professores') {
            $ids = Aula::query()
                ->ativas()
                ->where('horario_id', $this->horario->id)
                ->pluck('professor_id')
                ->filter()
                ->unique()
                ->values();

            if ($ids->isNotEmpty()) {
                return Professor::ativo()
                    ->whereIn('id', $ids)
                    ->orderBy('nome')
                    ->get();
            }

            $fromAllocations = $this->allocations
                ->pluck('professor_id')
                ->filter()
                ->unique()
                ->values();

            if ($fromAllocations->isNotEmpty()) {
                return Professor::ativo()
                    ->whereIn('id', $fromAllocations)
                    ->orderBy('nome')
                    ->get();
            }

            return Professor::ativo()->orderBy('nome')->get();
        }

        $ids = Aula::query()
            ->ativas()
            ->where('horario_id', $this->horario->id)
            ->pluck('turma_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isNotEmpty()) {
            return Turma::ativa()
                ->whereIn('id', $ids)
                ->orderBy('nome')
                ->get();
        }

        $fromAllocations = $this->allocations
            ->pluck('turma_id')
            ->filter()
            ->unique()
            ->values();

        if ($fromAllocations->isNotEmpty()) {
            return Turma::ativa()
                ->whereIn('id', $fromAllocations)
                ->orderBy('nome')
                ->get();
        }

        return Turma::ativa()->orderBy('nome')->get();
    }

    private function resolveDefaultEntidadeId(): ?int
    {
        if (! $this->requiresEntitySelection) {
            return null;
        }

        if ($this->view === 'professores') {
            $fromAulas = Aula::query()
                ->ativas()
                ->where('horario_id', $this->horario->id)
                ->whereNotNull('professor_id')
                ->orderBy('id')
                ->value('professor_id');

            if ($fromAulas) {
                return (int) $fromAulas;
            }

            $fromAllocations = $this->allocations
                ->pluck('professor_id')
                ->filter()
                ->unique()
                ->first();

            return $fromAllocations
                ? (int) $fromAllocations
                : Professor::ativo()->value('id');
        }

        $fromAulas = Aula::query()
            ->ativas()
            ->where('horario_id', $this->horario->id)
            ->whereNotNull('turma_id')
            ->orderBy('id')
            ->value('turma_id');

        if ($fromAulas) {
            return (int) $fromAulas;
        }

        $fromAllocations = $this->allocations
            ->pluck('turma_id')
            ->filter()
            ->unique()
            ->first();

        return $fromAllocations
            ? (int) $fromAllocations
            : Turma::ativa()->value('id');
    }

    public function getUnallocatedAulasProperty(): Collection
    {
        $executionId = $this->executionId;

        $query = Aula::query()
            ->ativas()
            ->where('horario_id', $this->horario->id)
            ->with([
                'disciplina:id,nome,codigo',
                'professor:id,nome,nome_abreviado',
                'turma:id,nome,codigo',
            ])
            ->withCount([
                'alocacoes as alocadas_count' => function ($q) use ($executionId) {
                    if ($executionId) {
                        $q->where('execution_id', $executionId);
                    } else {
                        $q->whereRaw('1=0');
                    }
                },
            ]);

        if ($this->view === 'professores' && $this->entidadeId) {
            $query->where('professor_id', $this->entidadeId);
        }

        if ($this->view === 'turmas' && $this->entidadeId) {
            $query->where('turma_id', $this->entidadeId);
        }

        return $query
            ->get()
            ->map(function (Aula $aula) {
                $faltantes = max(0, (int) $aula->aulas_semana - (int) $aula->alocadas_count);

                return [
                    'aula_id' => $aula->id,
                    'disciplina_codigo' => $aula->disciplina?->codigo ?? '---',
                    'disciplina_nome' => $aula->disciplina?->nome ?? 'Disciplina',
                    'professor' => $aula->professor?->nome_abreviado ?? $aula->professor?->nome ?? 'Professor',
                    'turma' => $aula->turma?->nome ?? 'Turma',
                    'turma_abreviada' => $aula->turma?->codigo ?? $aula->turma?->nome ?? 'Turma',
                    'aulas_semana' => (int) $aula->aulas_semana,
                    'alocadas' => (int) $aula->alocadas_count,
                    'faltantes' => $faltantes,
                ];
            })
            ->filter(fn (array $item) => $item['faltantes'] > 0)
            ->values();
    }

    public function getGradeProperty(): array
    {
        if (! $this->requiresEntitySelection || ! $this->entidadeId) {
            return [];
        }

        $grid = [];
        $slots = array_keys($this->timeSlots);
        $isProfessorView = ($this->view === 'professores');

        foreach ($this->allocations as $a) {
            $match = $isProfessorView ? ($a->professor_id == $this->entidadeId) : ($a->turma_id == $this->entidadeId);

            if (! $match) {
                continue;
            }

            $index = $a->tempo - 1;

            if (! isset($slots[$index])) {
                continue;
            }

            $slot = $slots[$index];
            $day = $a->dia_semana;
            $duration = max(1, $a->duracao_tempos);

            $grid[$day][$slot] = [
                'type' => 'start',
                'span' => $duration,
                'allocation' => $a,
            ];

            for ($i = 1; $i < $duration; $i++) {
                $nextIndex = $index + $i;

                if (! isset($slots[$nextIndex])) {
                    break;
                }

                $nextSlot = $slots[$nextIndex];

                $grid[$day][$nextSlot] = [
                    'type' => 'continuation',
                ];
            }
        }

        return $grid;
    }

    public function getMatrixProfessoresProperty(): Collection
    {
        $ids = Aula::query()
            ->ativas()
            ->where('horario_id', $this->horario->id)
            ->pluck('professor_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            $ids = $this->allocations
                ->pluck('professor_id')
                ->filter()
                ->unique()
                ->values();
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        return Professor::query()
            ->whereIn('id', $ids)
            ->orderBy('nome')
            ->get(['id', 'nome', 'nome_abreviado']);
    }

    public function getMatrixTurmasProperty(): Collection
    {
        $ids = Aula::query()
            ->ativas()
            ->where('horario_id', $this->horario->id)
            ->pluck('turma_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            $ids = $this->allocations
                ->pluck('turma_id')
                ->filter()
                ->unique()
                ->values();
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        return Turma::query()
            ->whereIn('id', $ids)
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo']);
    }

    public function getTemposNumericosProperty(): array
    {
        return range(1, count($this->timeSlots));
    }

    public function getProfessorTempoMatrixProperty(): array
    {
        $matrix = [];

        foreach ($this->allocations as $allocation) {
            $professorId = (int) $allocation->professor_id;
            $day = $allocation->dia_semana;
            $tempoInicial = (int) $allocation->tempo;
            $duracao = max(1, (int) $allocation->duracao_tempos);

            for ($offset = 0; $offset < $duracao; $offset++) {
                $tempo = $tempoInicial + $offset;

                $matrix[$professorId][$day][$tempo] = [
                    'allocation_id' => (int) $allocation->id,
                    'disciplina_id' => (int) $allocation->disciplina_id,
                    'disciplina_codigo' => $allocation->disciplina?->codigo ?? '---',
                    'turma_id' => (int) $allocation->turma_id,
                    'turma_codigo' => $allocation->turma?->codigo ?? $allocation->turma?->nome ?? 'Turma',
                    'tempo' => $tempo,
                    'horario' => $this->formatTempoRange($tempo, 1),
                    'continuacao' => $offset > 0,
                ];
            }
        }

        return $matrix;
    }

    public function getDiasSemanaProperty(): array
    {
        return [
            'segunda' => 'Segunda',
            'terca' => 'Terça',
            'quarta' => 'Quarta',
            'quinta' => 'Quinta',
            'sexta' => 'Sexta',
        ];
    }

    public function getTimeSlotsProperty(): array
    {
        $config = $this->horario->configuracaoHorario;

        if (! $config) {
            return [];
        }

        $slots = [];
        $totalTempos = $config->getTotalTempos();

        for ($tempo = 1; $tempo <= $totalTempos; $tempo++) {
            $inicio = $config->getHorarioTempo($tempo);
            $fim = $config->getTemposFim($tempo);
            $slots[$inicio] = "{$inicio} - {$fim}";
        }

        return $slots;
    }

    public function handleDrop(int $allocationId, string $day, string $time): void
    {
        $allocation = $this->allocationScopeQuery()
            ->whereKey($allocationId)
            ->first();

        if (! $allocation) {
            return;
        }

        $newTempo = $this->resolveTempoFromTime($time);

        if ($newTempo === null) {
            return;
        }

        $duration = max(1, (int) $allocation->duracao_tempos);
        $conflictMessage = $this->validateAllocationPlacement(
            day: $day,
            tempo: $newTempo,
            duration: $duration,
            turmaId: (int) $allocation->turma_id,
            professorId: (int) $allocation->professor_id,
            ignoreAllocationId: (int) $allocation->id,
        );

        if ($conflictMessage !== null) {
            session()->flash('error', $conflictMessage);

            return;
        }

        $horarios = $this->resolveHorarioRange($newTempo, $duration);

        if ($horarios === null) {
            session()->flash('error', 'Nao foi possivel determinar o horario para o bloco informado.');

            return;
        }

        $allocation->update([
            'dia_semana' => $day,
            'tempo' => $newTempo,
            'horario_inicio' => $horarios['inicio'],
            'horario_fim' => $horarios['fim'],
            'eh_manual' => true,
        ]);

        session()->flash('success', 'Aula reposicionada com sucesso.');

        $this->refreshDerivedState();
    }

    public function allocateUnallocatedAula(int $aulaId, string $day, string $time): void
    {
        $aula = Aula::query()
            ->ativas()
            ->where('horario_id', $this->horario->id)
            ->with([
                'disciplina:id,nome,codigo',
                'professor:id,nome,nome_abreviado',
                'turma:id,nome,codigo',
            ])
            ->find($aulaId);

        if (! $aula) {
            session()->flash('error', 'A aula selecionada nao foi encontrada.');

            return;
        }

        if (! $aula->turma_id || ! $aula->professor_id || ! $aula->disciplina_id) {
            session()->flash('error', 'A aula precisa ter turma, professor e disciplina definidos para ser alocada manualmente.');

            return;
        }

        $tempo = $this->resolveTempoFromTime($time);

        if ($tempo === null) {
            session()->flash('error', 'O tempo selecionado nao e valido para este horario.');

            return;
        }

        $alocadas = $this->allocationScopeQuery()
            ->where('aula_id', $aula->id)
            ->count();

        if ($alocadas >= (int) $aula->aulas_semana) {
            session()->flash('error', 'Todas as ocorrencias dessa aula ja foram alocadas.');

            return;
        }

        $duration = max(1, $aula->getDuracaoTempos());

        $conflictMessage = $this->validateAllocationPlacement(
            day: $day,
            tempo: $tempo,
            duration: $duration,
            turmaId: (int) $aula->turma_id,
            professorId: (int) $aula->professor_id,
        );

        if ($conflictMessage !== null) {
            session()->flash('error', $conflictMessage);

            return;
        }

        $horarios = $this->resolveHorarioRange($tempo, $duration);

        if ($horarios === null) {
            session()->flash('error', 'Nao foi possivel determinar o horario para o bloco informado.');

            return;
        }

        Alocacao::query()->create([
            'horario_id' => $this->horario->id,
            'execution_id' => $this->executionId,
            'aula_id' => $aula->id,
            'turma_id' => $aula->turma_id,
            'disciplina_id' => $aula->disciplina_id,
            'professor_id' => $aula->professor_id,
            'dia_semana' => $day,
            'tempo' => $tempo,
            'duracao_tempos' => $duration,
            'eh_manual' => true,
            'bloqueada' => false,
            'horario_inicio' => $horarios['inicio'],
            'horario_fim' => $horarios['fim'],
        ]);

        session()->flash('success', 'Aula alocada manualmente com sucesso.');

        $this->refreshDerivedState();
    }

    public function moveToUnallocated(int $allocationId): void
    {
        $allocation = $this->allocationScopeQuery()
            ->whereKey($allocationId)
            ->first();

        if (! $allocation) {
            return;
        }

        $allocation->delete();

        session()->flash('success', 'Aula movida para nao alocadas.');

        $this->refreshDerivedState();

        if (! $this->entidadeId || ! $this->entidades->pluck('id')->contains((int) $this->entidadeId)) {
            $this->entidadeId = $this->resolveDefaultEntidadeId();
        }
    }

    private function allocationScopeQuery(): Builder
    {
        $query = Alocacao::query()
            ->where('horario_id', $this->horario->id);

        if ($this->executionId) {
            $query->where('execution_id', $this->executionId);
        }

        return $query;
    }

    private function resolveTempoFromTime(string $time): ?int
    {
        $slots = array_keys($this->timeSlots);
        $index = array_search($time, $slots, true);

        if ($index === false) {
            return null;
        }

        return $index + 1;
    }

    private function resolveHorarioRange(int $tempo, int $duration): ?array
    {
        $config = $this->horario->configuracaoHorario;

        if (! $config) {
            return null;
        }

        $endTempo = $tempo + $duration - 1;

        return [
            'inicio' => $config->getHorarioTempo($tempo),
            'fim' => $config->getTemposFim($endTempo),
        ];
    }

    private function validateAllocationPlacement(
        string $day,
        int $tempo,
        int $duration,
        int $turmaId,
        int $professorId,
        ?int $ignoreAllocationId = null,
    ): ?string {
        $maxTempo = count($this->timeSlots);

        if ($tempo < 1 || $maxTempo === 0) {
            return 'Nao existe configuracao de tempos disponivel para este horario.';
        }

        $endTempo = $tempo + $duration - 1;

        if ($endTempo > $maxTempo) {
            return 'A aula excede o ultimo tempo disponivel do dia.';
        }

        $conflict = $this->allocationScopeQuery()
            ->where('dia_semana', $day)
            ->with([
                'professor:id,nome,nome_abreviado',
                'turma:id,nome,codigo',
            ])
            ->get()
            ->first(function (Alocacao $allocation) use ($endTempo, $ignoreAllocationId, $professorId, $tempo, $turmaId) {
                if ($ignoreAllocationId !== null && (int) $allocation->id === $ignoreAllocationId) {
                    return false;
                }

                if ((int) $allocation->turma_id !== $turmaId && (int) $allocation->professor_id !== $professorId) {
                    return false;
                }

                $allocationStart = (int) $allocation->tempo;
                $allocationEnd = $allocationStart + max(1, (int) $allocation->duracao_tempos) - 1;

                return $tempo <= $allocationEnd && $endTempo >= $allocationStart;
            });

        if (! $conflict) {
            return null;
        }

        $conflictTargets = [];

        if ((int) $conflict->turma_id === $turmaId) {
            $conflictTargets[] = 'a turma ' . ($conflict->turma?->codigo ?? $conflict->turma?->nome ?? 'informada');
        }

        if ((int) $conflict->professor_id === $professorId) {
            $conflictTargets[] = 'o professor ' . ($conflict->professor?->nome_abreviado ?? $conflict->professor?->nome ?? 'informado');
        }

        return sprintf(
            'Conflito de horario: %s ja possui aula em %s, %s.',
            implode(' e ', $conflictTargets),
            $this->diasSemana[$day] ?? ucfirst($day),
            $this->formatTempoRange($tempo, $duration),
        );
    }

    private function formatTempoRange(int $tempo, int $duration): string
    {
        $horarios = $this->resolveHorarioRange($tempo, $duration);

        if ($horarios === null) {
            return 'tempo ' . $tempo;
        }

        return $horarios['inicio'] . ' - ' . $horarios['fim'];
    }

    private function refreshDerivedState(): void
    {
        unset($this->allocations);
        unset($this->entidades);
        unset($this->grade);
        unset($this->unallocatedAulas);
        unset($this->matrixProfessores);
        unset($this->matrixTurmas);
        unset($this->professorTempoMatrix);
        unset($this->temposNumericos);
    }

    public function render()
    {
        return view('modules.horarios.livewire.show');
    }
}
