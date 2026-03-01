<?php
// app/Livewire/Horarios/ResumoConfiguracao.php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use App\Models\Aula;
use App\Models\RestricaoTempo;
use Livewire\Component;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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

    public function getAulasPorTurmaProperty(): Collection {
        return $this->horario->aulas->groupBy('turma_id')->map(function ($aulasDaTurma) {

            $turma = $aulasDaTurma->first()->turma;

            return [
                'turma' => $turma,
                'total_aulas' => $aulasDaTurma->count(),
                'total_tempos' => $aulasDaTurma->sum(function ($aula) {
                    return $aula->aulas_semana * match ($aula->tipo) {
                        'simples' => 1,
                        'dupla' => 2,
                        'tripla' => 3,
                        default => 1,
                    };
                }),
                'disciplinas' => $aulasDaTurma->pluck('disciplina')->unique('id'),
            ];
        });
    }

    public function getProntoParaGerarProperty() {
        $config = $this->horario->configuracaoHorario;
        $aulas = $this->horario->aulas->count();

        return $config && $aulas > 0;
    }

    public function iniciarGeracao() {
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
        Log::info("Renderizando view livewire.horarios.resumo-configuracao via app\Livewire\Horarios\ResumoConfiguracao.php");
        return view('livewire.horarios.resumo-configuracao', [
            'estatisticas' => $this->estatisticas,
            'restricoes' => $this->restricoes,
            'aulasPorTurma' => $this->aulasPorTurma,
            'prontoParaGerar' => $this->prontoParaGerar,
            'diasDaSemana' => $this->diasDaSemana, // Passar para a view
        ]);
    }
}
