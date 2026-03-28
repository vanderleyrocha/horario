<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS;

use App\Modules\AG\Domain\Operators\EvolutionaryOperatorInterface;

final class RouletteWheelSelector implements OperatorSelectionStrategy
{
    public function select(array $operators, array $stats): object
    {
        if ($operators === []) {
            throw new \RuntimeException('RouletteWheelSelector recebeu operadores vazios.');
        }

        $totalWeight = 0.0;

        foreach ($operators as $operator) {
            $totalWeight += $this->weightOf($operator, $stats);
        }

        if ($totalWeight <= 0.0) {
            return $operators[array_key_first($operators)];
        }

        $random = (mt_rand() / mt_getrandmax()) * $totalWeight;
        $accumulated = 0.0;

        foreach ($operators as $operator) {
            $accumulated += $this->weightOf($operator, $stats);

            if ($random <= $accumulated) {
                return $operator;
            }
        }

        return $operators[array_key_first($operators)];
    }

    /**
     * @param  array<string, array<string, float|int|string|null>>  $stats
     */
    private function weightOf(object $operator, array $stats): float
    {
        if (! $operator instanceof EvolutionaryOperatorInterface) {
            return 1.0;
        }

        $name = $operator->getName();

        return (float) ($stats[$name]['weight'] ?? 1.0);
    }
}
