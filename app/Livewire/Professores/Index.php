<?php

namespace App\Livewire\Professores;

use App\Models\Professor;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.app-layout', ['title' => 'Professores'])]
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
        $professor = Professor::findOrFail($id);

        // Verificar se há alocações antes de excluir
        if ($professor->alocacoes()->count() > 0) {
            session()->flash('error', 'Não é possível excluir este professor pois ele possui alocações de horários.');
            return;
        }

        $professor->delete();

        session()->flash('success', 'Professor excluído com sucesso!');
    }

    public function toggleStatus(int $id): void
    {
        $professor = Professor::findOrFail($id);
        $professor->ativo = !$professor->ativo;
        $professor->save();

        $status = $professor->ativo ? 'ativado' : 'desativado';
        session()->flash('success', "Professor {$status} com sucesso!");
    }

    public function render()
    {
        $professores = Professor::query()
            ->when($this->search, fn($query) => 
                $query->where('nome', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
            )
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('livewire.professores.index', [
            'professores' => $professores,
        ]);
    }
}
