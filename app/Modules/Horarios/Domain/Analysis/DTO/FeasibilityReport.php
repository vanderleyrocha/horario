<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Analysis\DTO;

final class FeasibilityReport {
    private bool $isFeasible;

    /**
     * Percentual de ocupação global (0 – 200+)
     */
    private float $globalSaturation;

    /**
     * [
     *   turmaId => [
     *      'carga_total' => int,
     *      'capacidade_maxima' => int,
     *      'excedente' => int,
     *      'percentual' => float
     *   ]
     * ]
     */
    private array $turmaOverloads;

    /**
     * [
     *   professorId => [
     *      'carga_total' => int,
     *      'capacidade_maxima' => int,
     *      'excedente' => int,
     *      'percentual' => float
     *   ]
     * ]
     */
    private array $professorOverloads;

    /**
     * [
     *   aulaId => [
     *      'duracao' => int,
     *      'blocos_disponiveis' => int
     *   ]
     * ]
     */
    private array $doubleBlockIssues;

    /**
     * Gargalos estruturais adicionais
     */
    private array $structuralBottlenecks;

    /**
     * Sugestões automáticas
     */
    private array $suggestions;

    /**
     * Índice matemático de risco (0–100)
     */
    private int $riskIndex;

    public function __construct(
        bool $isFeasible,
        float $globalSaturation,
        array $turmaOverloads = [],
        array $professorOverloads = [],
        array $doubleBlockIssues = [],
        array $structuralBottlenecks = [],
        array $suggestions = [],
        int $riskIndex = 0
    ) {
        $this->isFeasible = $isFeasible;
        $this->globalSaturation = $globalSaturation;
        $this->turmaOverloads = $turmaOverloads;
        $this->professorOverloads = $professorOverloads;
        $this->doubleBlockIssues = $doubleBlockIssues;
        $this->structuralBottlenecks = $structuralBottlenecks;
        $this->suggestions = $suggestions;
        $this->riskIndex = max(0, min(100, $riskIndex));
    }

    /* ============================================================
     |  GETTERS
     ============================================================ */

    public function isFeasible(): bool {
        return $this->isFeasible;
    }

    public function globalSaturation(): float {
        return $this->globalSaturation;
    }

    public function turmaOverloads(): array {
        return $this->turmaOverloads;
    }

    public function professorOverloads(): array {
        return $this->professorOverloads;
    }

    public function doubleBlockIssues(): array {
        return $this->doubleBlockIssues;
    }

    public function structuralBottlenecks(): array {
        return $this->structuralBottlenecks;
    }

    public function suggestions(): array {
        return $this->suggestions;
    }

    public function riskIndex(): int {
        return $this->riskIndex;
    }

    /* ============================================================
     |  CLASSIFICAÇÃO DE RISCO
     ============================================================ */

    public function riskLevel(): string {
        return match (true) {
            $this->riskIndex >= 80 => 'CRITICO',
            $this->riskIndex >= 60 => 'ALTO',
            $this->riskIndex >= 40 => 'MODERADO',
            $this->riskIndex >= 20 => 'BAIXO',
            default => 'MINIMO',
        };
    }

    /* ============================================================
     |  SERIALIZAÇÃO (para cache / banco / frontend)
     ============================================================ */

    public function toArray(): array {
        return [
            'is_feasible' => $this->isFeasible,
            'global_saturation' => round($this->globalSaturation, 2),
            'risk_index' => $this->riskIndex,
            'risk_level' => $this->riskLevel(),
            'turmas_criticas' => $this->turmaOverloads,
            'professores_criticos' => $this->professorOverloads,
            'aulas_duplas_problema' => $this->doubleBlockIssues,
            'gargalos_estruturais' => $this->structuralBottlenecks,
            'sugestoes' => $this->suggestions,
        ];
    }
}
