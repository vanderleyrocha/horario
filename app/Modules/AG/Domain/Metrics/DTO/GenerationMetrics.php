<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Metrics\DTO;

class GenerationMetrics
{
    public int $generation;

    public float $bestFitness;

    public float $avgFitness;

    public float $variance;

    public float $diversity;

    public float $entropy;

    public float $mutationRate;

    public int $stagnation;

    public ?string $landscapeState;

    public ?string $operatorUsed;

    public ?float $operatorReward;

    public ?string $alnsDestroyOperator;

    public ?string $alnsRepairOperator;

    public ?float $alnsImprovement;

    public function __construct(
        int $generation,
        float $bestFitness,
        float $avgFitness,
        float $variance,
        float $diversity,
        float $entropy,
        float $mutationRate,
        int $stagnation,
        ?string $landscapeState = null,
        ?string $operatorUsed = null,
        ?float $operatorReward = null,
        ?string $alnsDestroyOperator = null,
        ?string $alnsRepairOperator = null,
        ?float $alnsImprovement = null
    ) {
        $this->generation = $generation;
        $this->bestFitness = $bestFitness;
        $this->avgFitness = $avgFitness;
        $this->variance = $variance;
        $this->diversity = $diversity;
        $this->entropy = $entropy;
        $this->mutationRate = $mutationRate;
        $this->stagnation = $stagnation;
        $this->landscapeState = $landscapeState;
        $this->operatorUsed = $operatorUsed;
        $this->operatorReward = $operatorReward;
        $this->alnsDestroyOperator = $alnsDestroyOperator;
        $this->alnsRepairOperator = $alnsRepairOperator;
        $this->alnsImprovement = $alnsImprovement;
    }

    public function toArray(): array
    {
        return [
            'generation' => $this->generation,
            'best_fitness' => $this->bestFitness,
            'avg_fitness' => $this->avgFitness,
            'variance' => $this->variance,
            'diversity' => $this->diversity,
            'entropy' => $this->entropy,
            'mutation_rate' => $this->mutationRate,
            'stagnation' => $this->stagnation,
            'landscape_state' => $this->landscapeState,
            'operator_used' => $this->operatorUsed,
            'operator_reward' => $this->operatorReward,
            'alns_destroy_operator' => $this->alnsDestroyOperator,
            'alns_repair_operator' => $this->alnsRepairOperator,
            'alns_improvement' => $this->alnsImprovement,
        ];
    }
}
