<?php

namespace App\Modules\Horarios\UI\Livewire;

use App\Models\Alocacao;
use App\Models\Horario;
use App\Models\Professor;
use App\Models\Turma;
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

    public function mount(Horario $horario)
    {
        $this->horario = $horario;

        if (! $this->entidadeId) {
            $this->entidadeId = $this->view === 'professores' ? Professor::ativo()->value('id') : Turma::ativa()->value('id');
        }
    }

    public function getAllocationsProperty(): Collection
    {
        $executionId = $this->horario->lastExecution?->id;

        if (! $executionId) {
            return collect();
        }

        return Alocacao::query()
            ->where('horario_id', $this->horario->id)
            ->where('execution_id', $executionId)
            ->with(['disciplina:id,codigo', 'professor:id,nome_abreviado', 'turma:id,nome'])
            ->get();
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

    public function updatedView()
    {
        $this->entidadeId = $this->view === 'professores' ? Professor::ativo()->value('id') : Turma::ativa()->value('id');
    }

    public function getGradeProperty(): array
    {
        if (! $this->entidadeId) {
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

            // O tempo 1 equivale ao índice 0 do array de timeSlots
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
        // Recupera a configuração do banco ligada a este horário
        $config = $this->horario->configuracaoHorario;

        if (! $config) {
            return []; // Retorna vazio caso não haja configuração para evitar erro
        }

        $slots = [];
        $totalTempos = $config->getTotalTempos(); // Equivalente ao "aulas_por_dia"

        // Constrói os horários utilizando os helpers do model ConfiguracaoHorario
        for ($tempo = 1; $tempo <= $totalTempos; $tempo++) {
            $inicio = $config->getHorarioTempo($tempo);
            $fim = $config->getTemposFim($tempo);

            // O formato final fica como antes: '07:00' => '07:00 - 07:50'
            $slots[$inicio] = "{$inicio} - {$fim}";
        }

        return $slots;
    }

    public function handleDrop(int $allocationId, string $day, string $time): void
    {
        $allocation = Alocacao::find($allocationId);

        if (! $allocation) {
            return;
        }

        $slots = array_keys($this->timeSlots);
        $index = array_search($time, $slots);

        if ($index === false) {
            return;
        }

        $newTempo = $index + 1;

        $allocation->update([
            'dia_semana' => $day,
            'tempo' => $newTempo,
        ]);

        unset($this->grade);
        unset($this->allocations);
    }

    public function render()
    {
        return view('livewire.horarios.show');
    }
}
