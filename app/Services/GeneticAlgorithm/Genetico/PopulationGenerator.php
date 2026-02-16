<?php

namespace App\Services\GeneticAlgorithm\Genetico;

use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;

final class PopulationGenerator
{
    public function __construct(
        private readonly array $aulas,
        private readonly GeneticAlgorithmConfigDTO $configAG
    ) {}

    public function generate(int $populationSize): array
    {
        $population = [];

        for ($i = 0; $i < $populationSize; $i++) {
            $population[] = $this->createDiverseCromossomo($i);
        }

        return $population;
    }

    /**
     * Gera cromossomo com alta diversidade estrutural
     */
    private function createDiverseCromossomo(int $seedOffset): Cromossomo
    {
        $genes = [];

        $aulas = $this->aulas;
        shuffle($aulas);

        $horarios = $this->configAG->horariosDisponiveis;
        shuffle($horarios);

        $horarioCount = count($horarios);
        $pointer = $seedOffset % max(1, $horarioCount);

        $ocupacaoProfessor = [];
        $ocupacaoTurma = [];

        foreach ($aulas as $aula) {

            $numAlocacoes = (int) $aula->aulas_semana;
            $duracao = $this->getDuracaoTempos($aula->tipo);

            for ($i = 0; $i < $numAlocacoes; $i++) {

                $tentativas = 0;
                $maxTentativas = 30;
                $horarioEscolhido = null;

                while ($tentativas < $maxTentativas) {

                    $horario = $horarios[$pointer];
                    $pointer = ($pointer + 1) % $horarioCount;

                    $dia = $horario['dia'];
                    $tempo = $horario['tempo'];

                    if (!$this->temConflitoBasico(
                        $aula->professor->id,
                        $aula->turma->id,
                        $dia,
                        $tempo,
                        $duracao,
                        $ocupacaoProfessor,
                        $ocupacaoTurma
                    )) {
                        $horarioEscolhido = $horario;
                        break;
                    }

                    $tentativas++;
                }

                if (!$horarioEscolhido) {
                    $horarioEscolhido = $horarios[array_rand($horarios)];
                }

                $dia = $horarioEscolhido['dia'];
                $tempo = $horarioEscolhido['tempo'];

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

    private function temConflitoBasico(
        int $professorId,
        int $turmaId,
        int $dia,
        int $tempo,
        int $duracao,
        array $ocupacaoProfessor,
        array $ocupacaoTurma
    ): bool {

        for ($i = 0; $i < $duracao; $i++) {

            if (
                isset($ocupacaoProfessor[$professorId][$dia][$tempo + $i]) ||
                isset($ocupacaoTurma[$turmaId][$dia][$tempo + $i])
            ) {
                return true;
            }
        }

        return false;
    }

    private function getDuracaoTempos(string $tipo): int
    {
        return match ($tipo) {
            'simples' => 1,
            'dupla' => 2,
            'tripla' => 3,
            default => 1,
        };
    }
}