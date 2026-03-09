<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Contracts;

use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface GeneticProblem {
    /**
     * Cria indivíduo inicial.
     */
    public function createIndividual(): Cromossomo;

    /**
     * Avaliação completa.
     */
    public function evaluate(Cromossomo $individual): FitnessResult;

    /**
     * Avaliação incremental (Delta Fitness).
     */
    public function evaluateDelta(Cromossomo $individual, AffectedRegion $region, FitnessResult $previous): FitnessResult;

    /**
     * Repara indivíduo após operadores genéticos.
     */
    public function repair(Cromossomo $individual): Cromossomo;

    /**
     * Verifica viabilidade estrutural.
     */
    public function isFeasible(Cromossomo $individual): bool;

    /**
     * Limpa caches internos.
     */
    public function clearFitnessCache(): void;
}
