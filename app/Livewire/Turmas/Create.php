<?php

namespace App\Livewire\Turmas;

use App\Models\Turma;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Nova Turma'])]
class Create extends Component
{
    #[Rule('required|min:3|max:255')]
    public string $nome = '';

    #[Rule('required|unique:turmas,codigo|max:20')]
    public string $codigo = '';

    #[Rule('required|in:matutino,vespertino,noturno')]
    public string $turno = '';

    #[Rule('required|integer|min:1|max:100')]
    public int $numero_alunos = 30;

    #[Rule('required|integer|min:2020|max:2100')]
    public int $ano;

    public function mount(): void
    {
        $this->ano = now()->year;
    }

    public function save(): void
    {
        $this->validate();

        try {
            Turma::create([
                'nome' => $this->nome,
                'codigo' => strtoupper($this->codigo),
                'turno' => $this->turno,
                'numero_alunos' => $this->numero_alunos,
                'ano' => $this->ano,
                'ativa' => true,
            ]);

            session()->flash('success', 'Turma cadastrada com sucesso!');

            $this->redirect(route('turmas.index'));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao cadastrar turma: ' . $e->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('turmas.index'));
    }

    public function render()
    {
        return view('livewire.turmas.create');
    }
}
