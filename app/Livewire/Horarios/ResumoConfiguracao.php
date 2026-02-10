<?php
// app/Livewire/Horarios/ResumoConfiguracao.php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use App\Models\Aula;
use App\Models\RestricaoTempo;
use Livewire\Component;
use Illuminate\Support\Collection;

class ResumoConfiguracao extends Component {
    public Horario $horario;

    // ✅ ATUALIZADO: Array para mapear dias da semana com string e display name
    public array $diasDaSemana = [
        1 => ['string' => 'segunda', 'display' => 'Segunda'],
        2 => ['string' => 'terca', 'display' => 'Terça'],
        3 => ['string' => 'quarta', 'display' => 'Quarta'],
        4 => ['string' => 'quinta', 'display' => 'Quinta'],
        5 => ['string' => 'sexta', 'display' => 'Sexta'],
        6 => ['string' => 'sabado', 'display' => 'Sábado'],
        7 => ['string' => 'domingo', 'display' => 'Domingo'],
    ];

    public function mount(Horario $horario) {
        $this->horario = $horario->load([
            'configuracaoHorario',
            'aulas.professor',
            'aulas.disciplina',
            'aulas.turma',
            'alocacoes.aula.disciplina',
            'alocacoes.aula.turma',
        ]);
    }

    public function getEstatisticasProperty() {
        $config = $this->horario->configuracaoHorario;
        $aulas = $this->horario->aulas;

        $totalTemposNecessarios = $aulas->sum(function ($aula) {
            return $aula->aulas_semana * match ($aula->tipo) {
                'simples' => 1,
                'dupla' => 2,
                'tripla' => 3,
                default => 1,
            };
        });

        $temposDisponiveis = $config
            ? ($config->aulas_por_dia * $config->dias_semana)
            : 25;

        $turmasUnicas = $aulas->unique('turma_id')->count();
        $professoresUnicos = $aulas->unique('professor_id')->count();
        $disciplinasUnicas = $aulas->unique('disciplina_id')->count();

        return [
            'total_aulas' => $aulas->count(),
            'total_tempos_necessarios' => $totalTemposNecessarios,
            'tempos_disponiveis' => $temposDisponiveis,
            'taxa_ocupacao' => $turmasUnicas > 0 && $temposDisponiveis > 0
                ? round(($totalTemposNecessarios / ($temposDisponiveis * $turmasUnicas)) * 100, 1)
                : 0,
            'turmas' => $turmasUnicas,
            'professores' => $professoresUnicos,
            'disciplinas' => $disciplinasUnicas,
        ];
    }

    public function getRestricoesProperty() {
        return RestricaoTempo::where('horario_id', $this->horario->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->toArray();
    }

    public function getAulasPorTurmaProperty(): Collection
    {
        $alocacoesValidas = $this->horario->alocacoes->filter(function ($alocacao) {
            return $alocacao->aula !== null;
        });

        $aulasPorTurma = $alocacoesValidas->groupBy(fn($alocacao) => optional($alocacao->aula)->turma_id);

        return $aulasPorTurma->map(function ($alocacoesDaTurma, $turmaId) {
            if ($turmaId === null || $alocacoesDaTurma->isEmpty()) {
                return null;
            }

            $firstAlocacao = $alocacoesDaTurma->first();

            if (!$firstAlocacao->aula || !$firstAlocacao->aula->turma) {
                return null;
            }

            $turma = $firstAlocacao->aula->turma;

            $horarioDaTurma = [];
            foreach ($alocacoesDaTurma as $alocacao) {
                if ($alocacao->aula && $alocacao->aula->disciplina) {
                    $horarioDaTurma[$alocacao->dia_semana][$alocacao->tempo] = $alocacao;
                }
            }

            return [
                'turma' => $turma,
                'horario_detalhado' => $horarioDaTurma,
                'total_aulas' => $alocacoesDaTurma->count(),
                'total_tempos' => $alocacoesDaTurma->sum(function ($alocacao) {
                    return $alocacao->duracao_tempos;
                }),
                'disciplinas' => $alocacoesDaTurma->pluck('aula.disciplina')->filter()->unique('id'),
            ];
        })->filter();
    }

    public function getProntoParaGerarProperty() {
        $config = $this->horario->configuracaoHorario;
        $aulas = $this->horario->aulas->count();

        return $config && $aulas > 0;
    }

    public function iniciarGeracao()
    {
        if (!$this->prontoParaGerar) {
            session()->flash('error', 'Configure o horário antes de gerar!');
            return;
        }

        return redirect()->route('algoritmo.index', ['horario' => $this->horario->id]);
    }

    public function voltarParaEdicao($etapa) {
        $this->dispatch('irParaEtapa', etapa: $etapa);
    }

    public function render() {
        return view('livewire.horarios.resumo-configuracao', [
            'estatisticas' => $this->estatisticas,
            'restricoes' => $this->restricoes,
            'aulasPorTurma' => $this->aulasPorTurma,
            'prontoParaGerar' => $this->prontoParaGerar,
            'diasDaSemana' => $this->diasDaSemana, // Passar para a view
        ]);
    }
}
