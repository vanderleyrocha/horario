<?php

namespace App\Modules\Horarios\UI\Livewire\Professores;

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
        $professor = Professor::query()->findOrFail($id);

        if ($professor->alocacoes()->count() > 0) {
            session()->flash('error', 'Nao e possivel excluir este professor pois ele possui alocacoes de horarios.');

            return;
        }

        $professor->delete();

        session()->flash('success', 'Professor excluido com sucesso!');
    }

    public function toggleStatus(int $id): void
    {
        $professor = Professor::query()->findOrFail($id);
        $professor->ativo = ! $professor->ativo;
        $professor->save();

        session()->flash('success', sprintf(
            'Professor %s com sucesso!',
            $professor->ativo ? 'ativado' : 'desativado'
        ));
    }

    public function render()
    {
        $professores = Professor::query()
            ->when($this->search, fn ($query) => $query
                ->where('nome', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('modules.horarios.livewire.professores.index', [
            'professores' => $professores,
        ]);
    }
}
