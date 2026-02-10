<?php

namespace App\Livewire\Professores;

use App\Models\Professor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Editar Professor'])]
class Edit extends Component
{
    public Professor $professor;

    #[Rule('required|min:3|max:255')]
    public string $nome = '';

    #[Rule('required|email')]
    public string $email = '';

    #[Rule('nullable|string|max:20')]
    public string $telefone = '';

    #[Rule('required|integer|min:1|max:60')]
    public int $carga_horaria_maxima = 40;

    public array $dias_disponiveis = [];

    public bool $ativo = true;

    public function mount(Professor $professor): void
    {
        $this->professor = $professor;
        $this->nome = $professor->nome;
        $this->email = $professor->email;
        $this->telefone = $professor->telefone ?? '';
        $this->carga_horaria_maxima = $professor->carga_horaria_maxima;
        $this->dias_disponiveis = $professor->dias_disponiveis ?? [];
        $this->ativo = $professor->ativo;
    }

    public function getDiasSemanaProperty(): array
    {
        return [
            'segunda' => 'Segunda-feira',
            'terca' => 'Terça-feira',
            'quarta' => 'Quarta-feira',
            'quinta' => 'Quinta-feira',
            'sexta' => 'Sexta-feira',
        ];
    }

    public function update(): void
    {
        // Validar email único exceto para o professor atual
        $this->validate([
            'nome' => 'required|min:3|max:255',
            'email' => 'required|email|unique:professores,email,' . $this->professor->id,
            'telefone' => 'nullable|string|max:20',
            'carga_horaria_maxima' => 'required|integer|min:1|max:60',
        ]);

        try {
            $this->professor->update([
                'nome' => $this->nome,
                'email' => $this->email,
                'telefone' => $this->telefone ?: null,
                'carga_horaria_maxima' => $this->carga_horaria_maxima,
                'dias_disponiveis' => $this->dias_disponiveis,
                'ativo' => $this->ativo,
            ]);

            session()->flash('success', 'Professor atualizado com sucesso!');

            $this->redirect(route('professores.index'));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao atualizar professor: ' . $e->getMessage());
        }
    }

    public function delete(): void
    {
        // Verificar se há alocações
        if ($this->professor->alocacoes()->count() > 0) {
            session()->flash('error', 'Não é possível excluir este professor pois ele possui alocações de horários.');
            return;
        }

        try {
            $this->professor->delete();

            session()->flash('success', 'Professor excluído com sucesso!');

            $this->redirect(route('professores.index'));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao excluir professor: ' . $e->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('professores.index'));
    }

    public function render()
    {
        return view('livewire.professores.edit');
    }
}
