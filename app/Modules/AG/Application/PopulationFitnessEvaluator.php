<?php

declare(strict_types=1);

namespace App\Modules\AG\Application;

use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use Spatie\Async\Pool;

final class PopulationFitnessEvaluator {
    public function __construct(private readonly GeneticProblem $problem, private readonly int $concurrency = 8) {
    }

    /**
     * @param Cromossomo[] $population
     */
    public function evaluate(array $population): void {
        $pool = Pool::create()->concurrency($this->concurrency);

        foreach ($population as $individual) {

            $pool->add(function () use ($individual) {

                $this->problem->evaluate($individual);

                return $individual;
            })->then(function (Cromossomo $individual) {

                // fitness já definido

            });
        }

        $pool->wait();
    }
}
