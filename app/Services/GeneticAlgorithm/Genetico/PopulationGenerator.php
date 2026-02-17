<?php

namespace App\Services\GeneticAlgorithm\Genetico;

use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;
use Illuminate\Support\Facades\Log;

final class PopulationGenerator {
    public function __construct(
        private readonly array $aulas,
        private readonly GeneticAlgorithmConfigDTO $configAG
    ) {
    }

    public function generate(): array {

        $population = [];

        for ($i = 0; $i < $this->configAG->tamanhoPopulacao; $i++) {
            $population[] = $this->createDiverseCromossomo($i);
        }

        return $population;
    }

    /**
     * Gera cromossomo com alta diversidade estrutural
     */
    private function createDiverseCromossomo(int $seedOffset): Cromossomo {
        $genes = [];

        $aulas = $this->aulas;
        // shuffle($aulas);

        $horariosBase = $this->configAG->horariosDisponiveis;

        $ocupacaoProfessor = [];
        $ocupacaoTurma = [];

        foreach ($aulas as $aula) {

            $numAlocacoes = (int) $aula->aulas_semana;
            $duracao = $this->getDuracaoTempos($aula->tipo);

            for ($aloc = 0; $aloc < $numAlocacoes; $aloc++) {

                $horarios = $horariosBase;
                shuffle($horarios);

                $horarioEscolhido = null;

                foreach ($horarios as $horario) {

                    $dia = $horario['dia'];
                    $tempo = $horario['tempo'];

                    // 🔒 1. Impedir overflow no final do dia
                    if ($tempo + $duracao - 1 > $this->configAG->aulasPorDia) {
                        continue;
                    }

                    // 🔒 2. Impedir conflito professor/turma
                    if ($this->temConflitoBasico($aula->professor->id, $aula->turma->id, $dia, $tempo, $duracao, $ocupacaoProfessor, $ocupacaoTurma)) {
                        continue;
                    }

                    $horarioEscolhido = $horario;
                    break;
                }

                if (!$horarioEscolhido) {

                    Log::error("Impossível alocar aula {$aula->id} sem conflito. Professor {$aula->professor->id}, Turma {$aula->turma->id}");

                    throw new \RuntimeException("Configuração inviável: não há espaço suficiente para gerar população inicial válida.", 1);
                }

                $dia = $horarioEscolhido['dia'];
                $tempo = $horarioEscolhido['tempo'];

                // Registrar ocupação
                for ($d = 0; $d < $duracao; $d++) {
                    $ocupacaoProfessor[$aula->professor->id][$dia][$tempo + $d] = true;
                    $ocupacaoTurma[$aula->turma->id][$dia][$tempo + $d] = true;
                }

                $genes[] = new Gene(
                    aulaId: $aula->id,
                    professorId: $aula->professor->id,
                    turmaId: $aula->turma->id,
                    disciplinaId: $aula->disciplina->id,
                    diaSemana: $dia,
                    periodoDia: $tempo,
                    duracaoTempos: $duracao
                );
            }
        }

        return new Cromossomo($genes);
    }

    
    private function temConflitoBasico(int $professorId, int $turmaId, int $dia, int $tempo, int $duracao, array $ocupacaoProfessor, array $ocupacaoTurma): bool {

        for ($i = 0; $i < $duracao; $i++) {

            if (
                isset($ocupacaoProfessor[$professorId][$dia][$tempo + $i])
                ||
                isset($ocupacaoTurma[$turmaId][$dia][$tempo + $i])
            ) {
                return true;
            }
        }

        return false;
    }

    private function getDuracaoTempos(string $tipo): int {
        return match ($tipo) {
            'simples' => 1,
            'dupla' => 2,
            'tripla' => 3,
            default => 1,
        };
    }
}
