<?php
// app/Livewire/Horarios/Show.php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use App\Models\Turma;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Visualizar Horário'])]
class Show extends Component
{
    public Horario $horario;
    public ?int $turmaId = null;
    public string $view = 'turmas'; // 'turmas' ou 'professores'

    protected $queryString = [
        'turmaId' => ['except' => null],
        'view' => ['except' => 'turmas'],
    ];

    public function mount(Horario $horario): void
    {
        $this->horario = $horario;

        // Selecionar primeira turma se nenhuma foi selecionada
        if (!$this->turmaId && $turmas = Turma::ativa()->first()) {
            $this->turmaId = $turmas->id;
        }
    }

    public function getDiasSemanaProperty(): array
    {
        return [
            'segunda' => 'Segunda-feira',
            'terca' => 'Terça-feira',
            'quarta' => 'Quarta-feira',
            'quinta' => 'Quinta-feira',
            'sexta' => 'Sexta-feira',
        ];
    }

    public function getHorariosProperty(): array
    {
        return [
            '07:00' => '07:00 - 07:50',
            '08:00' => '08:00 - 08:50',
            '09:00' => '09:00 - 09:50',
            '10:00' => '10:00 - 10:50',
            '11:00' => '11:00 - 11:50',
            '13:00' => '13:00 - 13:50',
            '14:00' => '14:00 - 14:50',
            '15:00' => '15:00 - 15:50',
            '16:00' => '16:00 - 16:50',
            '17:00' => '17:00 - 17:50',
            '19:00' => '19:00 - 19:50',
            '20:00' => '20:00 - 20:50',
            '21:00' => '21:00 - 21:50',
            '22:00' => '22:00 - 22:50',
        ];
    }

    public function getGradeProperty(): array
    {
        if (!$this->turmaId) {
            return [];
        }

        $grade = [];

        $alocacoes = $this->horario->alocacoes()->where('turma_id', $this->turmaId)->with(['disciplina', 'professor'])->get();

        foreach ($alocacoes as $alocacao) {
            $horarioInicio = $alocacao->horario_inicio->format('H:i');
            $grade[$alocacao->dia_semana][$horarioInicio] = $alocacao;
        }

        return $grade;
    }

    public function getTurmasProperty()
    {
        return Turma::ativa()->orderBy('nome')->get();
    }

    public function generateSchedule(): void
    {
        // Redirecionar para página de geração
        $this->redirect(route('algoritmo.index', ['horario_id' => $this->horario->id]));
    }

    public function exportPdf(): void
    {
        session()->flash('info', 'Funcionalidade de exportação será implementada em breve.');
    }

    public function render()
    {
        return view('livewire.horarios.show');
    }
}
