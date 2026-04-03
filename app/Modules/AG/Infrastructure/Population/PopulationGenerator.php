<?php

declare(strict_types=1);

namespace App\Modules\AG\Infrastructure\Population;

use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\AG\Support\AGErrorFactory;
use App\Modules\AG\Support\DTO\GeneticAlgorithmConfigDTO;
use App\Modules\AG\Support\Exceptions\InviableScheduleException;
use App\Modules\Horarios\Domain\Analysis\DTO\FeasibilityReport;
use App\Modules\Horarios\Domain\Analysis\ScheduleFeasibilityAnalyzer;

final class PopulationGenerator
{
    private int $expectedGeneCount;

    private int $structuralRiskIndex = 0;

    private array $profLoad = [];

    private array $classLoad = [];

    public function __construct(
        private readonly array $aulas,
        private readonly GeneticAlgorithmConfigDTO $config,
        private readonly ?ProgressReporterInterface $progressReporter = null
    ) {
        $this->expectedGeneCount = $this->calculateExpectedGeneCount();

        $this->precomputeLoads();
    }

    /**
     * @return Cromossomo[]
     */
    public function generate(): array
    {
        $report = $this->validateStructuralFeasibility();

        $this->structuralRiskIndex = $report->riskIndex();

        $population = [];

        $attempts = 0;
        $maxAttempts = $this->config->tamanhoPopulacao * 4;

        while (count($population) < $this->config->tamanhoPopulacao && $attempts < $maxAttempts) {

            $attempts++;

            $this->progressReporter?->report([
                'status' => 'executando',
                'fase' => 'populacao',
                'atual' => count($population) + 1,
                'total' => $this->config->tamanhoPopulacao,
            ]);

            try {

                $population[] = $this->createChromosome();
            } catch (\Throwable) {
                // tentativa falhou
            }
        }

        if (count($population) !== $this->config->tamanhoPopulacao) {

            throw new InviableScheduleException(
                AGErrorFactory::populationGargalo(
                    report: $report,
                    estatisticas: [
                        'gerados' => count($population),
                        'esperado' => $this->config->tamanhoPopulacao,
                        'tentativas' => $attempts,
                    ]
                )
            );
        }

        return $population;
    }

    private function createChromosome(): Cromossomo
    {
        $orderedAulas = $this->orderByDifficulty($this->aulas);

        $slots = $this->config->horariosDisponiveis;

        $risk = $this->structuralRiskIndex;

        $maxAttempts = match (true) {
            $risk >= 80 => 15,
            $risk >= 60 => 25,
            default => 40,
        };

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {

            $genes = [];

            $profIndex = [];
            $turmaIndex = [];

            foreach ($orderedAulas as $aula) {

                $duracao = $this->durationFromTipo($aula->tipo);

                for ($i = 0; $i < $aula->aulas_semana; $i++) {

                    $slot = $this->findBestSlot($aula, $slots, $duracao, $profIndex, $turmaIndex);

                    if (! $slot) {
                        continue 2;
                    }

                    $this->indexSlot($aula, $slot, $duracao, $profIndex, $turmaIndex);

                    $genes[] = $this->buildGene($aula, $slot, $duracao);
                }
            }

            if (count($genes) === $this->expectedGeneCount) {

                $genes = $this->injectControlledNoise($genes, $risk, $profIndex, $turmaIndex);

                return new Cromossomo($genes);
            }
        }

        throw new InviableScheduleException(
            AGErrorFactory::populationFailure([
                'codigo_diagnostico' => 'AG-011',
                'mensagem_diagnostico' => 'Falha na geração heurística.',
                'expected_genes' => $this->expectedGeneCount,
            ])
        );
    }

    private function findBestSlot($aula, array $slots, int $duracao, array $profIndex, array $turmaIndex): ?array
    {

        $bestScore = PHP_INT_MAX;
        $bestSlot = null;

        foreach ($slots as $slot) {

            $dia = $slot['dia'];
            $tempo = $slot['tempo'];

            if ($tempo + $duracao - 1 > $this->config->aulasPorDia) {
                continue;
            }

            $score = 0;

            for ($i = 0; $i < $duracao; $i++) {

                if (isset($profIndex[$aula->professor->id][$dia][$tempo + $i])) {
                    $score += 20;
                }

                if (isset($turmaIndex[$aula->turma->id][$dia][$tempo + $i])) {
                    $score += 20;
                }
            }

            if ($score === 0) {
                return $slot;
            }

            if ($score < $bestScore) {
                $bestScore = $score;
                $bestSlot = $slot;
            }
        }

        return $bestSlot;
    }

