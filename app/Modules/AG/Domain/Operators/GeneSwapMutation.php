<?php

namespace App\Modules\AG\Domain\Operators;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

final class GeneSwapMutation implements MutationOperatorInterface {
    public function mutate(Cromossomo $individual): Cromossomo {
        $i = random_int(0, $individual->count() - 1);
        $j = random_int(0, $individual->count() - 1);

        $individual->swapGenes($i, $j);

        return $individual;
    }
}
