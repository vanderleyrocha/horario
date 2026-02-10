<?php

namespace App\Http\Controllers;

use App\Models\Horario;
use Illuminate\Http\Request;

class TesteController extends Controller {

    protected $horario;

    public function alocacoes() {
        // return "Teste alocações";
        $this->horario = Horario::findOrFail(1);

        $load = $this->horario->load([
            'configuracaoHorario',
            'aulas.professor',
            'aulas.disciplina',
            'aulas.turma',
            'alocacoes.aula.disciplina',
            'alocacoes.aula.turma',
        ]);

        $alocacoes = $load->alocacoes;
        $aulasPorTurma = $alocacoes->groupBy('aula.turma_id');
        return $load->aulas_por_turma;
    }

    public function getAulasPorTurmaProperty() {
        $alocacoes = $this->horario->alocacoes;

        // Agrupar as alocações pela turma da aula alocada
        $aulasPorTurma = $alocacoes->groupBy('aula.turma_id');

        return $aulasPorTurma->map(function ($alocacoesDaTurma, $turmaId) {
            // ✅ ADICIONADO: Verificação para garantir que há alocações antes de tentar acessar first()
            if ($alocacoesDaTurma->isEmpty()) {
                return null; // Ou um array vazio, dependendo de como você quer tratar turmas sem alocações
            }

            // Pega a turma de uma das alocações (garantindo que a relação 'aula.turma' foi carregada)
            $firstAlocacao = $alocacoesDaTurma->first();

            // ✅ ADICIONADO: Verificação para garantir que 'aula' e 'turma' existem
            if (!$firstAlocacao->aula || !$firstAlocacao->aula->turma) {
                return null; // Se a aula ou a turma da aula for nula, ignora esta entrada
            }

            $turma = $firstAlocacao->aula->turma;

            // Estruturar as alocações para fácil acesso na view (dia -> tempo -> alocacao)
            $horarioDaTurma = [];
            foreach ($alocacoesDaTurma as $alocacao) {
                // Certifique-se de que a aula e a disciplina estão carregadas na alocação
                $alocacao->loadMissing('aula.disciplina');
                $horarioDaTurma[$alocacao->dia_semana][$alocacao->tempo] = $alocacao;
            }

            return [
                'turma' => $turma,
                'horario_detalhado' => $horarioDaTurma,
                'total_aulas' => $alocacoesDaTurma->count(),
                'total_tempos' => $alocacoesDaTurma->sum(function ($alocacao) {
                    return $alocacao->duracao_tempos;
                }),
                'disciplinas' => $alocacoesDaTurma->pluck('aula.disciplina')->unique('id'),
            ];
        })->filter();
    }
}
