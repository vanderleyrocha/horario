<?php

namespace App\Modules\Horarios\UI\Livewire\Turmas;

use App\Models\Aula;
use App\Models\Turma;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Aulas'])]
class Aulas extends Component
{
    public Turma $turma;

    public int $totalDisciplinas = 0;

    public int $totalProfessores = 0;

    public int $totalCargaHoraria = 0;

    public function mount(Turma $turma): void
    {
        $this->turma = $turma;
    }

    public function delete(int $id): void
    {
        $aula = Aula::query()->findOrFail($id);

        if ($aula->alocacoes()->count() > 0) {
            session()->flash('error', 'Nao e possivel excluir esta aula pois ela possui alocacoes de horarios.');

            return;
        }

        $aula->delete();

        session()->flash('success', 'Aula excluida com sucesso!');
    }

    public function render()
    {
        $aulasDaTurma = $this->turma->aulas;
        $disciplinasUnicas = $aulasDaTurma->pluck('disciplina_id')->unique();
        $professoresUnicos = $aulasDaTurma->pluck('professor_id')->unique();

        $this->totalDisciplinas = $disciplinasUnicas->count();
        $this->totalProfessores = $professoresUnicos->count();
        $this->totalCargaHoraria = 0;

        foreach ($aulasDaTurma as $aula) {
            $tipoFactor = match ($aula->tipo) {
                'simples' => 1,
                'dupla' => 2,
                'tripla' => 3,
                default => 1,
            };

            $this->totalCargaHoraria += $aula->aulas_semana * $tipoFactor;
        }

        return view('modules.horarios.livewire.turmas.aulas', [
            'totalDisciplinas' => $this->totalDisciplinas,
            'totalProfessores' => $this->totalProfessores,
            'totalCargaHoraria' => $this->totalCargaHoraria,
        ]);
    }
}
