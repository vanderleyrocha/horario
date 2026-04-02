<?php

namespace App\Modules\AG\Domain\Fitness;

use InvalidArgumentException;

final class FitnessWeights {
    private array $weights;

    public function __construct(
        array $weights = [],
        private readonly float $defaultWeight = 1.0
    ) {
        $this->weights = [];

        foreach ($weights as $ruleClass => $weight) {

            if (!is_numeric($weight)) {
                throw new InvalidArgumentException("Peso inválido para {$ruleClass}");
            }

            $weight = (float) $weight;

            if ($weight < 0) {
                throw new InvalidArgumentException("Peso não pode ser negativo ({$ruleClass})");
            }

            $this->weights[$ruleClass] = $weight;
        }

        if ($this->defaultWeight < 0) {
            throw new InvalidArgumentException("defaultWeight não pode ser negativo");
        }
    }

    public function get(string $ruleClass): float {
        return $this->weights[$ruleClass] ?? $this->defaultWeight;
    }

    public function all(): array {
        return $this->weights;
    }

    public function has(string $ruleClass): bool {
        return array_key_exists($ruleClass, $this->weights);
    }

    /**
     * Factory padrão recomendada
     */
    public static function default(): self {
        return new self([
            // Hard Rules
            \App\Modules\Horarios\Domain\Evaluation\HardRules\TeacherConflictRule::class => 10.0,
            \App\Modules\Horarios\Domain\Evaluation\HardRules\ClassConflictRule::class => 10.0,
            \App\Modules\Horarios\Domain\Evaluation\HardRules\WorkloadExceededRule::class => 8.0,
            \App\Modules\Horarios\Domain\Evaluation\HardRules\MandatoryBlockViolationRule::class => 6.0,
            \App\Modules\Horarios\Domain\Evaluation\HardRules\CustomConstraintHardRule::class => 1.0,

            // Soft Rules
            \App\Modules\Horarios\Domain\Evaluation\SoftRules\WindowPenaltyRule::class => 2.0,
            \App\Modules\Horarios\Domain\Evaluation\SoftRules\DistributionRule::class => 2.0,
            \App\Modules\Horarios\Domain\Evaluation\SoftRules\MaxLessonsPerDayRule::class => 1.5,
            \App\Modules\Horarios\Domain\Evaluation\SoftRules\ConsecutiveLessonRule::class => 1.0,
            \App\Modules\Horarios\Domain\Evaluation\SoftRules\PreferredTimeRule::class => 1.0,
            \App\Modules\Horarios\Domain\Evaluation\SoftRules\CustomConstraintSoftRule::class => 1.0,
        ]);
    }
}
