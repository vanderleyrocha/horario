<?php

namespace App\Modules\AG\Domain\Operators\Mutation;

use App\Modules\AG\Domain\Operators\EvolutionaryOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface MutationOperatorInterface extends EvolutionaryOperatorInterface
{
    public function mutate(Cromossomo $individual): Cromossomo;

    public function getName(): string;
}
