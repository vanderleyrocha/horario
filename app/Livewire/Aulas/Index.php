<?php

namespace App\Livewire\Aulas;

use App\Models\Horario;
use App\Models\Turma;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Aulas'])]
class Index extends Component
{

    public Horario $horario;

    public function mount(int $horario_id)
    {
        $this->horario = Horario::findOrFail($horario_id);
    }

    public function render()
    {
        $turmas = Turma::where("ano", $this->horario->ano)->with("aulas")->get();
        foreach ($turmas as $turma) {
            $ch = 0;
            foreach ($turma->aulas as $aula) {
                $ch += $aula->aulas_semana * $this->getTipoValue($aula->tipo);
            }
            $turma->ch = $ch;
        }
         return view('livewire.aulas.index', [
            'turmas' => $turmas,
        ]);
    }

    private function getTipoValue($tipo) : int {
        if ($tipo == "simples") return 1;
        if ($tipo == "dupla") return 2;
        if ($tipo == "tripla") return 3;
        return 0;
    }
}
