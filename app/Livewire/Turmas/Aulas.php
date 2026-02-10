<?php

namespace App\Livewire\Turmas;

use App\Models\Aula;
use App\Models\Turma;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Aulas'])]
class Aulas extends Component
{
    public Turma $turma;

    // ✅ ADICIONADO: Propriedades para armazenar os totais
    public int $totalDisciplinas = 0;
    public int $totalProfessores = 0;
    public int $totalCargaHoraria = 0;

    public function mount(Turma $turma): void
    {
        $this->turma = $turma;
    }

    public function render()
    {
        // ✅ ADICIONADO: Lógica para calcular os totais
        $aulasDaTurma = $this->turma->aulas; // Assumindo que 'aulas' é um relacionamento na Turma

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
                default => 1, // Valor padrão caso o tipo não seja reconhecido
            };
            $this->totalCargaHoraria += ($aula->aulas_semana * $tipoFactor);
        }

        return view('livewire.turmas.aulas', [
            // ✅ ADICIONADO: Passar os totais para a view
            'totalDisciplinas' => $this->totalDisciplinas,
            'totalProfessores' => $this->totalProfessores,
            'totalCargaHoraria' => $this->totalCargaHoraria,
        ]);
    }

    public function delete(int $id): void
    {
        $aula = Aula::findOrFail($id);

        if ($aula->alocacoes()->count() > 0) {
            session()->flash('error', 'Não é possível excluir esta aula pois ela possui alocações de horários.');
            return;
        }

        $aula->delete();

        session()->flash('success', 'Aula excluída com sucesso!');
    }
}
