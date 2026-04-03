<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Conflict;

class Conflict
{
    public function __construct(public readonly int $geneIndex) {}
}
