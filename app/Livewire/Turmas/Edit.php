<?php

namespace App\Livewire\Turmas;

use App\Models\Turma;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Editar Turma'])]
class Edit extends Component
{
    public Turma $turma;

    #[Rule('required|min:3|max:255')]
    public string $nome = '';

    #[Rule('required|max:20')]
    public string $codigo = '';

    #[Rule('required|in:matutino,vespertino,noturno')]
    public string $turno = '';

    #[Rule('required|integer|min:1|max:100')]
    public int $numero_alunos = 30;

    #[Rule('required|integer|min:2020|max:2100')]
    public int $ano;

    public bool $ativa = true;

    public function mount(Turma $turma): void
    {
        $this->turma = $turma;
        $this->nome = $turma->nome;
        $this->codigo = $turma->codigo;
        $this->turno = $turma->turno;
        $this->numero_alunos = $turma->numero_alunos;
        $this->ano = $turma->ano;
        $this->ativa = $turma->ativa;
    }

    public function update(): void
    {
        // Validar código único exceto para a turma atual
        $this->validate([
            'nome' => 'required|min:3|max:255',
            'codigo' => 'required|max:20|unique:turmas,codigo,' . $this->turma->id,
            'turno' => 'required|in:matutino,vespertino,noturno',
            'numero_alunos' => 'required|integer|min:1|max:100',
            'ano' => 'required|integer|min:2020|max:2100',
        ]);

        try {
            $this->turma->update([
                'nome' => $this->nome,
                'codigo' => strtoupper($this->codigo),
                'turno' => $this->turno,
                'numero_alunos' => $this->numero_alunos,
                'ano' => $this->ano,
                'ativa' => $this->ativa,
            ]);

            session()->flash('success', 'Turma atualizada com sucesso!');

            $this->redirect(route('turmas.index'));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao atualizar turma: ' . $e->getMessage());
        }
    }

    public function delete(): void
    {
        // Verificar se há alocações
        if ($this->turma->alocacoes()->count() > 0) {
            session()->flash('error', 'Não é possível excluir esta turma pois ela possui alocações de horários.');
            return;
        }

        try {
            $this->turma->delete();

            session()->flash('success', 'Turma excluída com sucesso!');

            $this->redirect(route('turmas.index'));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao excluir turma: ' . $e->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('turmas.index'));
    }

    public function render()
    {
        return view('livewire.turmas.edit');
    }
}
