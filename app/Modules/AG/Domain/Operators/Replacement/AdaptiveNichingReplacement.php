<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Replacement;

use App\Modules\AG\Domain\Metrics\GeneticDistance;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class AdaptiveNichingReplacement implements ReplacementStrategyInterface
{
    private SimilarityReplacement $similarity;

    private CrowdingReplacement $crowding;

    private WorstIndividualReplacement $worst;

    public function __construct(GeneticDistance $distance)
    {
        $this->similarity = new SimilarityReplacement($distance, 12);
        $this->crowding = new CrowdingReplacement($distance);
        $this->worst = new WorstIndividualReplacement(2);
    }

    public function replace(array &$population, Cromossomo $incoming): void
    {
        if (empty($population)) {
            $population[] = $incoming;
            return;
        }

        /*
        =====================================================
        calcular diversidade aproximada
        =====================================================
        */

        $diversity = $this->estimateDiversity($population);

        /*
        =====================================================
        escolher estratégia
        =====================================================
        */

        if ($diversity < 0.08) {

            /*
            população colapsando
            → usar crowding forte
            */

            $this->crowding->replace($population, $incoming);
            return;
        }

        if ($diversity < 0.18) {

            /*
            diversidade moderada
            → similarity replacement
            */

            $this->similarity->replace($population, $incoming);
            return;
        }

        /*
        diversidade alta
        → exploração global
        */

        $this->worst->replace($population, $incoming);
    }

    private function estimateDiversity(array $population): float
    {
        $size = count($population);

        if ($size < 2) {
            return 1.0;
        }

        $sample = min(8, $size - 1);

        $sum = 0;
        $count = 0;

        for ($i = 0; $i < $sample; $i++) {

            $a = $population[array_rand($population)];
            $b = $population[array_rand($population)];

            $sum += abs($a->fitness() - $b->fitness());
            $count++;
        }

        if ($count === 0) {
            return 1.0;
        }

        return $sum / $count;
    }
}
