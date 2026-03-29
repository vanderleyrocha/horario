<?php

namespace App\Modules\Horarios\UI\Livewire\Turmas;

use App\Models\Turma;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.app-layout', ['title' => 'Turmas'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterTurno = '';

    public string $sortField = 'nome';

    public string $sortDirection = 'asc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTurno(): void
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
        $turma = Turma::query()->findOrFail($id);

        if ($turma->alocacoes()->count() > 0) {
            session()->flash('error', 'Nao e possivel excluir esta turma pois ela possui alocacoes de horarios.');

            return;
        }

        $turma->delete();

        session()->flash('success', 'Turma excluida com sucesso!');
    }

    public function toggleStatus(int $id): void
    {
        $turma = Turma::query()->findOrFail($id);
        $turma->ativa = ! $turma->ativa;
        $turma->save();

        session()->flash('success', sprintf(
            'Turma %s com sucesso!',
            $turma->ativa ? 'ativada' : 'desativada'
        ));
    }

    public function render()
    {
        $turmas = Turma::query()
            ->when($this->search, fn ($query) => $query
                ->where('nome', 'like', "%{$this->search}%")
                ->orWhere('codigo', 'like', "%{$this->search}%"))
            ->when($this->filterTurno, fn ($query) => $query->where('turno', $this->filterTurno))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('modules.horarios.livewire.turmas.index', [
            'turmas' => $turmas,
        ]);
    }
}
