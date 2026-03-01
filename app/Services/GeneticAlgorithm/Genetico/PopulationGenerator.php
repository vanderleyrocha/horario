<?php

namespace App\Services\GeneticAlgorithm\Genetico;

use App\Services\GeneticAlgorithm\Analysis\ScheduleFeasibilityAnalyzer;
use App\Services\GeneticAlgorithm\Exceptions\InviableScheduleException;
use App\Services\GeneticAlgorithm\Support\AGErrorFactory;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;
use Closure;
use Illuminate\Support\Facades\Log;

final class PopulationGenerator {
    private int $expectedGeneCount;

    private ?Closure $progressCallback = null;

    public function __construct(private readonly array $aulas, private readonly GeneticAlgorithmConfigDTO $configAG, ?Closure $progressCallback = null) {
        $this->expectedGeneCount = $this->calcularTotalGenesEsperados();
        $this->progressCallback = $progressCallback;
    }

    public function generate(): array {
        $analyzer = new ScheduleFeasibilityAnalyzer();
        $report = $analyzer->analisar($this->aulas, $this->configAG->diasSemana, $this->configAG->aulasPorDia);

        if (!$report->isFeasible) {
            throw new InviableScheduleException(AGErrorFactory::populationInfeasible($report));
        }

        Log::info("Iniciando geração da população inicial", [
            'tamanho_populacao' => $this->configAG->tamanhoPopulacao,
            'genes_esperados_por_cromossomo' => $this->expectedGeneCount,
        ]);

        $population = [];
        $falhas = [];

        for ($i = 0; $i < $this->configAG->tamanhoPopulacao; $i++) {

            try {

                if ($this->progressCallback) {
                    ($this->progressCallback)(fase: 'populacao', atual: $i + 1, total: $this->configAG->tamanhoPopulacao);
                }

                $cromossomo = $this->createDiverseCromossomo($i);

                if ($cromossomo->count() !== $this->expectedGeneCount) {
                    throw new \RuntimeException("Cromossomo inválido: tamanho incorreto.");
                }

                $population[] = $cromossomo;
            } catch (\Throwable $e) {
                $falhas[] = [
                    'seed' => $i,
                    'erro' => $e->getMessage()
                ];
            }
        }

        if (count($falhas)) {
            Log::warning("Falhas ocorridas durante a geração da população inicial", [
                'total_falhas' => count($falhas),
                'exemplos_falhas' => array_slice($falhas, 0, 5)
            ]);
        }

        if (count($population) !== $this->configAG->tamanhoPopulacao) {

            $analyzer = new ScheduleFeasibilityAnalyzer();

            $report = $analyzer->analisar($this->aulas, $this->configAG->diasSemana, $this->configAG->aulasPorDia);

            /*
            |--------------------------------------------------------------------------
            | Se estruturalmente inviável → AG-010 detalhado
            |--------------------------------------------------------------------------
            */
            if (!$report->isFeasible) {
                throw new InviableScheduleException(AGErrorFactory::populationInfeasible($report));
            }

            /*
            |--------------------------------------------------------------------------
            | Estruturalmente viável mas falhou 100 vezes → Gargalo estrutural
            |--------------------------------------------------------------------------
            */

            $report->suggestions[] =
                "Configuração estrutural extremamente restritiva. Considerar permitir janelas.";

            $report->suggestions[] =
                "Reduzir número de aulas duplas.";

            $report->suggestions[] =
                "Aumentar períodos por dia.";

            throw new InviableScheduleException(
                AGErrorFactory::populationGargalo($report, [
                    'gerados' => count($population),
                    'esperado' => $this->configAG->tamanhoPopulacao,
                    'falhas' => count($falhas),
                ])
            );
        }

        return $population;
    }

    private function createDiverseCromossomo(int $seedOffset): Cromossomo {
        $aulasOrdenadas = $this->ordenarAulasPorDificuldade($this->aulas);
        $horariosBase = $this->configAG->horariosDisponiveis;

        $maxTentativas = 50;

        $contagemDeGenesPorTentativa = [];

        for ($tentativa = 0; $tentativa < $maxTentativas; $tentativa++) {

            $genes = [];
            $ocupacaoProfessor = [];
            $ocupacaoTurma = [];

            foreach ($aulasOrdenadas as $aula) {

                $duracao = $this->getDuracaoTempos($aula->tipo);

                for ($aloc = 0; $aloc < $aula->aulas_semana; $aloc++) {

                    $resultado = $this->encontrarHorarioViavel(
                        $aula,
                        $horariosBase,
                        $duracao,
                        $ocupacaoProfessor,
                        $ocupacaoTurma
                    );

                    if (!$resultado['sucesso']) {
                        // tentativa falhou → reiniciar cromossomo
                        continue 2;
                    }

                    $dia = $resultado['dia'];
                    $tempo = $resultado['tempo'];

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

            $contagemDeGenesPorTentativa[] = count($genes);

            if (count($genes) === $this->expectedGeneCount) {
                return new Cromossomo($genes);
            }
        }

        // Log::error("Falha crítica ao gerar cromossomo completo após múltiplas tentativas", [
        //     'expected_genes' => $this->expectedGeneCount,
        //     'tentativas' => $maxTentativas,
        //     'contagem_de_genes_por_tentativa' => $contagemDeGenesPorTentativa,
        // ]);

        throw new InviableScheduleException(
            AGErrorFactory::populationFailure([
                'codigo_diagnostico' => 'AG-011',
                'mensagem_diagnostico' => 'Não foi possível gerar cromossomo completo após múltiplas tentativas.',
                'expected_genes' => $this->expectedGeneCount
            ])
        );
    }

    private function encontrarHorarioViavel($aula, array $horarios, int $duracao, array $ocupacaoProfessor, array $ocupacaoTurma): array {

        shuffle($horarios);

        foreach ($horarios as $horario) {

            $dia = $horario['dia'];
            $tempo = $horario['tempo'];

            if ($tempo + $duracao - 1 > $this->configAG->aulasPorDia) {
                continue;
            }

            for ($i = 0; $i < $duracao; $i++) {

                if (isset($ocupacaoProfessor[$aula->professor->id][$dia][$tempo + $i])) {
                    continue 2;
                }

                if (isset($ocupacaoTurma[$aula->turma->id][$dia][$tempo + $i])) {
                    continue 2;
                }
            }

            return [
                'sucesso' => true,
                'dia' => $dia,
                'tempo' => $tempo
            ];
        }

        return ['sucesso' => false];
    }

    private function ordenarAulasPorDificuldade(array $aulas): array {
        usort(
            $aulas,
            fn($a, $b) => ($b->aulas_semana * $this->getDuracaoTempos($b->tipo))
                <=>
                ($a->aulas_semana * $this->getDuracaoTempos($a->tipo))
        );

        return $aulas;
    }

    private function getDuracaoTempos(string $tipo): int {
        return match ($tipo) {
            'simples' => 1,
            'dupla' => 2,
            'tripla' => 3,
            default => 1,
        };
    }

    private function calcularTotalGenesEsperados(): int {
        $total = 0;

        foreach ($this->aulas as $aula) {
            $total += $aula->aulas_semana * $this->getDuracaoTempos($aula->tipo);
        }

        return $total;
    }
}
