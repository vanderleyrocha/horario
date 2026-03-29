<?php

namespace App\Modules\Horarios\UI\Livewire\Aulas;

use App\Models\Horario;
use App\Models\Turma;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Aulas'])]
class Index extends Component
{
    public Horario $horario;

    public function mount(int $horario_id): void
    {
        $this->horario = Horario::query()->findOrFail($horario_id);
    }

    public function render()
    {
        $turmas = Turma::query()
            ->where('ano', $this->horario->ano)
            ->with('aulas')
            ->get();

        foreach ($turmas as $turma) {
            $turma->ch = $turma->aulas->sum(
                fn ($aula) => $aula->aulas_semana * $this->getTipoValue((string) $aula->tipo)
            );
        }

        return view('modules.horarios.livewire.aulas.index', [
            'turmas' => $turmas,
        ]);
    }

    private function getTipoValue(string $tipo): int
    {
        return match ($tipo) {
            'simples' => 1,
            'dupla' => 2,
            'tripla' => 3,
            default => 0,
        };
    }
}
