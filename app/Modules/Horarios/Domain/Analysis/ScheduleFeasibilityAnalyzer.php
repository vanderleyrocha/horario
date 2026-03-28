<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Analysis;

use App\Modules\Horarios\Domain\Analysis\DTO\FeasibilityReport;
use App\Modules\Horarios\Domain\Risk\RiskIndexCalculator;
use App\Modules\Horarios\Domain\Risk\SaturationCalculator;
use App\Modules\Horarios\Domain\Risk\StructuralEntropyCalculator;

final class ScheduleFeasibilityAnalyzer
{
    public function __construct(
        private readonly ?SaturationCalculator $saturationCalculator = null,
        private readonly ?StructuralEntropyCalculator $structuralEntropyCalculator = null,
        private readonly ?RiskIndexCalculator $riskIndexCalculator = null
    ) {
    }

    public function analisar(array $aulas, int $dias, int $temposPorDia): FeasibilityReport
    {
        if ($aulas === []) {
            return new FeasibilityReport(isFeasible: true, globalSaturation: 0.0);
        }

        $saturationCalculator = $this->saturationCalculator ?? new SaturationCalculator();
        $structuralEntropyCalculator = $this->structuralEntropyCalculator ?? new StructuralEntropyCalculator();
        $riskIndexCalculator = $this->riskIndexCalculator ?? new RiskIndexCalculator();

        $capacityPerEntity = $dias * $temposPorDia;
        $globalLoad = 0;
        $loadsByClass = [];
        $loadsByProfessor = [];
        $requiredBlocks = 0;

        foreach ($aulas as $aula) {
            $duration = match ($aula->tipo) {
                'simples' => 1,
                'dupla' => 2,
                'tripla' => 3,
                default => 1,
            };

            $load = $aula->aulas_semana * $duration;
            $globalLoad += $load;

            $loadsByClass[$aula->turma->id] = ($loadsByClass[$aula->turma->id] ?? 0) + $load;
            $loadsByProfessor[$aula->professor->id] = ($loadsByProfessor[$aula->professor->id] ?? 0) + $load;

            if ($duration > 1) {
                $requiredBlocks += $aula->aulas_semana;
            }
        }

        $totalClasses = count($loadsByClass);

        if ($totalClasses === 0) {
            return new FeasibilityReport(isFeasible: true, globalSaturation: 0.0);
        }

        $globalSaturation = $saturationCalculator->global($globalLoad, $totalClasses, $dias, $temposPorDia);
        $turmaOverloads = $saturationCalculator->overloads($loadsByClass, $capacityPerEntity);
        $professorOverloads = $saturationCalculator->overloads($loadsByProfessor, $capacityPerEntity);

        $availableBlocks = $totalClasses * $dias * max($temposPorDia - 1, 0);
        $doubleBlockIssues = [];

        if ($requiredBlocks > $availableBlocks) {
            $doubleBlockIssues[] = [
                'duracao' => 'dupla_ou_maior',
                'blocos_disponiveis' => $availableBlocks,
                'necessarios' => $requiredBlocks,
                'deficit' => $requiredBlocks - $availableBlocks,
            ];
        }

        $structuralEntropy = $structuralEntropyCalculator->calculate(array_merge(
            array_values($loadsByClass),
            array_values($loadsByProfessor)
        ));

        $structuralBottlenecks = $this->buildStructuralBottlenecks(
            $globalSaturation,
            $turmaOverloads,
            $professorOverloads,
            $doubleBlockIssues,
            $structuralEntropy
        );

        $isFeasible = empty($turmaOverloads)
            && empty($doubleBlockIssues)
            && $globalSaturation <= 100;

        $suggestions = $this->gerarSugestoes($globalSaturation, $turmaOverloads, $professorOverloads, $doubleBlockIssues, $structuralEntropy);

        $riskIndex = $riskIndexCalculator->calculate(
            $globalSaturation,
            $turmaOverloads,
            $professorOverloads,
            $doubleBlockIssues,
            $structuralEntropy
        );

        return new FeasibilityReport(
            isFeasible: $isFeasible,
            globalSaturation: $globalSaturation,
            turmaOverloads: $turmaOverloads,
            professorOverloads: $professorOverloads,
            doubleBlockIssues: $doubleBlockIssues,
            structuralBottlenecks: $structuralBottlenecks,
            suggestions: $suggestions,
            riskIndex: $riskIndex,
            structuralEntropy: $structuralEntropy
        );
    }

    private function gerarSugestoes(
        float $saturacao,
        array $turmas,
        array $professores,
        array $blocos,
        float $entropiaEstrutural
    ): array {
        $sugestoes = [];

        if ($saturacao > 100) {
            $sugestoes[] = 'Aumentar periodos por dia ou reduzir carga global.';
        }

        foreach ($turmas as $id => $dados) {
            $sugestoes[] = "Reduzir carga da turma {$id} em {$dados['excedente']} tempos.";
        }

        foreach ($professores as $id => $dados) {
            $sugestoes[] = "Redistribuir {$dados['excedente']} tempos do professor {$id}.";
        }

        if ($blocos !== []) {
            $sugestoes[] = 'Reduzir aulas duplas/triplas ou permitir janelas.';
        }

        if ($entropiaEstrutural < 45) {
            $sugestoes[] = 'Rebalancear a distribuicao estrutural da carga entre turmas e professores.';
        }

        return $sugestoes;
    }

    private function buildStructuralBottlenecks(
        float $globalSaturation,
        array $turmaOverloads,
        array $professorOverloads,
        array $doubleBlockIssues,
        float $structuralEntropy
    ): array {
        $bottlenecks = [];

        if ($globalSaturation > 100) {
            $bottlenecks[] = [
                'tipo' => 'saturacao_global',
                'valor' => round($globalSaturation, 2),
            ];
        }

        if ($turmaOverloads !== []) {
            $bottlenecks[] = [
                'tipo' => 'excesso_turmas',
                'valor' => count($turmaOverloads),
            ];
        }

        if ($professorOverloads !== []) {
            $bottlenecks[] = [
                'tipo' => 'excesso_professores',
                'valor' => count($professorOverloads),
            ];
        }

        if ($doubleBlockIssues !== []) {
            $bottlenecks[] = [
                'tipo' => 'deficit_blocos',
                'valor' => array_sum(array_map(
                    static fn (array $issue): int => (int) ($issue['deficit'] ?? 0),
                    $doubleBlockIssues
                )),
            ];
        }

        if ($structuralEntropy < 45) {
            $bottlenecks[] = [
                'tipo' => 'entropia_estrutural_baixa',
                'valor' => round($structuralEntropy, 2),
            ];
        }

        return $bottlenecks;
    }
}
