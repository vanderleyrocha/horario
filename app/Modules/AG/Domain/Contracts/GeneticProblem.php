<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Contracts;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;
use App\Modules\AG\Domain\Fitness\FitnessResult;

interface GeneticProblem {
    /**
     * Cria um indivíduo inicial válido ou parcialmente válido.
     */
    public function createIndividual(): Cromossomo;

    /**
     * Avalia completamente o indivíduo.
     */
    public function evaluate(Cromossomo $individual): FitnessResult;

    /**
     * Repara indivíduo após crossover/mutation.
     */
    public function repair(Cromossomo $individual): Cromossomo;

    /**
     * Verifica se o indivíduo é estruturalmente viável.
     */
    public function isFeasible(Cromossomo $individual): bool;
}
