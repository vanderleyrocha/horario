<?php

namespace App\Modules\AG\Domain\Operators\Selection;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface SelectionOperatorInterface
{
    public function select(array $population): Cromossomo;
}
