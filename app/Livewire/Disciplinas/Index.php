<?php

namespace App\Livewire\Disciplinas;

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
        $disciplina = Disciplina::findOrFail($id);

        if ($disciplina->alocacoes()->count() > 0) {
            session()->flash('error', 'Não é possível excluir esta disciplina pois ela possui alocações de horários.');
            return;
        }

        $disciplina->delete();

        session()->flash('success', 'Disciplina excluída com sucesso!');
    }

    public function toggleStatus(int $id): void
    {
        $disciplina = Disciplina::findOrFail($id);
        $disciplina->ativa = !$disciplina->ativa;
        $disciplina->save();

        $status = $disciplina->ativa ? 'ativada' : 'desativada';
        session()->flash('success', "Disciplina {$status} com sucesso!");
    }

    public function render()
    {
        $disciplinas = Disciplina::query()
            ->when($this->search, fn($query) => 
                $query->where('nome', 'like', "%{$this->search}%")
                      ->orWhere('codigo', 'like', "%{$this->search}%")
            )
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('livewire.disciplinas.index', [
            'disciplinas' => $disciplinas,
        ]);
    }
}
