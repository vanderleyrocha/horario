<?php

namespace App\Modules\Horarios\UI\Livewire\Disciplinas;

use App\Models\Disciplina;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.app-layout', ['title' => 'Disciplinas'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortField = 'nome';

    public string $sortDirection = 'asc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }

        $this->sortField = $field;
    }

    public function delete(int $id): void
    {
        $disciplina = Disciplina::query()->findOrFail($id);

        if ($disciplina->alocacoes()->count() > 0) {
            session()->flash('error', 'Nao e possivel excluir esta disciplina pois ela possui alocacoes de horarios.');

            return;
        }

        $disciplina->delete();

        session()->flash('success', 'Disciplina excluida com sucesso!');
    }

    public function toggleStatus(int $id): void
    {
        $disciplina = Disciplina::query()->findOrFail($id);
        $disciplina->ativa = ! $disciplina->ativa;
        $disciplina->save();

        session()->flash('success', sprintf(
            'Disciplina %s com sucesso!',
            $disciplina->ativa ? 'ativada' : 'desativada'
        ));
    }

    public function render()
    {
        $disciplinas = Disciplina::query()
            ->when($this->search, fn ($query) => $query
                ->where('nome', 'like', "%{$this->search}%")
                ->orWhere('codigo', 'like', "%{$this->search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('modules.horarios.livewire.disciplinas.index', [
            'disciplinas' => $disciplinas,
        ]);
    }
}
