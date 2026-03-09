<?php

namespace App\Modules\AG\Domain\Operators\Mutation;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface MutationOperatorInterface {
    public function mutate(Cromossomo $individual): Cromossomo;
}
