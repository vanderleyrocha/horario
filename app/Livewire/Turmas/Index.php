<?php

namespace App\Livewire\Turmas;

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
        $turma = Turma::findOrFail($id);

        if ($turma->alocacoes()->count() > 0) {
            session()->flash('error', 'Não é possível excluir esta turma pois ela possui alocações de horários.');
            return;
        }

        $turma->delete();

        session()->flash('success', 'Turma excluída com sucesso!');
    }

    public function toggleStatus(int $id): void
    {
        $turma = Turma::findOrFail($id);
        $turma->ativa = !$turma->ativa;
        $turma->save();

        $status = $turma->ativa ? 'ativada' : 'desativada';
        session()->flash('success', "Turma {$status} com sucesso!");
    }

    public function render()
    {
        $turmas = Turma::query()
            ->when($this->search, fn($query) => 
                $query->where('nome', 'like', "%{$this->search}%")
                      ->orWhere('codigo', 'like', "%{$this->search}%")
            )
            ->when($this->filterTurno, fn($query) => 
                $query->where('turno', $this->filterTurno)
            )
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('livewire.turmas.index', [
            'turmas' => $turmas,
        ]);
    }
}
