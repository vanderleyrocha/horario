<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Metrics;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface DiversityCalculatorInterface
{
    /**
     * Calcula diversidade genética normalizada (0–1)
     *
     * @param  Cromossomo[]  $population
     */
    public function calculate(array $population): float;
}
