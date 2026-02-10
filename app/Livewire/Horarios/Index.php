<?php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.app-layout', ['title' => 'Horários'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public string $filterAno = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAno(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $horario = Horario::findOrFail($id);
        $horario->delete();

        session()->flash('success', 'Horário excluído com sucesso!');
    }

    public function duplicate(int $id): void
    {
        $horario = Horario::findOrFail($id);

        $novoHorario = $horario->replicate();
        $novoHorario->nome = $horario->nome . ' (Cópia)';
        $novoHorario->status = 'rascunho';
        $novoHorario->gerado_em = null;
        $novoHorario->save();

        // Duplicar alocações
        foreach ($horario->alocacoes as $alocacao) {
            $novaAlocacao = $alocacao->replicate();
            $novaAlocacao->horario_id = $novoHorario->id;
            $novaAlocacao->save();
        }

        session()->flash('success', 'Horário duplicado com sucesso!');

        $this->redirect(route('horarios.show', $novoHorario));
    }

    public function setActive(int $id): void
    {
        // Desativar todos os horários
        Horario::where('status', 'ativo')->update(['status' => 'concluido']);

        // Ativar o selecionado
        $horario = Horario::findOrFail($id);
        $horario->status = 'ativo';
        $horario->save();

        session()->flash('success', 'Horário ativado com sucesso!');
    }

    public function getAnosProperty()
    {
        return Horario::distinct()->pluck('ano')->sort()->values();
    }

    public function render()
    {
        $horarios = Horario::query()
            ->when($this->search, fn($query) => 
                $query->where('nome', 'like', "%{$this->search}%")
            )
            ->when($this->filterStatus, fn($query) => 
                $query->where('status', $this->filterStatus)
            )
            ->when($this->filterAno, fn($query) => 
                $query->where('ano', $this->filterAno)
            )
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('livewire.horarios.index', [
            'horarios' => $horarios,
        ]);
    }
}
