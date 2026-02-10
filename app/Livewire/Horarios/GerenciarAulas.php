<?php
// app/Livewire/Horarios/GerenciarAulas.php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use App\Models\Aula;
use Livewire\Component;
use Livewire\WithPagination;
use App\Traits\ComDadosComuns;

class GerenciarAulas extends Component {
    use WithPagination;
    use ComDadosComuns;

    public Horario $horario;

    // Modal de Adicionar/Editar
    public $modalAberto = false;
    public $editandoId = null;

    // Formulário
    public $professor_id = '';
    public $disciplina_id = '';
    public $turma_id = '';
    public $aulas_semana = 2;
    public $tipo = 'simples';
    public $aulas_consecutivas = false;
    public $max_aulas_dia = 2;
    public $min_intervalo_dias = 0;
    public $preferencia_periodo = 'qualquer';
    public $dias_preferidos = [];
    public $tempos_preferidos = [];
    public $observacoes = '';

    // Filtros
    public $filtroTurma = '';
    public $filtroProfessor = '';
    public $busca = '';

    protected $paginationTheme = 'tailwind';
    protected $listeners = ['fecharModal' => 'fecharModal'];

    public function mount(Horario $horario) {
        $this->horario = $horario;
    }

    public function abrirModal() {
        $this->resetearFormulario();
        $this->modalAberto = true;
    }

    public function resetearFormulario() {
        $this->editandoId = null;
        $this->professor_id = '';
        $this->disciplina_id = '';
        $this->turma_id = '';
        $this->aulas_semana = 2;
        $this->tipo = 'simples';
        $this->aulas_consecutivas = false;
        $this->max_aulas_dia = 2;
        $this->min_intervalo_dias = 0;
        $this->preferencia_periodo = 'qualquer';
        $this->dias_preferidos = [];
        $this->tempos_preferidos = [];
        $this->observacoes = '';
        $this->resetValidation();
    }

    public function fecharModal() {
        $this->modalAberto = false;
        $this->resetearFormulario();
    }

    public function editar($aulaId) {
        $aula = Aula::findOrFail($aulaId);

        $this->editandoId = $aula->id;
        $this->professor_id = $aula->professor_id;
        $this->disciplina_id = $aula->disciplina_id;
        $this->turma_id = $aula->turma_id;
        $this->aulas_semana = $aula->aulas_semana;
        $this->tipo = $aula->tipo;
        $this->aulas_consecutivas = $aula->aulas_consecutivas;
        $this->max_aulas_dia = $aula->max_aulas_dia;
        $this->min_intervalo_dias = $aula->min_intervalo_dias ?? 0;
        $this->preferencia_periodo = $aula->preferencia_periodo ?? 'qualquer';

        $this->dias_preferidos = is_array($aula->dias_preferidos)
            ? $aula->dias_preferidos
            : (json_decode($aula->dias_preferidos, true) ?? []);

        $this->tempos_preferidos = is_array($aula->tempos_preferidos)
            ? $aula->tempos_preferidos
            : (json_decode($aula->tempos_preferidos, true) ?? []);

        $this->observacoes = $aula->observacoes ?? '';

        $this->modalAberto = true;
    }

    public function salvar() {
        $this->validate([
            'professor_id' => 'required|exists:professores,id',
            'disciplina_id' => 'required|exists:disciplinas,id',
            'turma_id' => 'required|exists:turmas,id',
            'aulas_semana' => 'required|integer|min:1|max:10',
            'tipo' => 'required|in:simples,dupla,tripla',
            'max_aulas_dia' => 'required|integer|min:1|max:5',
        ]);

        $dados = [
            'horario_id' => $this->horario->id,
            'professor_id' => $this->professor_id,
            'disciplina_id' => $this->disciplina_id,
            'turma_id' => $this->turma_id,
            'aulas_semana' => $this->aulas_semana,
            'tipo' => $this->tipo,
            'aulas_consecutivas' => $this->aulas_consecutivas,
            'max_aulas_dia' => $this->max_aulas_dia,
            'min_intervalo_dias' => $this->min_intervalo_dias,
            'preferencia_periodo' => $this->preferencia_periodo,
            'dias_preferidos' => !empty($this->dias_preferidos) ? $this->dias_preferidos : null,
            'tempos_preferidos' => !empty($this->tempos_preferidos) ? $this->tempos_preferidos : null,
            'observacoes' => $this->observacoes,
            'ativa' => true,
        ];

        if ($this->editandoId) {
            $aula = Aula::findOrFail($this->editandoId);
            $aula->update($dados);
            session()->flash('success', 'Aula atualizada com sucesso!');
        } else {
            Aula::create($dados);
            session()->flash('success', 'Aula adicionada com sucesso!');
        }

        $this->fecharModal();
        $this->resetPage();
    }

    public function duplicar($aulaId) {
        $aula = Aula::findOrFail($aulaId);

        $novaAula = $aula->replicate();
        $novaAula->save();

        session()->flash('success', 'Aula duplicada com sucesso!');
        $this->resetPage();
    }

    public function excluir($aulaId) {
        $aula = Aula::findOrFail($aulaId);
        $aula->delete();

        session()->flash('success', 'Aula excluída com sucesso!');
        $this->resetPage();
    }

    public function getAulasProperty() {
        $query = Aula::where('horario_id', $this->horario->id)
            ->with(['professor', 'disciplina', 'turma']);

        if ($this->filtroTurma) {
            $query->where('turma_id', $this->filtroTurma);
        }

        if ($this->filtroProfessor) {
            $query->where('professor_id', $this->filtroProfessor);
        }

        if ($this->busca) {
            $query->where(function ($q) {
                $q->whereHas('professor', function ($sq) {
                    $sq->where('nome', 'like', "%{$this->busca}%");
                })
                    ->orWhereHas('disciplina', function ($sq) {
                        $sq->where('nome', 'like', "%{$this->busca}%");
                    })
                    ->orWhereHas('turma', function ($sq) {
                        $sq->where('nome', 'like', "%{$this->busca}%");
                    });
            });
        }

        return $query->orderBy('turma_id')
            ->orderBy('disciplina_id')
            ->paginate(15);
    }

    public function render() {
        return view('livewire.horarios.gerenciar-aulas', [
            'aulas' => $this->aulas,
            'professores' => $this->professores,
            'disciplinas' => $this->disciplinas,
            'turmas' => $this->turmas,
        ]);
    }
}