    private function injectControlledNoise(array $genes, int $risk, array $profIndex, array $turmaIndex): array
    {

        $mutationRate = match (true) {
            $risk >= 80 => 0.01,
            $risk >= 60 => 0.03,
            default => 0.08,
        };

        foreach ($genes as $index => $gene) {

            if (mt_rand() / mt_getrandmax() <= $mutationRate) {

                $novoDia = random_int(1, $this->config->diasSemana);
                $novoPeriodo = random_int(1, $this->config->aulasPorDia);

                $tentativa = $gene->withDiaPeriodo(
                    $novoDia,
                    $novoPeriodo
                );

                if (! $this->hasConflict(
                    $tentativa,
                    $profIndex,
                    $turmaIndex
                )) {

                    $genes[$index] = $tentativa;
                }
            }
        }

        return $genes;
    }

    private function precomputeLoads(): void
    {
        foreach ($this->aulas as $aula) {

            $duracao = $this->durationFromTipo($aula->tipo);

            $this->profLoad[$aula->professor->id] = ($this->profLoad[$aula->professor->id] ?? 0) + $aula->aulas_semana * $duracao;

            $this->classLoad[$aula->turma->id] = ($this->classLoad[$aula->turma->id] ?? 0) + $aula->aulas_semana * $duracao;
        }
    }

    private function orderByDifficulty(array $aulas): array
    {
        usort($aulas, function ($a, $b) {

            $scoreA =
                ($a->aulas_semana * $this->durationFromTipo($a->tipo))
                + ($this->profLoad[$a->professor->id] ?? 0)
                + ($this->classLoad[$a->turma->id] ?? 0);

            $scoreB =
                ($b->aulas_semana * $this->durationFromTipo($b->tipo))
                + ($this->profLoad[$b->professor->id] ?? 0)
                + ($this->classLoad[$b->turma->id] ?? 0);

            return $scoreB <=> $scoreA;
        });

        return $aulas;
    }

    private function validateStructuralFeasibility(): FeasibilityReport
    {
        $analyzer = new ScheduleFeasibilityAnalyzer;

        $report = $analyzer->analisar(
            $this->aulas,
            $this->config->diasSemana,
            $this->config->aulasPorDia
        );

        if (! $report->isFeasible()) {

            throw new InviableScheduleException(
                AGErrorFactory::populationInfeasible($report)
            );
        }

        return $report;
    }

    private function durationFromTipo(string $tipo): int
    {
        return match ($tipo) {
            'simples' => 1,
            'dupla' => 2,
            'tripla' => 3,
            default => 1,
        };
    }

    private function calculateExpectedGeneCount(): int
    {
        $total = 0;

        foreach ($this->aulas as $aula) {

            $total += $aula->aulas_semana
                * $this->durationFromTipo($aula->tipo);
        }

        return $total;
    }

    private function hasConflict(Gene $gene, array $profIndex, array $turmaIndex): bool
    {

        $prof = $gene->professorId();
        $turma = $gene->turmaId();
        $dia = $gene->diaSemana();
        $tempo = $gene->periodoDia();
        $duracao = $gene->duracaoTempos();

        for ($i = 0; $i < $duracao; $i++) {

            if (isset($profIndex[$prof][$dia][$tempo + $i])) {
                return true;
            }

            if (isset($turmaIndex[$turma][$dia][$tempo + $i])) {
                return true;
            }
        }

        return false;
    }

    private function indexSlot($aula, array $slot, int $duracao, array &$profIndex, array &$turmaIndex): void
    {

        $dia = $slot['dia'];
        $tempoInicial = $slot['tempo'];

        $professorId = $aula->professor->id;
        $turmaId = $aula->turma->id;

        for ($i = 0; $i < $duracao; $i++) {

            $tempo = $tempoInicial + $i;

            $profIndex[$professorId][$dia][$tempo] = true;
            $turmaIndex[$turmaId][$dia][$tempo] = true;
        }
    }

    private function buildGene($aula, array $slot, int $duracao): Gene
    {

        return new Gene(
            aulaId: $aula->id,
            professorId: $aula->professor->id,
            turmaId: $aula->turma->id,
            disciplinaId: $aula->disciplina->id,
            diaSemana: $slot['dia'],
            periodoDia: $slot['tempo'],
            duracaoTempos: $duracao
        );
    }
}
