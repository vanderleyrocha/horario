<?php

namespace App\Modules\AG\Domain\Operators;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

interface SelectionOperatorInterface {
    public function select(array $population): Cromossomo;
}
