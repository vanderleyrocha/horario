<?php

namespace App\Livewire\Disciplinas;

use App\Models\Disciplina;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Nova Disciplina'])]
class Create extends Component
{
    #[Rule('required|min:3|max:255')]
    public string $nome = '';

    #[Rule('required|unique:disciplinas,codigo|max:20')]
    public string $codigo = '';

    #[Rule('required|integer|min:1|max:40')]
    public int $carga_horaria_semanal = 2;

    #[Rule('nullable|string|max:500')]
    public string $descricao = '';

    #[Rule('required|regex:/^#[0-9A-F]{6}$/i')]
    public string $cor = '#3B82F6';

    public array $coresPreDefinidas = [
        '#3B82F6', // Blue
        '#10B981', // Green
        '#F59E0B', // Amber
        '#EF4444', // Red
        '#8B5CF6', // Violet
        '#EC4899', // Pink
        '#06B6D4', // Cyan
        '#F97316', // Orange
        '#14B8A6', // Teal
        '#6366F1', // Indigo
    ];

    public function save(): void
    {
        $this->validate();

        try {
            Disciplina::create([
                'nome' => $this->nome,
                'codigo' => strtoupper($this->codigo),
                'carga_horaria_semanal' => $this->carga_horaria_semanal,
                'descricao' => $this->descricao ?: null,
                'cor' => $this->cor,
                'ativa' => true,
            ]);

            session()->flash('success', 'Disciplina cadastrada com sucesso!');

            $this->redirect(route('disciplinas.index'));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao cadastrar disciplina: ' . $e->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('disciplinas.index'));
    }

    public function render()
    {
        return view('livewire.disciplinas.create');
    }
}
