<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Analysis\DTO;

use App\Modules\Horarios\Domain\Risk\RiskClassification;

final class FeasibilityReport
{
    private bool $isFeasible;

    private float $globalSaturation;

    private array $turmaOverloads;

    private array $professorOverloads;

    private array $doubleBlockIssues;

    private array $structuralBottlenecks;

    private array $suggestions;

    private int $riskIndex;

    private float $structuralEntropy;

    public function __construct(
        bool $isFeasible,
        float $globalSaturation,
        array $turmaOverloads = [],
        array $professorOverloads = [],
        array $doubleBlockIssues = [],
        array $structuralBottlenecks = [],
        array $suggestions = [],
        int $riskIndex = 0,
        float $structuralEntropy = 0.0
    ) {
        $this->isFeasible = $isFeasible;
        $this->globalSaturation = $globalSaturation;
        $this->turmaOverloads = $turmaOverloads;
        $this->professorOverloads = $professorOverloads;
        $this->doubleBlockIssues = $doubleBlockIssues;
        $this->structuralBottlenecks = $structuralBottlenecks;
        $this->suggestions = $suggestions;
        $this->riskIndex = max(0, min(100, $riskIndex));
        $this->structuralEntropy = max(0.0, min(100.0, $structuralEntropy));
    }

    public function isFeasible(): bool
    {
        return $this->isFeasible;
    }

    public function globalSaturation(): float
    {
        return $this->globalSaturation;
    }

    public function turmaOverloads(): array
    {
        return $this->turmaOverloads;
    }

    public function professorOverloads(): array
    {
        return $this->professorOverloads;
    }

    public function doubleBlockIssues(): array
    {
        return $this->doubleBlockIssues;
    }

    public function structuralBottlenecks(): array
    {
        return $this->structuralBottlenecks;
    }

    public function suggestions(): array
    {
        return $this->suggestions;
    }

    public function riskIndex(): int
    {
        return $this->riskIndex;
    }

    public function structuralEntropy(): float
    {
        return $this->structuralEntropy;
    }

    public function riskLevel(): string
    {
        return (new RiskClassification)->classify($this->riskIndex);
    }

    public function toArray(): array
    {
        return [
            'is_feasible' => $this->isFeasible,
            'global_saturation' => round($this->globalSaturation, 2),
            'risk_index' => $this->riskIndex,
            'risk_level' => $this->riskLevel(),
            'structural_entropy' => round($this->structuralEntropy, 2),
            'turmas_criticas' => $this->turmaOverloads,
            'professores_criticos' => $this->professorOverloads,
            'aulas_duplas_problema' => $this->doubleBlockIssues,
            'gargalos_estruturais' => $this->structuralBottlenecks,
            'sugestoes' => $this->suggestions,
        ];
    }
}
