<?php

namespace App\Modules\Horarios\UI\Livewire\Disciplinas;

use App\Models\Disciplina;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Editar Disciplina'])]
class Edit extends Component
{
    public Disciplina $disciplina;

    #[Rule('required|min:3|max:255')]
    public string $nome = '';

    #[Rule('required|max:20')]
    public string $codigo = '';

    #[Rule('required|integer|min:1|max:40')]
    public int $carga_horaria_semanal = 2;

    #[Rule('nullable|string|max:500')]
    public string $descricao = '';

    #[Rule('required|regex:/^#[0-9A-F]{6}$/i')]
    public string $cor = '#3B82F6';

    public bool $ativa = true;

    public array $coresPreDefinidas = [
        '#3B82F6',
        '#10B981',
        '#F59E0B',
        '#EF4444',
        '#8B5CF6',
        '#EC4899',
        '#06B6D4',
        '#F97316',
        '#14B8A6',
        '#6366F1',
    ];

    public function mount(Disciplina $disciplina): void
    {
        $this->disciplina = $disciplina;
        $this->nome = $disciplina->nome;
        $this->codigo = $disciplina->codigo;
        $this->carga_horaria_semanal = (int) $disciplina->carga_horaria_semanal;
        $this->descricao = (string) ($disciplina->descricao ?? '');
        $this->cor = $disciplina->cor;
        $this->ativa = (bool) $disciplina->ativa;
    }

    public function update(): void
    {
        $this->validate([
            'nome' => 'required|min:3|max:255',
            'codigo' => 'required|max:20|unique:disciplinas,codigo,'.$this->disciplina->id,
            'carga_horaria_semanal' => 'required|integer|min:1|max:40',
            'descricao' => 'nullable|string|max:500',
            'cor' => 'required|regex:/^#[0-9A-F]{6}$/i',
        ]);

        try {
            $this->disciplina->update([
                'nome' => $this->nome,
                'codigo' => strtoupper($this->codigo),
                'carga_horaria_semanal' => $this->carga_horaria_semanal,
                'descricao' => $this->descricao ?: null,
                'cor' => $this->cor,
                'ativa' => $this->ativa,
            ]);

            session()->flash('success', 'Disciplina atualizada com sucesso!');

            $this->redirect(route('disciplinas.index'));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao atualizar disciplina: '.$e->getMessage());
        }
    }

    public function delete(): void
    {
        if ($this->disciplina->alocacoes()->count() > 0) {
            session()->flash('error', 'Nao e possivel excluir esta disciplina pois ela possui alocacoes de horarios.');

            return;
        }

        try {
            $this->disciplina->delete();

            session()->flash('success', 'Disciplina excluida com sucesso!');

            $this->redirect(route('disciplinas.index'));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao excluir disciplina: '.$e->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('disciplinas.index'));
    }

    public function render()
    {
        return view('modules.horarios.livewire.disciplinas.edit');
    }
}
