<?php

namespace App\Modules\AG\Domain\Operators;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

interface MutationOperatorInterface {
    public function mutate(Cromossomo $individual): Cromossomo;
}
