<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

interface OperatorSelectionStrategy
{
    public function select(array $operators): string;
}
