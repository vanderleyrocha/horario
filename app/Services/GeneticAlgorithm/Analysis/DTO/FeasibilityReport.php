<?php

namespace App\Services\GeneticAlgorithm\Analysis\DTO;

final class FeasibilityReport {
    public bool $isFeasible = true;

    public float $globalSaturation = 0;

    public array $turmaOverloads = [];
    public array $professorOverloads = [];
    public array $doubleBlockIssues = [];

    public array $suggestions = [];

    public int $riskIndex = 0;

    public function toArray(): array {
        return [
            'is_feasible' => $this->isFeasible,
            'global_saturation' => round($this->globalSaturation, 2) . '%',
            'turmas' => $this->turmaOverloads,
            'professores' => $this->professorOverloads,
            'aulas_duplas' => $this->doubleBlockIssues,
            'sugestoes' => $this->suggestions,
            'risk_index' => $this->riskIndex,
        ];
    }
}
