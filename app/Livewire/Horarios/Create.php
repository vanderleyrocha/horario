<?php
// app/Livewire/Horarios/Create.php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Novo Horário'])]
class Create extends Component
{
    #[Rule('required|min:3|max:255')]
    public string $nome = '';

    #[Rule('required|integer|min:2020|max:2100')]
    public int $ano;

    #[Rule('required|integer|min:1|max:2')]
    public int $semestre = 1;

    public function mount(): void
    {
        $this->ano = now()->year;
    }

    public function save(): void
    {
        $this->validate();

        try {
            $user = Auth::user();
            $horario = Horario::create([
                'nome' => $this->nome,
                'ano' => $this->ano,
                'semestre' => $this->semestre,
                'status' => 'rascunho',
                'criado_por' => $user->id,
            ]);

            session()->flash('success', 'Horário criado com sucesso! Configure as informações antes de gerar.');

            // Redirecionar para configuração em vez de visualização
            $this->redirect(route('horarios.configurar', $horario), navigate: true);
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao criar horário: ' . $e->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('horarios.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.horarios.create');
    }
}
