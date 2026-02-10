<?php
// app/Livewire/Horarios/GerenciarRestricoes.php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use App\Models\RestricaoTempo;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\Disciplina;
use Livewire\Component;

class GerenciarRestricoes extends Component
{
    public Horario $horario;

    // Configurações
    public $diasSemana = [];
    public $tempos = [];

    // Seleção
    public $tipoEntidade = 'professor'; // professor, turma, disciplina
    public $entidadeSelecionada = null;

    // Grade de Restrições
    public $restricoes = [];

    // Modal de Edição Rápida
    public $modalEdicao = false;
    public $edicaoDia = null;
    public $edicaoTempo = null;
    public $edicaoStatus = 'livre';
    public $edicaoMotivo = '';
    public $edicaoPeso = 1;

    public function mount(Horario $horario)
    {
        $this->horario = $horario;

        // ✅ CORREÇÃO: Usar configuracaoHorario (relacionamento) em vez de configuracao (campo JSON)
        $config = $horario->configuracaoHorario;

        if ($config) {
            $this->diasSemana = range(1, $config->dias_semana);
            $this->tempos = range(1, $config->aulas_por_dia);
        } else {
            // Valores padrão caso não tenha configuração
            $this->diasSemana = range(1, 5);
            $this->tempos = range(1, 7);
        }
    }

    public function updatedTipoEntidade()
    {
        $this->entidadeSelecionada = null;
        $this->restricoes = [];
    }

    public function updatedEntidadeSelecionada()
    {
        $this->carregarRestricoes();
    }

    public function carregarRestricoes()
    {
        if (!$this->entidadeSelecionada) {
            $this->restricoes = [];
            return;
        }

        $modelClass = $this->getModelClass();

        $restricoesDb = RestricaoTempo::where('horario_id', $this->horario->id)
            ->where('entidade_type', $modelClass)
            ->where('entidade_id', $this->entidadeSelecionada)
            ->get()
            ->keyBy(fn($r) => "{$r->dia_semana}_{$r->tempo}");

        $this->restricoes = [];
        foreach ($this->diasSemana as $dia) {
            foreach ($this->tempos as $tempo) {
                $key = "{$dia}_{$tempo}";
                $this->restricoes[$key] = $restricoesDb->get($key)?->status ?? 'livre';
            }
        }
    }

    protected function getModelClass()
    {
        return match($this->tipoEntidade) {
            'professor' => Professor::class,
            'turma' => Turma::class,
            'disciplina' => Disciplina::class,
        };
    }

    public function alterarStatus($dia, $tempo)
    {
        $key = "{$dia}_{$tempo}";
        $statusAtual = $this->restricoes[$key] ?? 'livre';

        // Ciclo: livre -> preferencial -> bloqueado -> livre
        $this->restricoes[$key] = match($statusAtual) {
            'livre' => 'preferencial',
            'preferencial' => 'bloqueado',
            'bloqueado' => 'livre',
            default => 'livre',
        };

        $this->salvarRestricao($dia, $tempo, $this->restricoes[$key]);
    }

    public function abrirModalEdicao($dia, $tempo)
    {
        $key = "{$dia}_{$tempo}";
        $restricao = RestricaoTempo::where('horario_id', $this->horario->id)
            ->where('entidade_type', $this->getModelClass())
            ->where('entidade_id', $this->entidadeSelecionada)
            ->where('dia_semana', $dia)
            ->where('tempo', $tempo)
            ->first();

        $this->edicaoDia = $dia;
        $this->edicaoTempo = $tempo;
        $this->edicaoStatus = $restricao?->status ?? 'livre';
        $this->edicaoMotivo = $restricao?->motivo ?? '';
        $this->edicaoPeso = $restricao?->peso ?? 1;
        $this->modalEdicao = true;
    }

    public function salvarEdicao()
    {
        $this->salvarRestricao(
            $this->edicaoDia,
            $this->edicaoTempo,
            $this->edicaoStatus,
            $this->edicaoMotivo,
            $this->edicaoPeso
        );

        $this->modalEdicao = false;
        $this->carregarRestricoes();
    }

    protected function salvarRestricao($dia, $tempo, $status, $motivo = null, $peso = 1)
    {
        if (!$this->entidadeSelecionada) {
            return;
        }

        RestricaoTempo::updateOrCreate(
            [
                'horario_id' => $this->horario->id,
                'entidade_type' => $this->getModelClass(),
                'entidade_id' => $this->entidadeSelecionada,
                'dia_semana' => $dia,
                'tempo' => $tempo,
            ],
            [
                'status' => $status,
                'motivo' => $motivo,
                'peso' => $peso,
            ]
        );
    }

    public function aplicarBloqueioMassa($status)
    {
        if (!$this->entidadeSelecionada) {
            session()->flash('error', 'Selecione uma entidade primeiro');
            return;
        }

        foreach ($this->diasSemana as $dia) {
            foreach ($this->tempos as $tempo) {
                $this->salvarRestricao($dia, $tempo, $status);
            }
        }

        $this->carregarRestricoes();
        session()->flash('success', 'Bloqueio em massa aplicado!');
    }

    public function limparRestricoes()
    {
        if (!$this->entidadeSelecionada) {
            return;
        }

        RestricaoTempo::where('horario_id', $this->horario->id)
            ->where('entidade_type', $this->getModelClass())
            ->where('entidade_id', $this->entidadeSelecionada)
            ->delete();

        $this->carregarRestricoes();
        session()->flash('success', 'Restrições removidas!');
    }

    public function getProfessoresProperty()
    {
        return Professor::ativo()->orderBy('nome')->get();
    }

    public function getTurmasProperty()
    {
        return Turma::ativa()->orderBy('nome')->get();
    }

    public function getDisciplinasProperty()
    {
        return Disciplina::ativa()->orderBy('nome')->get();
    }

    public function getEntidadesProperty()
    {
        return match($this->tipoEntidade) {
            'professor' => $this->professores,
            'turma' => $this->turmas,
            'disciplina' => $this->disciplinas,
        };
    }

    public function getDiaNome($dia)
    {
        $dias = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];
        return $dias[$dia] ?? '';
    }

    public function render()
    {
        return view('livewire.horarios.gerenciar-restricoes', [
            'entidades' => $this->entidades,
        ]);
    }
}
