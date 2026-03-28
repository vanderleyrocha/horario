<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS;

interface OperatorSelectionStrategy
{
    /**
     * @param  object[]  $operators
     * @param  array<string, array<string, float|int|string|null>>  $stats
     */
    public function select(array $operators, array $stats): object;
}
