<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Contracts;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface FitnessEvaluatorInterface
{
    /**
     * Avalia o fitness de uma população inteira.
     *
     * @param  Cromossomo[]  $population
     */
    public function evaluate(array $population): void;
}
