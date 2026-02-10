<?php

namespace App\Livewire\Professores;

use App\Models\Professor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Novo Professor'])]
class Create extends Component
{
    #[Rule('required|min:3|max:255')]
    public string $nome = '';

    #[Rule('required|email|unique:professores,email')]
    public string $email = '';

    #[Rule('nullable|string|max:20')]
    public string $telefone = '';

    #[Rule('required|integer|min:1|max:60')]
    public int $carga_horaria_maxima = 40;

    public array $dias_disponiveis = [];

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

    public function save(): void
    {
        $this->validate();

        try {
            Professor::create([
                'nome' => $this->nome,
                'email' => $this->email,
                'telefone' => $this->telefone ?: null,
                'carga_horaria_maxima' => $this->carga_horaria_maxima,
                'dias_disponiveis' => $this->dias_disponiveis,
                'ativo' => true,
            ]);

            session()->flash('success', 'Professor cadastrado com sucesso!');

            $this->redirect(route('professores.index'));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao cadastrar professor: ' . $e->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('professores.index'));
    }

    public function render()
    {
        return view('livewire.professores.create');
    }
}
