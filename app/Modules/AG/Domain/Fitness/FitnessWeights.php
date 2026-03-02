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
            // Hard rules (peso alto)
            \App\Modules\AG\Domain\Fitness\Rules\Hard\ConflitoProfessorRule::class => 10.0,
            \App\Modules\AG\Domain\Fitness\Rules\Hard\ConflitoTurmaRule::class => 10.0,
            \App\Modules\AG\Domain\Fitness\Rules\Hard\CargaHorariaExcedidaRule::class => 8.0,
            \App\Modules\AG\Domain\Fitness\Rules\Hard\BloqueiosHardRule::class => 6.0,

            // Soft rules (peso menor)
            \App\Modules\AG\Domain\Fitness\Rules\Soft\JanelasRule::class => 2.0,
            \App\Modules\AG\Domain\Fitness\Rules\Soft\DistribuicaoRule::class => 2.0,
            \App\Modules\AG\Domain\Fitness\Rules\Soft\MaxAulasDiaRule::class => 1.5,
            \App\Modules\AG\Domain\Fitness\Rules\Soft\AulasNaoConsecutivasRule::class => 1.0,
            \App\Modules\AG\Domain\Fitness\Rules\Soft\BloqueiosPreferenciaisRule::class => 1.0,
        ]);
    }
}
